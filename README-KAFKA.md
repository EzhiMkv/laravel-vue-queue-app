# Работа с Kafka в приложении

Это руководство описывает процесс настройки и использования Kafka в данном приложении.

## Обзор

В приложении используется Kafka для обработки событий очереди. События, которые отправляются:

1. **client_added** - добавление клиента в очередь
2. **client_removed** - удаление клиента из очереди
3. **queue_proceeded** - продвижение очереди

## Конфигурация

Настройки Kafka находятся в файле `.env`:

```
KAFKA_BROKERS=kafka:9092
KAFKA_TOPIC_QUEUE_EVENTS=queue-events
KAFKA_CONSUMER_GROUP_ID=queue-app-group
```

## Запуск консьюмера

Чтобы запустить консьюмер Kafka для обработки сообщений:

```bash
# Стандартный режим
php artisan kafka:consume

# С указанием топика
php artisan kafka:consume queue-events

# С включенным детальным выводом
php artisan kafka:consume --debug
```

## Отправка тестовых сообщений

Для тестирования консьюмера можно отправить тестовые сообщения с помощью команды:

```bash
# Отправить одно сообщение типа client_added (по умолчанию)
php artisan kafka:test-producer

# Отправить сообщение заданного типа
php artisan kafka:test-producer client_removed
php artisan kafka:test-producer queue_proceeded

# Отправить несколько сообщений
php artisan kafka:test-producer --count=5
php artisan kafka:test-producer queue_proceeded --count=3
```

## Компоненты системы

### Продюсер

`KafkaProducerService` отправляет сообщения в топик Kafka. Используется в:

- `AddClientToQueue` - при добавлении клиента в очередь
- `RemoveClientFromQueue` - при удалении клиента из очереди
- `QueueService::proceed()` - при продвижении очереди

### Консьюмер

`KafkaConsumerCommand` запускает консьюмер и обрабатывает входящие сообщения.

### Обработчик событий

`ProcessQueueEvent` содержит логику обработки разных типов событий из Kafka.

## Тестирование

Для тестирования можно:

1. Запустить консьюмер в одном терминале:
   ```
   php artisan kafka:consume --debug
   ```

2. Отправить тестовые сообщения в другом терминале:
   ```
   php artisan kafka:test-producer --count=3
   php artisan kafka:test-producer client_removed
   php artisan kafka:test-producer queue_proceeded
   ```

3. Или выполнить действия с очередью:
   ```
   # Добавить нового клиента
   php artisan tinker
   // App\Models\Client::create(['name' => 'Тестовый клиент']);
   
   # Продвинуть очередь
   php artisan queue:proceed
   ```