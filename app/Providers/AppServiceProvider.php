<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\QueueManager;
use Junges\Kafka\Queue\KafkaQueue;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
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
