<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceLog extends Model
{
    use HasFactory;

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'queue_id',
        'client_id',
        'operator_id',
        'position_id',
        'started_at',
        'ended_at',
        'service_duration',
        'status',
        'notes',
        'metadata'
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'service_duration' => 'integer',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Получить клиента, связанного с этим логом.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Получить очередь, связанную с этим логом.
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    /**
     * Получить оператора, связанного с этим логом.
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * Получить позицию, связанную с этим логом.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(QueuePosition::class, 'position_id');
    }

    /**
     * Получить форматированную длительность обслуживания.
     */
    public function getFormattedDuration(): string
    {
        if (!$this->service_duration) {
            return 'В процессе';
        }
        
        $minutes = floor($this->service_duration / 60);
        $seconds = $this->service_duration % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
