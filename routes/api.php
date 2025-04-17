<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Аутентификация
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Публичные маршруты
Route::prefix('queues')->group(function () {
    Route::get('/', [QueueController::class, 'index']);
    Route::get('/{queueId}', [QueueController::class, 'show']);
    Route::post('/{queueId}/join', [QueueController::class, 'joinQueue']);
});

// Временный маршрут для дебага
Route::get('/debug-user', function (Request $request) {
    if ($request->user()) {
        return response()->json([
            'success' => true,
            'user' => $request->user()->load('role'),
            'token_valid' => true
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Пользователь не аутентифицирован или токен недействителен',
        'token_valid' => false
    ]);
});

// Защищенные маршруты
Route::middleware('auth:sanctum')->group(function () {
    // Общие маршруты для всех аутентифицированных пользователей
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role');
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Маршруты для администраторов
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        // Клиенты
        Route::apiResource('clients', ClientController::class);
        Route::get('clients/{client}/positions', [ClientController::class, 'getClientPositions']);
        Route::get('clients/{client}/history', [ClientController::class, 'getClientHistory']);
        
        // Очереди
        Route::get('queues', [QueueController::class, 'index']); // Добавлен GET-маршрут для списка очередей
        Route::post('queues', [QueueController::class, 'store']);
        Route::put('queues/{queueId}', [QueueController::class, 'update']);
        Route::delete('queues/{queueId}', [QueueController::class, 'destroy']);
        Route::get('queues/{queueId}/stats', [QueueController::class, 'getStats']);
        
        // Операторы
        Route::apiResource('operators', OperatorController::class);
        Route::get('operators/{operatorId}/stats', [OperatorController::class, 'getStats']);
        
        // Статистика и метрики
        Route::prefix('stats')->group(function () {
            Route::get('redis', [StatsController::class, 'getRedisMetrics']);
            Route::get('queues', [QueueController::class, 'getAllStats']);
            Route::get('operators', [OperatorController::class, 'getAllStats']);
        });
        
        // Роли и пользователи
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('users', UserController::class);
    });
    
    // Маршруты для операторов
    Route::middleware(['role:operator'])->prefix('operator')->group(function () {
        // Профиль оператора
        Route::get('/profile', [OperatorController::class, 'getProfile']);
        Route::put('/profile', [OperatorController::class, 'updateProfile']);
        Route::put('/status', [OperatorController::class, 'updateStatus']);
        
        // Управление очередями
        Route::get('/queues', [OperatorController::class, 'getAssignedQueues']);
        Route::post('/queues/{queueId}/assign', [OperatorController::class, 'assignSelfToQueue']);
        
        // Статистика и история
        Route::get('/stats', [OperatorController::class, 'getOwnStats']);
        Route::get('/history', [OperatorController::class, 'getServiceHistory']);
        
        // Работа с клиентами
        Route::get('/clients/next', [OperatorController::class, 'getNextClient']);
        Route::post('/clients/{clientId}/serve', [OperatorController::class, 'startServingClient']);
        Route::post('/clients/{clientId}/complete', [OperatorController::class, 'completeServingClient']);
        Route::post('/clients/{clientId}/skip', [OperatorController::class, 'skipClient']);
    });
    
    // Маршруты для клиентов
    Route::middleware(['role:client'])->prefix('client')->group(function () {
        // Профиль клиента
        Route::get('/profile', [ClientController::class, 'getProfile']);
        Route::put('/profile', [ClientController::class, 'updateProfile']);
        
        // Очереди и позиции клиента
        Route::get('/positions', [ClientController::class, 'getOwnPositions']);
        Route::get('/queues/{queueId}/position', [ClientController::class, 'getPositionInQueue']);
        
        // История обслуживания
        Route::get('/history', [ClientController::class, 'getOwnHistory']);
        
        // Действия с очередями
        Route::post('/queues/{queueId}/join', [QueueController::class, 'joinQueueAuthenticated']);
        Route::post('/queues/{queueId}/leave', [QueueController::class, 'leaveQueue']);
    });
});
