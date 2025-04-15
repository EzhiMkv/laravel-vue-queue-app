<?php

namespace App\Domain\Contracts;

interface CacheServiceInterface
{
    /**
     * Сохранить данные в кэш.
     *
     * @param string $key Ключ
     * @param mixed $value Значение
     * @param int|null $ttl Время жизни в секундах
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * Получить данные из кэша.
     *
     * @param string $key Ключ
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Проверить наличие ключа в кэше.
     *
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Удалить данные из кэша.
     *
     * @param string $key Ключ
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Очистить кэш.
     *
     * @param string|null $pattern Шаблон ключей
     * @return bool
     */
    public function clear(?string $pattern = null): bool;

    /**
     * Увеличить числовое значение.
     *
     * @param string $key Ключ
     * @param int $increment Значение инкремента
     * @return int
     */
    public function increment(string $key, int $increment = 1): int;

    /**
     * Уменьшить числовое значение.
     *
     * @param string $key Ключ
     * @param int $decrement Значение декремента
     * @return int
     */
    public function decrement(string $key, int $decrement = 1): int;

    /**
     * Добавить элемент в список.
     *
     * @param string $key Ключ
     * @param mixed $value Значение
     * @return int
     */
    public function listPush(string $key, $value): int;

    /**
     * Получить элементы списка.
     *
     * @param string $key Ключ
     * @param int $start Начальный индекс
     * @param int $end Конечный индекс
     * @return array
     */
    public function listRange(string $key, int $start = 0, int $end = -1): array;

    /**
     * Добавить элемент в множество.
     *
     * @param string $key Ключ
     * @param mixed $value Значение
     * @return int
     */
    public function setAdd(string $key, $value): int;

    /**
     * Получить элементы множества.
     *
     * @param string $key Ключ
     * @return array
     */
    public function setMembers(string $key): array;

    /**
     * Опубликовать сообщение в канал.
     *
     * @param string $channel Канал
     * @param mixed $message Сообщение
     * @return int
     */
    public function publish(string $channel, $message): int;

    /**
     * Подписаться на канал.
     *
     * @param string|array $channels Каналы
     * @param callable $callback Функция обратного вызова
     * @return void
     */
    public function subscribe($channels, callable $callback): void;

    /**
     * Получить метрики кэша.
     *
     * @return array
     */
    public function getMetrics(): array;
}
