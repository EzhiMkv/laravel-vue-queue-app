<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::apiResource('clients', \App\Http\Controllers\Api\ClientController::class);
    Route::get('clients/{client}/position', [\App\Http\Controllers\Api\ClientController::class, 'getClientQueuePosition']);
    Route::get('queue', [\App\Http\Controllers\Api\QueueController::class, 'index']);
    Route::get('queue/proceed', [\App\Http\Controllers\Api\QueueController::class, 'proceed']);
    Route::get('queue/next', [\App\Http\Controllers\Api\QueueController::class, 'getNextClient']);
});


