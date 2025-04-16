<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\SecureServer;
use React\Socket\Server;
use App\WebSocket\QueueWebSocketHandler;
use App\Domain\Contracts\Cache\CacheServiceInterface;

class WebSocketServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:serve {--port=6001} {--host=0.0.0.0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Запускает WebSocket-сервер для обновлений очереди в реальном времени';
    
    /**
     * @var CacheServiceInterface
     */
    protected $cacheService;
    
    /**
     * Конструктор команды.
     *
     * @param CacheServiceInterface $cacheService
     */
    public function __construct(CacheServiceInterface $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $port = $this->option('port');
        $host = $this->option('host');
        
        $this->info("🚀 Запуск WebSocket-сервера на {$host}:{$port}");
        $this->info("🛑 Для остановки нажмите Ctrl+C");
        
        $loop = Factory::create();
        
        // Создаем WebSocket-обработчик
        $webSocketHandler = new QueueWebSocketHandler();
        
        // Создаем HTTP-сервер с WebSocket-обработчиком
        $server = IoServer::factory(
            new HttpServer(
                new WsServer($webSocketHandler)
            ),
            $port,
            $host
        );
        
        // Добавляем периодическую проверку Redis на наличие новых сообщений через CacheService
        $server->loop->addPeriodicTimer(0.5, function () use ($webSocketHandler) {
            try {
                // Проверяем, есть ли новые сообщения в Redis
                $message = $this->cacheService->listGet('queue_updates_buffer', true, true);
                
                if ($message) {
                    $this->info("📨 Получено сообщение из кэша");
                    
                    // Сообщение уже декодировано в CacheService
                    $webSocketHandler->broadcastToAll(json_encode($message));
                    $this->info("📢 Сообщение отправлено всем клиентам WebSocket");
                }
            } catch (\Exception $e) {
                $this->error("❌ Ошибка при получении сообщений из кэша: " . $e->getMessage());
            }
        });
        
        // Настраиваем подписку на канал обновлений через PubSub
        $this->info("📡 Настройка подписки на канал обновлений 'queue_updates'");
        $server->loop->addPeriodicTimer(0.1, function () use ($webSocketHandler) {
            try {
                // Используем PubSub для получения сообщений в реальном времени
                $message = $this->cacheService->get('pubsub:queue_updates:last_message');
                
                if ($message && !empty($message)) {
                    $messageId = $message['id'] ?? '';
                    $lastProcessedId = $this->cacheService->get('pubsub:queue_updates:last_processed_id') ?? '';
                    
                    // Проверяем, не обрабатывали ли мы уже это сообщение
                    if ($messageId && $messageId !== $lastProcessedId) {
                        $this->info("📡 Получено сообщение из PubSub: {$messageId}");
                        $webSocketHandler->broadcastToAll(json_encode($message));
                        $this->info("📢 Сообщение отправлено всем клиентам WebSocket");
                        
                        // Сохраняем ID последнего обработанного сообщения
                        $this->cacheService->set('pubsub:queue_updates:last_processed_id', $messageId);
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Ошибка при обработке PubSub сообщений: " . $e->getMessage());
            }
        });
        
        // Добавляем периодический хартбит
        $server->loop->addPeriodicTimer(60, function () {
            $this->info("💓 WebSocket-сервер работает... " . date('Y-m-d H:i:s'));
        });
        
        $this->info("✅ WebSocket-сервер успешно запущен!");
        
        // Запускаем сервер
        $server->run();
        
        return self::SUCCESS;
    }
}
