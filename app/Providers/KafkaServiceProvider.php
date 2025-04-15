<?php

namespace App\Providers;

use App\Listeners\ProcessQueueEvent;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Config\Sasl;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class KafkaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        // Настраиваем SASL, если требуется
        if (config('kafka.sasl.enabled')) {
            $sasl = new Sasl(
                config('kafka.sasl.username'),
                config('kafka.sasl.password'),
                config('kafka.sasl.mechanisms')
            );
            
            Kafka::setSasl($sasl);
        }
        
        // Регистрируем обработчики Kafka только при запуске в фоне
        // Основной обработчик теперь использует KafkaConsumerCommand
    }
} 