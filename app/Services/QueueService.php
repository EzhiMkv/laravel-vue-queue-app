<?php

namespace App\Services;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\QueuePosition;
use App\Repositories\ClientRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class QueueService
{
    protected KafkaProducerService $kafkaProducer;
    protected RedisService $redisService;
    protected Carbon $startTime;
    
    public function __construct(KafkaProducerService $kafkaProducer, RedisService $redisService){
        $this->kafkaProducer = $kafkaProducer;
        $this->redisService = $redisService;
        $this->startTime = now();
    }
    
    public function addClientToQueue(Client $client): void
    {
        $position = 1;
        $last_position = QueuePosition::orderBy('position', 'desc')->first();
        if($last_position){
            $position = $last_position->position + 1;
        }
        QueuePosition::create(['client_id'=>$client->id, 'position'=>$position]);
        
        // Инкрементируем счетчик клиентов в Redis
        $this->redisService->incrementClientCounter();
        
        // Кэшируем обновленную очередь
        $this->cacheQueueData();
    }

    public function removeClientFromQueue(Client $client): void
    {
        $client_position = $client->position->position;
        DB::statement('UPDATE queue_positions SET position = position - 1 WHERE position > :client_position', ['client_position'=>$client_position]);
        
        // Кэшируем обновленную очередь
        $this->cacheQueueData();
    }

    public function proceed(){
        $first_client_position = QueuePosition::orderBy('position', 'asc')->first();
        $client = null;
        
        if($first_client_position){
            $client = $first_client_position->client;
            
            // Считаем время обслуживания
            $serviceTime = $this->calculateServiceTime();
            
            // Сохраняем статистику в Redis
            $this->redisService->addServiceStats($client->id, $serviceTime);
            
            $client->delete();
            
            // Отправляем событие в Kafka о продвижении очереди
            $this->kafkaProducer->sendQueueProceededEvent($client);
            
            // Сбрасываем таймер обслуживания
            $this->startTime = now();
        }
        
        // Кэшируем обновленную очередь
        return $this->cacheQueueData();
    }

    public function getNextClient(){
        $next_client_position = QueuePosition::orderBy('position', 'asc')->first();
        if($next_client_position){
            return $next_client_position->client;
        }
        return false;
    }

    public function getClientPosition($client_id){
        $client_position = QueuePosition::where('client_id', $client_id)->first();
        if($client_position){
            return ['position'=>$client_position->position];
        }
        return false;
    }

    public function getFullQueue(){
        // Пробуем получить данные из кэша
        $cachedData = $this->redisService->getCachedQueueData();
        
        // Если в кэше нет данных, получаем из БД и кэшируем
        if (!$cachedData) {
            return $this->cacheQueueData();
        }
        
        return $cachedData;
    }
    
    /**
     * Кэширует данные очереди в Redis
     */
    protected function cacheQueueData()
    {
        $queueData = QueuePosition::with('client')->orderBy('position', 'asc')->get();
        $this->redisService->cacheQueueData($queueData->toArray());
        return $queueData;
    }
    
    /**
     * Рассчитывает время обслуживания текущего клиента
     */
    protected function calculateServiceTime(): float
    {
        $now = now();
        $diffInSeconds = $now->diffInSeconds($this->startTime);
        
        // Минимум 1 секунда, чтобы избежать нулевых значений
        return max(1, $diffInSeconds);
    }
    
    /**
     * Получает статистику очереди
     */
    public function getQueueStats(): array
    {
        return $this->redisService->getQueueStats();
    }
}
