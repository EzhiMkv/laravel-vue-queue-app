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
        Schema::create('operators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('status', ['available', 'busy', 'offline'])->default('offline');
            $table->uuid('current_queue_id')->nullable();
            $table->foreign('current_queue_id')->references('id')->on('queues')->nullOnDelete();
            $table->integer('max_clients_per_day')->default(0);
            $table->integer('clients_served_today')->default(0);
            $table->json('skills')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
