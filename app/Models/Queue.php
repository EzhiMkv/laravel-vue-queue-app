<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Queue extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Таблица, связанная с моделью.
     *
     * @var string
     */
    protected $table = 'queues';

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'max_clients',
        'estimated_service_time',
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_clients' => 'integer',
        'estimated_service_time' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Получить позиции в очереди.
     */
    public function positions(): HasMany
    {
        return $this->hasMany(QueuePosition::class);
    }

    /**
     * Получить операторов, обслуживающих эту очередь.
     */
    public function operators(): HasMany
    {
        return $this->hasMany(Operator::class, 'current_queue_id');
    }

    /**
     * Получить логи обслуживания для этой очереди.
     */
    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    /**
     * Получить активные позиции в очереди, отсортированные по номеру позиции.
     */
    public function activePositions()
    {
        return $this->positions()
            ->whereIn('status', ['waiting', 'called'])
            ->orderBy('position', 'asc');
    }

    /**
     * Получить следующего клиента в очереди.
     */
    public function getNextClient()
    {
        $position = $this->activePositions()->first();
        
        return $position ? $position->client : null;
    }

    /**
     * Получить количество клиентов в очереди.
     */
    public function getClientCount()
    {
        return $this->activePositions()->count();
    }

    /**
     * Получить расчетное время ожидания для новой позиции.
     */
    public function getEstimatedWaitTime()
    {
        $clientCount = $this->getClientCount();
        
        return $clientCount * $this->estimated_service_time;
    }
}
