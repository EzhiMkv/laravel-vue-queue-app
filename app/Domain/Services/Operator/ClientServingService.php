<?php

namespace App\Domain\Services\Operator;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Operator\ClientServingInterface;
use App\Events\ClientServingFinished;
use App\Events\ClientServingStarted;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Queue;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientServingService implements ClientServingInterface
{
    /**
     * @var CacheServiceInterface
     */
    private $cacheService;

    /**
     * @var OperatorManagementService
     */
    private $managementService;

    /**
     * Префикс для ключей кэша.
     */
    private const CACHE_PREFIX = 'operator:serving:';

    /**
     * Конструктор сервиса.
     *
     * @param CacheServiceInterface $cacheService
     * @param OperatorManagementService $managementService
     */
    public function __construct(
        CacheServiceInterface $cacheService,
        OperatorManagementService $managementService
    ) {
        $this->cacheService = $cacheService;
        $this->managementService = $managementService;
    }

    /**
     * {@inheritdoc}
     */
    public function startServingClient(Operator $operator, Client $client, Queue $queue): ServiceLog
    {
        try {
            DB::beginTransaction();

            // Проверяем, доступен ли оператор
            if (!$operator->isAvailable()) {
                throw new \Exception("Оператор недоступен для обслуживания клиентов");
            }

            // Создаем запись в логе обслуживания
            $position = $client->getPositionInQueue($queue);
            
            $serviceLog = new ServiceLog([
                'queue_id' => $queue->id,
                'client_id' => $client->id,
                'operator_id' => $operator->id,
                'position_id' => $position ? $position->id : null,
                'started_at' => now(),
                'status' => 'in_progress'
            ]);
            
            $serviceLog->save();
            
            // Обновляем статус оператора
            $operator->status = 'busy';
            $operator->save();
            
            // Обновляем статус клиента
            $client->status = 'serving';
            $client->save();
            
            // Обновляем статус позиции, если она существует
            if ($position) {
                $position->status = 'serving';
                $position->serving_at = now();
                $position->save();
            }

            // Обновляем кэш
            $this->managementService->cacheOperatorInfo($operator);
            $this->cacheService->set(self::CACHE_PREFIX . $operator->id, $serviceLog->id, 3600);

            DB::commit();

            // Генерируем событие
            event(new ClientServingStarted($operator, $client, $queue, $serviceLog));

            return $serviceLog;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при начале обслуживания клиента: ' . $e->getMessage(), [
                'operator_id' => $operator->id,
                'client_id' => $client->id,
                'queue_id' => $queue->id,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishServingClient(Operator $operator, ServiceLog $serviceLog, string $status = 'completed', array $data = []): bool
    {
        try {
            DB::beginTransaction();

            // Проверяем, что этот лог принадлежит оператору
            if ($serviceLog->operator_id !== $operator->id) {
                throw new \Exception("Лог обслуживания не принадлежит этому оператору");
            }

            // Обновляем лог обслуживания
            $serviceLog->ended_at = now();
            $serviceLog->service_duration = $serviceLog->started_at->diffInSeconds(now());
            $serviceLog->status = $status;
            
            if (isset($data['notes'])) {
                $serviceLog->notes = $data['notes'];
            }
            
            if (isset($data['metadata'])) {
                $serviceLog->metadata = $data['metadata'];
            }
            
            $serviceLog->save();
            
            // Обновляем статус оператора
            $operator->status = 'available';
            $operator->clients_served_today++;
            $operator->save();
            
            // Обновляем статус клиента
            $client = $serviceLog->client;
            $client->status = 'served';
            $client->save();
            
            // Обновляем статус позиции, если она существует
            if ($serviceLog->position) {
                $serviceLog->position->status = 'served';
                $serviceLog->position->served_at = now();
                $serviceLog->position->save();
            }

            // Обновляем кэш
            $this->managementService->cacheOperatorInfo($operator);
            $this->cacheService->delete(self::CACHE_PREFIX . $operator->id);

            DB::commit();

            // Генерируем событие
            event(new ClientServingFinished($operator, $client, $serviceLog));

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при завершении обслуживания клиента: ' . $e->getMessage(), [
                'operator_id' => $operator->id,
                'service_log_id' => $serviceLog->id,
                'status' => $status,
                'exception' => $e
            ]);
            return false;
        }
    }
}
