<?php

return [
    /*
     | Брокеры Kafka
     */
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),

    /*
     | Автоматическое подтверждение сообщений
     */
    'auto_commit' => env('KAFKA_AUTO_COMMIT', true),

    /*
     | Конфигурация потребителя
     */
    'consumer' => [
        /*
         | Группа потребителей по умолчанию
         */
        'group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'queue-app-group'),

        /*
         | Смещение по умолчанию для сброса (earliest, latest)
         */
        'auto_offset_reset' => env('KAFKA_AUTO_OFFSET_RESET', 'latest'),
        
        /*
         | Хендлеры для обработки событий
         */
        'topics' => [
            'queue-events' => [
                // Здесь можно зарегистрировать потребители для обработки событий
            ],
        ],
    ],

    /*
     | Конфигурация продюсера
     */
    'producer' => [
        /*
         | Количество подтверждений от брокеров
         | 0 = Не ждать подтверждений
         | 1 = Дождаться подтверждения от лидера
         | -1 = Дождаться подтверждения от всех реплик
         */
        'acks' => env('KAFKA_REQUIRED_ACKS', '1'),
    ],

    /*
     | Топики по умолчанию для наших событий очереди
     */
    'topics' => [
        'queue_events' => env('KAFKA_TOPIC_QUEUE_EVENTS', 'queue-events'),
        'client_events' => env('KAFKA_TOPIC_CLIENT_EVENTS', 'client-events'),
    ],

    /*
     | Схема авторизации SASL
     | Поддерживается: plaintext, scram-sha-256, scram-sha-512
     */
    'sasl' => [
        'enabled' => env('KAFKA_SASL_ENABLED', false),
        'username' => env('KAFKA_SASL_USERNAME', null),
        'password' => env('KAFKA_SASL_PASSWORD', null),
        'mechanisms' => env('KAFKA_SASL_MECHANISMS', 'PLAIN'),
    ],
    
    /*
     | Конфигурация очереди
     */
    'queue' => [
        'enabled' => env('KAFKA_QUEUE_ENABLED', true),
        'queue_name' => env('KAFKA_QUEUE_NAME', 'default'),
        'dead_letter_queue' => env('KAFKA_QUEUE_DEAD_LETTER_QUEUE', 'dead_messages'),
        'flush_retry_count' => env('KAFKA_QUEUE_FLUSH_RETRY_COUNT', 10)
    ],
]; 