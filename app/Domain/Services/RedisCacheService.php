<?php

namespace App\Domain\Services;

use App\Domain\Contracts\CacheServiceInterface;
use Illuminate\Support\Facades\Redis;
use Predis\Client;

class RedisCacheService implements CacheServiceInterface
{
    /**
     * @var Client
     */
    private $redis;

    /**
     * Конструктор сервиса.
     */
    public function __construct()
    {
        $this->redis = Redis::connection()->client();
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        if ($ttl) {
            return (bool) $this->redis->setex($key, $ttl, $value);
        }

        return (bool) $this->redis->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        $value = $this->redis->get($key);

        if ($value === null) {
            return $default;
        }

        // Пытаемся декодировать JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return (bool) $this->redis->del($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(?string $pattern = null): bool
    {
        if ($pattern) {
            $keys = $this->redis->keys($pattern);
            if (count($keys) > 0) {
                return (bool) $this->redis->del($keys);
            }
            return true;
        }

        // Опасная операция, очищает всю БД
        return (bool) $this->redis->flushdb();
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $increment = 1): int
    {
        return $this->redis->incrby($key, $increment);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $decrement = 1): int
    {
        return $this->redis->decrby($key, $decrement);
    }

    /**
     * {@inheritdoc}
     */
    public function listPush(string $key, $value): int
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        return $this->redis->rpush($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function listRange(string $key, int $start = 0, int $end = -1): array
    {
        $values = $this->redis->lrange($key, $start, $end);
        
        // Пытаемся декодировать JSON для каждого элемента
        return array_map(function ($item) {
            $decoded = json_decode($item, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $item;
        }, $values);
    }

    /**
     * {@inheritdoc}
     */
    public function setAdd(string $key, $value): int
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        return $this->redis->sadd($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setMembers(string $key): array
    {
        $values = $this->redis->smembers($key);
        
        // Пытаемся декодировать JSON для каждого элемента
        return array_map(function ($item) {
            $decoded = json_decode($item, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $item;
        }, $values);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $channel, $message): int
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        return $this->redis->publish($channel, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe($channels, callable $callback): void
    {
        $this->redis->subscribe((array) $channels, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetrics(): array
    {
        $info = $this->redis->info();
        
        return [
            'used_memory' => $info['used_memory_human'] ?? 'N/A',
            'clients' => $info['connected_clients'] ?? 0,
            'uptime' => $info['uptime_in_seconds'] ?? 0,
            'commands_processed' => $info['total_commands_processed'] ?? 0,
            'keys' => $this->redis->dbsize(),
            'hit_rate' => $this->calculateHitRate($info),
        ];
    }

    /**
     * Рассчитать процент попаданий в кэш.
     *
     * @param array $info Информация о Redis
     * @return float
     */
    private function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        
        if ($hits + $misses == 0) {
            return 0;
        }
        
        return round(($hits / ($hits + $misses)) * 100, 2);
    }
}
