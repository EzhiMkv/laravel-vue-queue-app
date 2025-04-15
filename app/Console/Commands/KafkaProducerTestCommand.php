<?php

namespace App\Console\Commands;

use App\Services\KafkaProducerService;
use Illuminate\Console\Command;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class KafkaProducerTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:test-producer 
                            {event_type=client_added : Тип события (client_added, client_removed, queue_proceeded)}
                            {--count=1 : Количество сообщений для отправки}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отправляет тестовые сообщения в Kafka для проверки консьюмера';

    /**
     * @var KafkaProducerService
     */
    protected KafkaProducerService $kafkaProducer;

    /**
     * Constructor.
     */
    public function __construct(KafkaProducerService $kafkaProducer)
    {
        parent::__construct();
        $this->kafkaProducer = $kafkaProducer;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $eventType = $this->argument('event_type');
        $count = (int) $this->option('count');
        
        $this->info("🚀 Отправка {$count} тестовых сообщений типа '{$eventType}' в Kafka");
        
        $faker = Faker::create('ru_RU');
        
        try {
            for ($i = 1; $i <= $count; $i++) {
                $this->sendTestMessage($eventType, $faker, $i);
                $this->info("✅ Сообщение {$i} отправлено");
                
                if ($count > 1 && $i < $count) {
                    // Небольшая задержка между сообщениями
                    usleep(200000); // 0.2 секунды
                }
            }
            
            $this->info("✅ Все сообщения успешно отправлены");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Ошибка: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
    
    /**
     * Отправляет тестовое сообщение выбранного типа
     */
    private function sendTestMessage(string $eventType, \Faker\Generator $faker, int $index): void
    {
        switch ($eventType) {
            case 'client_added':
                $clientId = random_int(1000, 9999);
                $position = $index;
                
                $this->sendMessage(
                    'queue_events',
                    [
                        'event_type' => 'client_added',
                        'timestamp' => now()->toIso8601String(),
                        'client' => [
                            'id' => $clientId,
                            'name' => $faker->name,
                        ],
                        'position' => $position,
                    ]
                );
                break;
                
            case 'client_removed':
                $clientId = random_int(1000, 9999);
                $position = $index;
                
                $this->sendMessage(
                    'queue_events',
                    [
                        'event_type' => 'client_removed',
                        'timestamp' => now()->toIso8601String(),
                        'client' => [
                            'id' => $clientId,
                            'name' => $faker->name,
                        ],
                        'position' => $position,
                    ]
                );
                break;
                
            case 'queue_proceeded':
                $useClient = (bool) random_int(0, 1);
                $clientData = null;
                
                if ($useClient) {
                    $clientId = random_int(1000, 9999);
                    $clientData = [
                        'id' => $clientId,
                        'name' => $faker->name,
                    ];
                }
                
                $this->sendMessage(
                    'queue_events',
                    [
                        'event_type' => 'queue_proceeded',
                        'timestamp' => now()->toIso8601String(),
                        'client' => $clientData,
                        'new_queue_length' => random_int(0, 10),
                    ]
                );
                break;
                
            default:
                throw new \InvalidArgumentException("Неизвестный тип события: {$eventType}");
        }
    }
    
    /**
     * Отправляет сообщение в Kafka используя сервис
     */
    private function sendMessage(string $topic, array $data): void
    {
        $topicName = config("kafka.topics.{$topic}");
        
        $this->line("📤 Отправка в топик: {$topicName}");
        $this->table(['Свойство', 'Значение'], $this->formatMessageData($data));
        
        $this->kafkaProducer->sendMessage($topicName, $data);
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