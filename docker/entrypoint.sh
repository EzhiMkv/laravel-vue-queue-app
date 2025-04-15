#!/bin/bash
set -e

# Запускаем миграции при необходимости
php artisan migrate --force

# Запускаем WebSocket-сервер в фоновом режиме
nohup php artisan websocket:serve > /dev/null 2>&1 &

# Запускаем основной процесс Laravel
php artisan serve --host=0.0.0.0

# Если основной процесс завершается, завершаем и скрипт
exit 0
