<?php

namespace App\WebSocket;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Illuminate\Support\Facades\Log;
use SplObjectStorage;

class QueueWebSocketHandler implements MessageComponentInterface
{
    protected SplObjectStorage $clients;
    
    public function __construct()
    {
        $this->clients = new SplObjectStorage();
    }
    
    /**
     * Обработка нового подключения
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        
        $clientId = $conn->resourceId;
        Log::info("Новое WebSocket-подключение: {$clientId}");
        
        // Отправляем приветственное сообщение
        $conn->send(json_encode([
            'type' => 'connection_established',
            'client_id' => $clientId,
            'message' => 'Подключение к очереди установлено успешно',
            'timestamp' => now()->toIso8601String()
        ]));
    }
    
    /**
     * Обработка входящего сообщения
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $clientId = $from->resourceId;
        Log::info("Получено сообщение от клиента {$clientId}: {$msg}");
        
        // Обработка сообщений от клиентов (если нужно)
        $data = json_decode($msg, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['type'])) {
            switch ($data['type']) {
                case 'ping':
                    $from->send(json_encode([
                        'type' => 'pong',
                        'timestamp' => now()->toIso8601String()
                    ]));
                    break;
                    
                case 'subscribe':
                    // Здесь можно реализовать подписку на конкретные события
                    $from->send(json_encode([
                        'type' => 'subscription_confirmed',
                        'channel' => $data['channel'] ?? 'all',
                        'timestamp' => now()->toIso8601String()
                    ]));
                    break;
                    
                default:
                    // Неизвестный тип сообщения
                    break;
            }
        }
    }
    
    /**
     * Обработка закрытия соединения
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        
        $clientId = $conn->resourceId;
        Log::info("WebSocket-соединение закрыто: {$clientId}");
    }
    
    /**
     * Обработка ошибок
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $clientId = $conn->resourceId;
        Log::error("Ошибка WebSocket для клиента {$clientId}: {$e->getMessage()}", [
            'exception' => $e
        ]);
        
        $conn->close();
    }
    
    /**
     * Отправляет сообщение всем подключенным клиентам
     */
    public function broadcastToAll(string $message): void
    {
        foreach ($this->clients as $client) {
            $client->send($message);
        }
        
        $clientCount = count($this->clients);
        Log::info("Сообщение отправлено {$clientCount} клиентам: {$message}");
    }
}
