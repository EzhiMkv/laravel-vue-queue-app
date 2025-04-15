<?php

namespace App\Domain\Contracts\Cache;

/**
 * Интерфейс для базовых операций с кэшем.
 */
interface BasicCacheInterface
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
}
