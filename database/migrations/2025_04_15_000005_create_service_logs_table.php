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
        Schema::create('service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id');
            $table->foreign('queue_id')->references('id')->on('queues')->onDelete('cascade');
            $table->foreignId('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreignId('operator_id');
            $table->foreign('operator_id')->references('id')->on('operators')->onDelete('cascade');
            $table->foreignId('position_id')->nullable();
            $table->foreign('position_id')->references('id')->on('queue_positions')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('service_duration')->nullable(); // в секундах
            $table->enum('status', ['completed', 'cancelled', 'redirected', 'in_progress'])
                ->default('in_progress');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_logs');
    }
};
