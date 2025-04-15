<?php

namespace App\Http\Controllers\Api;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StatsController extends Controller
{
    /**
     * @var CacheServiceInterface
     */
    protected $cacheService;
    
    /**
     * Конструктор контроллера.
     *
     * @param CacheServiceInterface $cacheService
     */
    public function __construct(CacheServiceInterface $cacheService)
    {
        $this->cacheService = $cacheService;
    }
    
    /**
     * Получает метрики производительности Redis
     * 
     * @return JsonResponse
     */
    public function getRedisMetrics(): JsonResponse
    {
        try {
            $metrics = $this->cacheService->getMetrics();
            
            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении метрик Redis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении метрик Redis'
            ], 500);
        }
    }
}
