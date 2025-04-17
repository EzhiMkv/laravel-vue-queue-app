<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
});

// Добавляем маршрут login для перенаправления при ошибке аутентификации
Route::get('/login', function () {
    return redirect('/');
})->name('login');
