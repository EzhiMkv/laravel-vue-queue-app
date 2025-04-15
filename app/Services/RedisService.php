<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisService
{
    /**
     * Префикс для ключей Redis
     */
    private string $prefix = 'queue:';
    
    /**
     * Время жизни кэша по умолчанию (в секундах)
     */
    private int $defaultTtl = 3600;
    
    /**
     * Кэширует данные очереди
     */
    public function cacheQueueData(array $queueData): void
    {
        try {
            Redis::set($this->prefix . 'data', json_encode($queueData), 'EX', $this->defaultTtl);
            Redis::set($this->prefix . 'last_updated', now()->toIso8601String());
            
            // Публикуем событие для WebSocket
            $this->publishQueueUpdate($queueData);
            
            Log::info('Данные очереди успешно закэшированы в Redis');
        } catch (\Throwable $e) {
            Log::error('Ошибка кэширования данных очереди: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
    
    /**
     * Получает закэшированные данные очереди
     */
    public function getCachedQueueData(): ?array
    {
        try {
            $data = Redis::get($this->prefix . 'data');
            
            if (!$data) {
                return null;
            }
            
            return json_decode($data, true);
        } catch (\Throwable $e) {
            Log::error('Ошибка получения данных очереди из кэша: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }
    
    /**
     * Публикует обновление очереди для WebSocket
     */
    public function publishQueueUpdate(array $queueData): void
    {
        try {
            // Создаем сообщение для отправки
            $message = json_encode([
                'type' => 'queue_updated',
                'data' => $queueData,
                'timestamp' => now()->toIso8601String()
            ]);
            
            // Добавляем в буфер сообщений для WebSocket
            Redis::connection()->rpush('queue_updates_buffer', $message);
            
            // Также публикуем в канал (для обратной совместимости)
            Redis::publish('queue-updates', $message);
            
            // Ограничиваем размер буфера до 100 сообщений
            $bufferSize = Redis::connection()->llen('queue_updates_buffer');
            if ($bufferSize > 100) {
                Redis::connection()->ltrim('queue_updates_buffer', -100, -1);
            }
            
            Log::info('Событие обновления очереди опубликовано в Redis');
        } catch (\Throwable $e) {
            Log::error('Ошибка публикации события очереди: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
    
    /**
     * Инкрементирует счетчик клиентов
     */
    public function incrementClientCounter(): int
    {
        return Redis::incr($this->prefix . 'client_counter');
    }
    
    /**
     * Добавляет статистику обслуживания
     */
    public function addServiceStats(int $clientId, float $serviceTime): void
    {
        $key = $this->prefix . 'stats:service_times';
        
        // Добавляем время обслуживания в отсортированный набор
        Redis::zadd($key, $serviceTime, $clientId . ':' . now()->toIso8601String());
        
        // Обновляем средние показатели
        $this->updateAverageServiceTime($serviceTime);
        
        // Храним только последние 1000 записей
        Redis::zremrangebyrank($key, 0, -1001);
    }
    
    /**
     * Обновляет среднее время обслуживания
     */
    private function updateAverageServiceTime(float $newTime): void
    {
        $key = $this->prefix . 'stats:avg_service_time';
        $countKey = $this->prefix . 'stats:service_count';
        
        $currentAvg = (float)Redis::get($key) ?: 0;
        $count = Redis::incr($countKey);
        
        // Вычисляем новое среднее значение
        $newAvg = (($currentAvg * ($count - 1)) + $newTime) / $count;
        
        Redis::set($key, $newAvg);
    }
    
    /**
     * Получает статистику очереди
     */
    public function getQueueStats(): array
    {
        return [
            'avg_service_time' => (float)Redis::get($this->prefix . 'stats:avg_service_time') ?: 0,
            'total_clients_served' => (int)Redis::get($this->prefix . 'stats:service_count') ?: 0,
            'current_queue_length' => count($this->getCachedQueueData() ?: []),
            'last_updated' => Redis::get($this->prefix . 'last_updated') ?: now()->toIso8601String()
        ];
    }
    
    /**
     * Очищает все данные очереди из Redis
     */
    public function flushQueueData(): void
    {
        $keys = Redis::keys($this->prefix . '*');
        
        if (!empty($keys)) {
            Redis::del($keys);
            Log::info('Все данные очереди удалены из Redis');
        }
    }
}
