<?php

namespace App\Models;

use App\Observers\ClientObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ClientObserver::class])]
class Client extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'status',
        'metadata'
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Получить активную позицию клиента в очереди.
     */
    public function activePosition(): HasOne
    {
        return $this->hasOne(QueuePosition::class)
            ->whereIn('status', ['waiting', 'called'])
            ->latest();
    }

    /**
     * Получить все позиции клиента в очередях.
     */
    public function positions(): HasMany
    {
        return $this->hasMany(QueuePosition::class);
    }

    /**
     * Получить все логи обслуживания клиента.
     */
    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    /**
     * Проверить, находится ли клиент в очереди.
     */
    public function isInQueue(): bool
    {
        return $this->activePosition()->exists();
    }

    /**
     * Получить позицию клиента в конкретной очереди.
     */
    public function getPositionInQueue(Queue $queue): ?QueuePosition
    {
        return $this->positions()
            ->where('queue_id', $queue->id)
            ->whereIn('status', ['waiting', 'called'])
            ->first();
    }
}
