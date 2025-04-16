<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\QueueManager;
use Junges\Kafka\Queue\KafkaQueue;
use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Services\CacheService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Регистрируем сервис кэширования
        $this->app->singleton(CacheServiceInterface::class, CacheService::class);
        
        // Создаем алиас для интерфейса на случай, если где-то используется неправильный путь
        $this->app->singleton('App\Services\CacheServiceInterface', function ($app) {
            return $app->make(CacheServiceInterface::class);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerKafkaQueueDriver();
    }

    /**
     * Регистрируем драйвер Kafka для очередей
     */
    private function registerKafkaQueueDriver()
    {
        $this->app->afterResolving('queue', function (QueueManager $manager) {
            $manager->addConnector('kafka', function () {
                return new \Junges\Kafka\Queue\Connectors\KafkaConnector();
            });
        });
    }
}
