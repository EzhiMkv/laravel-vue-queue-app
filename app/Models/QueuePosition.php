<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QueuePosition extends Model
{
    use HasFactory, HasUuids;

    /**
     * Таблица, связанная с моделью.
     *
     * @var string
     */
    protected $table = 'queue_positions';

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'queue_id',
        'client_id',
        'position',
        'priority',
        'estimated_wait_time',
        'status',
        'called_at',
        'serving_at',
        'served_at'
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'estimated_wait_time' => 'integer',
        'called_at' => 'datetime',
        'serving_at' => 'datetime',
        'served_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Получить клиента, связанного с этой позицией.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Получить очередь, связанную с этой позицией.
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    /**
     * Получить лог обслуживания для этой позиции.
     */
    public function serviceLog(): HasOne
    {
        return $this->hasOne(ServiceLog::class, 'position_id');
    }

    /**
     * Проверить, активна ли позиция.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['waiting', 'called']);
    }

    /**
     * Получить время ожидания в форматированном виде.
     */
    public function getFormattedWaitTime(): string
    {
        if (!$this->estimated_wait_time) {
            return '~';
        }
        
        $minutes = floor($this->estimated_wait_time / 60);
        $seconds = $this->estimated_wait_time % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
