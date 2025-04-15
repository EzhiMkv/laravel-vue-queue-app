# Laravel 10 + Vue 3
## Электронная очередь

### Системные требования

- Docker и Docker Compose
- PHP >= 8.1 с расширением rdkafka (устанавливается автоматически в Docker)

### Установка

```bash
# Запуск Docker-контейнеров (включая Kafka и Zookeeper)
docker-compose up -d

# Миграции и сидеры (выполняем внутри контейнера)
docker-compose exec backend php artisan migrate --seed

# Фронтенд запускается автоматически на http://localhost:5173
```

# Доступы

- Веб-интерфейс: http://localhost:5173/
- API бэкенд: http://localhost:8000/
- Kafka UI: http://localhost:8080/

Логин: admin
Пароль: admin

Запуск тестов

```bash
docker-compose exec backend php artisan test
```

## Интеграция с Kafka

Проект использует Apache Kafka для обработки событий очереди в реальном времени:

- Все события очереди (добавление/удаление клиентов, продвижение очереди) публикуются в Kafka
- События могут быть использованы для интеграции с другими системами или аналитики
- Kafka UI доступен по адресу http://localhost:8080 для мониторинга топиков и сообщений

### Запуск консьюмера Kafka

```bash
docker-compose exec backend php artisan kafka:consume
```

### Техническая информация

- Проект использует расширение PHP `rdkafka` через пакет `mateusjunges/laravel-kafka`
- Все конфигурации находятся в файле `config/kafka.php`
- Контейнеры настроены для автоматической установки всех необходимых зависимостей

### Архитектура с Kafka

- `KafkaProducerService` - отправляет события в топики Kafka
- `KafkaServiceProvider` - настраивает соединение с Kafka
- События интегрированы в жизненный цикл очереди через лиснеры

Это позволяет масштабировать систему и добавлять внешние интеграции без изменения основной бизнес-логики.

![скрин](https://i.imgur.com/PaHafQD.png "скрин")