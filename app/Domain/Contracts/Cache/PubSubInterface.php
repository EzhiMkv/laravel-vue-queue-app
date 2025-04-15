<?php

namespace App\Domain\Contracts\Cache;

/**
 * Интерфейс для операций публикации/подписки в кэше.
 */
interface PubSubInterface
{
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
}
