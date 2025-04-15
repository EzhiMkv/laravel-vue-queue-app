<?php

namespace App\Domain\Contracts\Cache;

/**
 * Интерфейс для операций со счетчиками в кэше.
 */
interface CounterCacheInterface
{
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
}
