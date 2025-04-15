<?php

namespace App\Domain\Services\Queue;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Queue\QueueAnalyticsInterface;
use App\Models\Queue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QueueAnalyticsService implements QueueAnalyticsInterface
{
    /**
     * @var CacheServiceInterface
     */
    private $cacheService;

    /**
     * Префикс для ключей кэша.
     */
    private const CACHE_PREFIX = 'queue:stats:';

    /**
     * Конструктор сервиса.
     *
     * @param CacheServiceInterface $cacheService
     */
    public function __construct(CacheServiceInterface $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueueStats(Queue $queue, string $period = 'day'): array
    {
        // Определяем временной интервал
        $now = Carbon::now();
        $startDate = match($period) {
            'day' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            default => $now->copy()->startOfDay(),
        };

        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . "{$queue->id}:{$period}";
        $cachedStats = $this->cacheService->get($cacheKey);

        if ($cachedStats) {
            return $cachedStats;
        }

        // Если нет в кэше, формируем и кэшируем
        $serviceLogs = DB::table('service_logs')
            ->where('queue_id', $queue->id)
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalClients = $serviceLogs->count();
        $completedServices = $serviceLogs->where('status', 'completed')->count();
        $cancelledServices = $serviceLogs->where('status', 'cancelled')->count();
        $redirectedServices = $serviceLogs->where('status', 'redirected')->count();
        
        $serviceDurations = $serviceLogs
            ->where('status', 'completed')
            ->where('service_duration', '>', 0)
            ->pluck('service_duration')
            ->toArray();
        
        $avgServiceTime = count($serviceDurations) > 0 
            ? array_sum($serviceDurations) / count($serviceDurations) 
            : 0;
        
        $stats = [
            'total_clients' => $totalClients,
            'completed_services' => $completedServices,
            'cancelled_services' => $cancelledServices,
            'redirected_services' => $redirectedServices,
            'avg_service_time' => round($avgServiceTime, 2),
            'avg_service_time_formatted' => $this->formatSeconds($avgServiceTime),
            'completion_rate' => $totalClients > 0 
                ? round(($completedServices / $totalClients) * 100, 2) 
                : 0,
            'period' => $period,
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $now->toIso8601String(),
        ];

        // Кэшируем на более длительное время
        $ttl = match($period) {
            'day' => 3600, // 1 час
            'week' => 3600 * 6, // 6 часов
            'month' => 3600 * 12, // 12 часов
            default => 3600,
        };
        
        $this->cacheService->set($cacheKey, $stats, $ttl);

        return $stats;
    }

    /**
     * Форматировать секунды в читаемый формат.
     *
     * @param int $seconds Секунды
     * @return string
     */
    private function formatSeconds(int $seconds): string
    {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}
