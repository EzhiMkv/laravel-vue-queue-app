<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OperatorController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Пользователь
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Клиенты
    Route::apiResource('clients', ClientController::class);
    Route::get('clients/{client}/positions', [ClientController::class, 'getClientPositions']);
    Route::get('clients/{client}/history', [ClientController::class, 'getClientHistory']);
    
    // Очереди
    Route::get('queues', [QueueController::class, 'index']);
    Route::get('queues/{queueId}', [QueueController::class, 'show']);
    Route::get('queues/{queueId}/next', [QueueController::class, 'getNextClient']);
    Route::post('queues/{queueId}/call-next', [QueueController::class, 'callNext']);
    Route::post('queues/{queueId}/clients', [QueueController::class, 'addClientToQueue']);
    Route::delete('queues/{queueId}/clients/{clientId}', [QueueController::class, 'removeClientFromQueue']);
    Route::get('queues/{queueId}/stats', [QueueController::class, 'getStats']);
    
    // Операторы
    Route::apiResource('operators', OperatorController::class);
    Route::post('operators/{operatorId}/assign-queue/{queueId}', [OperatorController::class, 'assignToQueue']);
    Route::post('operators/{operatorId}/status', [OperatorController::class, 'changeStatus']);
    Route::post('operators/{operatorId}/serve/{clientId}', [OperatorController::class, 'startServingClient']);
    Route::post('operators/{operatorId}/finish-serving/{serviceLogId}', [OperatorController::class, 'finishServingClient']);
    Route::get('operators/{operatorId}/stats', [OperatorController::class, 'getStats']);
    
    // Статистика и метрики Redis
    Route::prefix('stats')->group(function () {
        Route::get('redis', [StatsController::class, 'getRedisMetrics']);
    });
});


