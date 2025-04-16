<?php

namespace App\Providers;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Client\ClientHistoryInterface;
use App\Domain\Contracts\Client\ClientManagementInterface;
use App\Domain\Contracts\Client\ClientQueueInfoInterface;
use App\Domain\Contracts\Client\ClientServiceInterface;
use App\Domain\Contracts\Operator\ClientServingInterface;
use App\Domain\Contracts\Operator\OperatorAnalyticsInterface;
use App\Domain\Contracts\Operator\OperatorManagementInterface;
use App\Domain\Contracts\Operator\OperatorQueueAssignmentInterface;
use App\Domain\Contracts\Operator\OperatorServiceInterface;
use App\Domain\Contracts\Queue\QueueAnalyticsInterface;
use App\Domain\Contracts\Queue\QueueClientOperationsInterface;
use App\Domain\Contracts\Queue\QueueManagementInterface;
use App\Domain\Contracts\Queue\QueueServiceInterface;
use App\Domain\Services\Cache\RedisCacheService;
use App\Domain\Services\Client\ClientHistoryService;
use App\Domain\Services\Client\ClientManagementService;
use App\Domain\Services\Client\ClientQueueInfoService;
use App\Domain\Services\Client\ClientService;
use App\Domain\Services\Operator\ClientServingService;
use App\Domain\Services\Operator\OperatorAnalyticsService;
use App\Domain\Services\Operator\OperatorManagementService;
use App\Domain\Services\Operator\OperatorQueueAssignmentService;
use App\Domain\Services\Operator\OperatorService;
use App\Domain\Services\Queue\QueueAnalyticsService;
use App\Domain\Services\Queue\QueueClientOperationsService;
use App\Domain\Services\Queue\QueueManagementService;
use App\Domain\Services\Queue\QueueService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Регистрация кэш-сервисов перенесена в AppServiceProvider

        // Регистрация сервисов для очередей
        $this->app->bind(QueueManagementInterface::class, QueueManagementService::class);
        $this->app->bind(QueueClientOperationsInterface::class, QueueClientOperationsService::class);
        $this->app->bind(QueueAnalyticsInterface::class, QueueAnalyticsService::class);
        $this->app->bind(QueueServiceInterface::class, QueueService::class);

        // Регистрация сервисов для клиентов
        $this->app->bind(ClientManagementInterface::class, ClientManagementService::class);
        $this->app->bind(ClientQueueInfoInterface::class, ClientQueueInfoService::class);
        $this->app->bind(ClientHistoryInterface::class, ClientHistoryService::class);
        $this->app->bind(ClientServiceInterface::class, ClientService::class);

        // Регистрация сервисов для операторов
        $this->app->bind(OperatorManagementInterface::class, OperatorManagementService::class);
        $this->app->bind(OperatorQueueAssignmentInterface::class, OperatorQueueAssignmentService::class);
        $this->app->bind(ClientServingInterface::class, ClientServingService::class);
        $this->app->bind(OperatorAnalyticsInterface::class, OperatorAnalyticsService::class);
        $this->app->bind(OperatorServiceInterface::class, OperatorService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
