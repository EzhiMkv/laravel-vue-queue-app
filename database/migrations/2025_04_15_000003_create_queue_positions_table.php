<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Сначала удаляем старую таблицу queue
        Schema::dropIfExists('queue');
        
        // Создаем новую таблицу queue_positions
        Schema::create('queue_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('queue_id');
            $table->foreign('queue_id')->references('id')->on('queues')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->integer('position');
            $table->enum('priority', ['low', 'normal', 'high', 'vip'])->default('normal');
            $table->integer('estimated_wait_time')->nullable(); // в секундах
            $table->enum('status', ['waiting', 'called', 'serving', 'served', 'skipped'])
                ->default('waiting');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('serving_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
            
            // Индексы для быстрого поиска
            $table->index(['queue_id', 'position']);
            $table->index(['client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_positions');
        
        // Восстанавливаем старую таблицу queue
        Schema::create('queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->integer('position');
            $table->timestamps();
        });
    }
};
