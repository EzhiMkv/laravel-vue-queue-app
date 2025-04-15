<?php

namespace App\Console\Commands;

use App\Listeners\ProcessQueueEvent;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class KafkaConsumerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:consume {topic? : Топик для прослушивания} {--debug : Включить подробный вывод событий}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Запускает консьюмер Kafka для обработки сообщений';

    /**
     * @var ProcessQueueEvent
     */
    protected ProcessQueueEvent $processor;

    /**
     * Конструктор консьюмера.
     */
    public function __construct(ProcessQueueEvent $processor) 
    {
        parent::__construct();
        $this->processor = $processor;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $topic = $this->argument('topic') ?? config('kafka.topics.queue_events');
        $isDebug = $this->option('debug');
        
        $this->info("🚀 Запуск потребителя Kafka для топика: {$topic}");
        $this->info("🛑 Для остановки нажмите Ctrl+C");
        
        try {
            $consumer = Kafka::createConsumer()
                ->subscribe([$topic])
                ->withConsumerGroupId(config('kafka.consumer.group_id'))
                ->withAutoCommit()
                ->withHandler(function (KafkaConsumerMessage $message) use ($isDebug) {
                    $this->processMessage($message, $isDebug);
                })
                ->build();
                
            $consumer->consume();
            
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Ошибка: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
    
    /**
     * Обрабатывает сообщение из Kafka
     */
    private function processMessage(KafkaConsumerMessage $message, bool $debug = false): void
    {
        $body = $message->getBody();
        $eventType = $body['event_type'] ?? 'unknown';
        
        $this->info("📨 Получено сообщение: {$eventType}");
        
        if ($debug) {
            $this->table(['Свойство', 'Значение'], $this->formatMessageData($body));
        }
        
        // Передаем сообщение в основной обработчик
        try {
            $this->processor->handle($message);
            $this->info("✅ Сообщение обработано успешно");
        } catch (\Throwable $e) {
            $this->error("❌ Ошибка обработки: {$e->getMessage()}");
            if ($debug) {
                $this->error($e->getTraceAsString());
            }
        }
    }
    
    /**
     * Форматирует данные сообщения для вывода в консоль
     */
    private function formatMessageData(array $data): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            
            $result[] = [$key, $value];
        }
        
        return $result;
    }
} 