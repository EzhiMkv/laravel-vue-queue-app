<?php

namespace App\Providers;

// Существующие интерфейсы и сервисы
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

// Новые интерфейсы и сервисы для профилей
use App\Domain\Contracts\ProfileInterface;
use App\Domain\Contracts\ClientProfileInterface;
use App\Domain\Contracts\OperatorProfileInterface;
use App\Domain\Contracts\AdminProfileInterface;
use App\Domain\Contracts\Repositories\ProfileRepositoryInterface;
use App\Domain\Contracts\Repositories\ClientProfileRepositoryInterface;
use App\Domain\Contracts\Repositories\OperatorProfileRepositoryInterface;
use App\Domain\Contracts\Repositories\RoleRepositoryInterface;
use App\Domain\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\Contracts\Services\ProfileServiceInterface;
use App\Domain\Contracts\Services\ClientServiceInterface as ClientProfileServiceInterface;
use App\Domain\Contracts\Services\OperatorServiceInterface as OperatorProfileServiceInterface;
use App\Domain\Contracts\Services\UserFactoryServiceInterface;
use App\Domain\Contracts\Services\PasswordServiceInterface;
use App\Domain\Contracts\Services\TransactionServiceInterface;
use App\Domain\Services\ProfileService;
use App\Domain\Services\ClientProfileService;
use App\Domain\Services\OperatorProfileService;
use App\Domain\Services\UserFactoryService;
use App\Domain\Validators\UserDataValidator;
use App\Infrastructure\Repositories\ProfileRepository;
use App\Infrastructure\Repositories\ClientProfileRepository;
use App\Infrastructure\Repositories\OperatorProfileRepository;
use App\Infrastructure\Repositories\RoleRepository;
use App\Infrastructure\Repositories\UserRepository;
use App\Infrastructure\Services\LaravelPasswordService;
use App\Infrastructure\Services\LaravelTransactionService;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Биндим интерфейс к реализации для DI
        $this->app->bind(
            \App\Domain\Contracts\Cache\CacheServiceInterface::class,
            \App\Services\CacheService::class
        );

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
        
        // Регистрация репозиториев
        $this->app->bind(ProfileRepositoryInterface::class, ProfileRepository::class);
        $this->app->bind(ClientProfileRepositoryInterface::class, ClientProfileRepository::class);
        $this->app->bind(OperatorProfileRepositoryInterface::class, OperatorProfileRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        
        // Регистрация валидаторов
        $this->app->singleton(UserDataValidator::class, function () {
            return new UserDataValidator();
        });
        
        // Регистрация сервисов для профилей
        $this->app->bind(ProfileServiceInterface::class, ProfileService::class);
        $this->app->bind(ClientProfileServiceInterface::class, ClientProfileService::class);
        $this->app->bind(OperatorProfileServiceInterface::class, OperatorProfileService::class);
        
        // Регистрация инфраструктурных сервисов
        $this->app->bind(PasswordServiceInterface::class, LaravelPasswordService::class);
        $this->app->bind(TransactionServiceInterface::class, LaravelTransactionService::class);
        
        // Регистрация фабрики пользователей
        $this->app->bind(UserFactoryServiceInterface::class, function ($app) {
            return new UserFactoryService(
                $app->make(RoleRepositoryInterface::class),
                $app->make(UserRepositoryInterface::class),
                $app->make(ProfileRepositoryInterface::class),
                $app->make(UserDataValidator::class),
                $app->make(PasswordServiceInterface::class),
                $app->make(TransactionServiceInterface::class),
                $app->make(LoggerInterface::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
