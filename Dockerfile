FROM php:8.2-fpm

# Устанавливаем зависимости и расширения PHP в одном слое
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        wget \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libpng-dev \
        libzip-dev \
        libjpeg-dev \
        libfreetype6-dev \
        librdkafka-dev \
        supervisor && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        gd && \
    pecl install xdebug rdkafka && \
    docker-php-ext-enable xdebug rdkafka pgsql

# Устанавливаем Composer из официального образа
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Создаем пользователя sail
RUN useradd sail

# Создаем директорию для логов supervisor
RUN mkdir -p /var/log/supervisor

WORKDIR /app

# Сначала копируем только файлы composer
COPY composer.json composer.lock ./

# Устанавливаем зависимости без скриптов и с кэшированием
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress

# Теперь копируем все остальные файлы
COPY . .

# Завершаем установку composer с оптимизацией
RUN composer dump-autoload --optimize --classmap-authoritative

# Оптимизируем настройки PHP для продакшена
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.memory_consumption=128" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.interned_strings_buffer=8" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.max_accelerated_files=4000" >> "$PHP_INI_DIR/conf.d/opcache.ini"

# Делаем скрипт запуска исполняемым
RUN chmod +x /app/docker/entrypoint.sh

# Устанавливаем конфигурацию supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Запускаем supervisor при старте контейнера
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
