<?php

namespace App\Domain\Contracts\Cache;

/**
 * Интерфейс для операций с коллекциями в кэше.
 */
interface CollectionCacheInterface
{
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
}
