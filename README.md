# Laravel 10 + Vue 3
## Электронная очередь
### Установка

```bash
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
cd frontend
npm install
npm run dev
```

Если порт не занят, приложение запустится по адресу http://localhost:5173/

Логин: admin

Пароль: admin

Запуск тестов

```
./vendor/bin/sail artisan test
```


![скрин](https://i.imgur.com/PaHafQD.png "скрин")


