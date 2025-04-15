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
use Illuminate\Support\Facades\Redis;

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
        
        // Добавляем периодическую проверку Redis на наличие новых сообщений
        $server->loop->addPeriodicTimer(1, function () use ($webSocketHandler) {
            try {
                // Проверяем, есть ли новые сообщения в Redis
                $message = Redis::connection()->lpop('queue_updates_buffer');
                
                if ($message) {
                    $this->info("📨 Получено сообщение из Redis");
                    $data = json_decode($message, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $webSocketHandler->broadcastToAll(json_encode($data));
                        $this->info("📢 Сообщение отправлено всем клиентам WebSocket");
                    } else {
                        $this->error("❌ Ошибка декодирования JSON: " . json_last_error_msg());
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Ошибка при получении сообщений из Redis: " . $e->getMessage());
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
