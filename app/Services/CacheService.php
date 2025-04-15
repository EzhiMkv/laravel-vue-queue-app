<?php

namespace App\Services;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Cache\BasicCacheInterface;
use App\Domain\Contracts\Cache\CounterCacheInterface;
use App\Domain\Contracts\Cache\CollectionCacheInterface;
use App\Domain\Contracts\Cache\PubSubInterface;
use App\Domain\Contracts\Cache\CacheMetricsInterface;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CacheService implements 
    CacheServiceInterface, 
    BasicCacheInterface, 
    CounterCacheInterface, 
    CollectionCacheInterface, 
    PubSubInterface, 
    CacheMetricsInterface
{
    /**
     * Установить значение в кэш.
     *
     * @param string $key Ключ
     * @param mixed $value Значение
     * @param int|null $ttl Время жизни в секундах (null - бессрочно)
     * @return bool Успешность операции
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        try {
            if ($ttl === null) {
                return (bool) Redis::set($key, is_array($value) ? json_encode($value) : $value);
            } else {
                return (bool) Redis::setex($key, $ttl, is_array($value) ? json_encode($value) : $value);
            }
        } catch (\Exception $e) {
            Log::error('Ошибка при установке значения в кэш: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить значение из кэша.
     *
     * @param string $key Ключ
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение из кэша или значение по умолчанию
     */
    public function get(string $key, $default = null)
    {
        try {
            $value = Redis::get($key);
            
            if ($value === false || $value === null) {
                return $default;
            }
            
            // Пытаемся декодировать JSON, если это возможно
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            return $value;
        } catch (\Exception $e) {
            Log::error('Ошибка при получении значения из кэша: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Проверить наличие ключа в кэше.
     *
     * @param string $key Ключ
     * @return bool Существует ли ключ
     */
    public function has(string $key): bool
    {
        try {
            return (bool) Redis::exists($key);
        } catch (\Exception $e) {
            Log::error('Ошибка при проверке наличия ключа в кэше: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить ключ из кэша.
     *
     * @param string $key Ключ
     * @return bool Успешность операции
     */
    public function delete(string $key): bool
    {
        try {
            return (bool) Redis::del($key);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении ключа из кэша: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить все ключи из кэша по шаблону.
     *
     * @param string $pattern Шаблон ключа (например, "user:*")
     * @return int Количество удаленных ключей
     */
    public function deletePattern(string $pattern): int
    {
        try {
            $keys = Redis::keys($pattern);
            
            if (empty($keys)) {
                return 0;
            }
            
            return Redis::del($keys);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении ключей по шаблону: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Очистить весь кэш.
     *
     * @return bool Успешность операции
     */
    public function clear(): bool
    {
        try {
            return (bool) Redis::flushDB();
        } catch (\Exception $e) {
            Log::error('Ошибка при очистке кэша: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Увеличить значение счетчика.
     *
     * @param string $key Ключ счетчика
     * @param int $increment Значение инкремента
     * @return int|false Новое значение или false в случае ошибки
     */
    public function increment(string $key, int $increment = 1)
    {
        try {
            return Redis::incrby($key, $increment);
        } catch (\Exception $e) {
            Log::error('Ошибка при увеличении счетчика: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Уменьшить значение счетчика.
     *
     * @param string $key Ключ счетчика
     * @param int $decrement Значение декремента
     * @return int|false Новое значение или false в случае ошибки
     */
    public function decrement(string $key, int $decrement = 1)
    {
        try {
            return Redis::decrby($key, $decrement);
        } catch (\Exception $e) {
            Log::error('Ошибка при уменьшении счетчика: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Добавить элемент в список.
     *
     * @param string $key Ключ списка
     * @param mixed $value Значение
     * @param bool $prepend Добавить в начало списка
     * @return int|false Длина списка после операции или false в случае ошибки
     */
    public function listPush(string $key, $value, bool $prepend = false)
    {
        try {
            $value = is_array($value) ? json_encode($value) : $value;
            
            if ($prepend) {
                return Redis::lpush($key, $value);
            } else {
                return Redis::rpush($key, $value);
            }
        } catch (\Exception $e) {
            Log::error('Ошибка при добавлении элемента в список: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить элемент из списка.
     *
     * @param string $key Ключ списка
     * @param bool $pop Удалить элемент из списка
     * @param bool $fromStart Получить элемент с начала списка
     * @return mixed Значение элемента или null, если список пуст
     */
    public function listGet(string $key, bool $pop = false, bool $fromStart = true)
    {
        try {
            if ($pop) {
                $value = $fromStart ? Redis::lpop($key) : Redis::rpop($key);
            } else {
                $index = $fromStart ? 0 : -1;
                $value = Redis::lindex($key, $index);
            }
            
            if ($value === false || $value === null) {
                return null;
            }
            
            // Пытаемся декодировать JSON, если это возможно
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            return $value;
        } catch (\Exception $e) {
            Log::error('Ошибка при получении элемента из списка: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Получить все элементы списка.
     *
     * @param string $key Ключ списка
     * @return array Массив элементов списка
     */
    public function listGetAll(string $key): array
    {
        try {
            $values = Redis::lrange($key, 0, -1);
            
            if (!is_array($values)) {
                return [];
            }
            
            // Пытаемся декодировать JSON для каждого элемента
            return array_map(function ($value) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                return $value;
            }, $values);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении всех элементов списка: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить длину списка.
     *
     * @param string $key Ключ списка
     * @return int Длина списка
     */
    public function listLength(string $key): int
    {
        try {
            return Redis::llen($key);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении длины списка: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Добавить элемент в множество.
     *
     * @param string $key Ключ множества
     * @param mixed $value Значение
     * @return int|false Количество добавленных элементов или false в случае ошибки
     */
    public function setAdd(string $key, $value)
    {
        try {
            $value = is_array($value) ? json_encode($value) : $value;
            return Redis::sadd($key, $value);
        } catch (\Exception $e) {
            Log::error('Ошибка при добавлении элемента в множество: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Проверить наличие элемента в множестве.
     *
     * @param string $key Ключ множества
     * @param mixed $value Значение
     * @return bool Существует ли элемент в множестве
     */
    public function setHas(string $key, $value): bool
    {
        try {
            $value = is_array($value) ? json_encode($value) : $value;
            return (bool) Redis::sismember($key, $value);
        } catch (\Exception $e) {
            Log::error('Ошибка при проверке наличия элемента в множестве: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить элемент из множества.
     *
     * @param string $key Ключ множества
     * @param mixed $value Значение
     * @return int|false Количество удаленных элементов или false в случае ошибки
     */
    public function setRemove(string $key, $value)
    {
        try {
            $value = is_array($value) ? json_encode($value) : $value;
            return Redis::srem($key, $value);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении элемента из множества: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить все элементы множества.
     *
     * @param string $key Ключ множества
     * @return array Массив элементов множества
     */
    public function setGetAll(string $key): array
    {
        try {
            $values = Redis::smembers($key);
            
            if (!is_array($values)) {
                return [];
            }
            
            // Пытаемся декодировать JSON для каждого элемента
            return array_map(function ($value) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                return $value;
            }, $values);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении всех элементов множества: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить количество элементов в множестве.
     *
     * @param string $key Ключ множества
     * @return int Количество элементов
     */
    public function setSize(string $key): int
    {
        try {
            return Redis::scard($key);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении размера множества: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Опубликовать сообщение в канал.
     *
     * @param string $channel Канал
     * @param mixed $message Сообщение
     * @return int|false Количество клиентов, получивших сообщение, или false в случае ошибки
     */
    public function publish(string $channel, $message)
    {
        try {
            $message = is_array($message) ? json_encode($message) : $message;
            return Redis::publish($channel, $message);
        } catch (\Exception $e) {
            Log::error('Ошибка при публикации сообщения: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Подписаться на канал.
     *
     * @param string $channel Канал
     * @param callable $callback Функция обратного вызова
     * @return void
     */
    public function subscribe(string $channel, callable $callback): void
    {
        try {
            Redis::subscribe([$channel], function ($message) use ($callback) {
                // Пытаемся декодировать JSON, если это возможно
                $decoded = json_decode($message, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $callback($decoded);
                } else {
                    $callback($message);
                }
            });
        } catch (\Exception $e) {
            Log::error('Ошибка при подписке на канал: ' . $e->getMessage());
        }
    }

    /**
     * Получить метрики кэша.
     *
     * @return array Метрики кэша
     */
    public function getMetrics(): array
    {
        try {
            $info = Redis::info();
            
            $metrics = [
                'memory' => [
                    'used_memory_human' => $info['Memory']['used_memory_human'] ?? 'N/A',
                    'used_memory_peak_human' => $info['Memory']['used_memory_peak_human'] ?? 'N/A',
                    'used_memory_lua_human' => $info['Memory']['used_memory_lua_human'] ?? 'N/A',
                ],
                'stats' => [
                    'total_connections_received' => $info['Stats']['total_connections_received'] ?? 0,
                    'total_commands_processed' => $info['Stats']['total_commands_processed'] ?? 0,
                    'instantaneous_ops_per_sec' => $info['Stats']['instantaneous_ops_per_sec'] ?? 0,
                    'rejected_connections' => $info['Stats']['rejected_connections'] ?? 0,
                ],
                'clients' => [
                    'connected_clients' => $info['Clients']['connected_clients'] ?? 0,
                    'client_recent_max_input_buffer' => $info['Clients']['client_recent_max_input_buffer'] ?? 0,
                    'client_recent_max_output_buffer' => $info['Clients']['client_recent_max_output_buffer'] ?? 0,
                ],
                'keyspace' => $info['Keyspace'] ?? [],
                'cache_hit_rate' => $this->calculateHitRate(),
                'keys_count' => $this->getKeysCount(),
            ];
            
            return $metrics;
        } catch (\Exception $e) {
            Log::error('Ошибка при получении метрик кэша: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Рассчитать соотношение попаданий в кэш.
     *
     * @return float Соотношение попаданий в кэш (0-100%)
     */
    private function calculateHitRate(): float
    {
        try {
            $info = Redis::info();
            $hits = $info['Stats']['keyspace_hits'] ?? 0;
            $misses = $info['Stats']['keyspace_misses'] ?? 0;
            
            if ($hits + $misses === 0) {
                return 0;
            }
            
            return round(($hits / ($hits + $misses)) * 100, 2);
        } catch (\Exception $e) {
            Log::error('Ошибка при расчете соотношения попаданий в кэш: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Получить количество ключей в кэше.
     *
     * @return int Количество ключей
     */
    private function getKeysCount(): int
    {
        try {
            $info = Redis::info();
            $keyspace = $info['Keyspace'] ?? [];
            
            $count = 0;
            foreach ($keyspace as $db => $stats) {
                $count += $stats['keys'] ?? 0;
            }
            
            return $count;
        } catch (\Exception $e) {
            Log::error('Ошибка при получении количества ключей в кэше: ' . $e->getMessage());
            return 0;
        }
    }
}
