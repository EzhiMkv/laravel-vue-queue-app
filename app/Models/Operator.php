<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operator extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'current_queue_id',
        'max_clients_per_day',
        'clients_served_today',
        'skills'
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_clients_per_day' => 'integer',
        'clients_served_today' => 'integer',
        'skills' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Получить пользователя, связанного с оператором.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить текущую очередь оператора.
     */
    public function currentQueue(): BelongsTo
    {
        return $this->belongsTo(Queue::class, 'current_queue_id');
    }

    /**
     * Получить логи обслуживания оператора.
     */
    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    /**
     * Проверить, доступен ли оператор для обслуживания клиентов.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available' && 
               ($this->max_clients_per_day === 0 || $this->clients_served_today < $this->max_clients_per_day);
    }

    /**
     * Обслужить клиента.
     */
    public function serveClient(Client $client, Queue $queue): ServiceLog
    {
        // Создаем запись в логе обслуживания
        $position = $client->getPositionInQueue($queue);
        
        $serviceLog = new ServiceLog([
            'queue_id' => $queue->id,
            'client_id' => $client->id,
            'operator_id' => $this->id,
            'position_id' => $position ? $position->id : null,
            'started_at' => now(),
            'status' => 'in_progress'
        ]);
        
        $serviceLog->save();
        
        // Обновляем статус оператора
        $this->status = 'busy';
        $this->save();
        
        // Обновляем статус клиента
        $client->status = 'serving';
        $client->save();
        
        // Обновляем статус позиции, если она существует
        if ($position) {
            $position->status = 'serving';
            $position->serving_at = now();
            $position->save();
        }
        
        return $serviceLog;
    }

    /**
     * Завершить обслуживание клиента.
     */
    public function finishServing(ServiceLog $serviceLog, string $status = 'completed'): void
    {
        // Обновляем лог обслуживания
        $serviceLog->ended_at = now();
        $serviceLog->service_duration = $serviceLog->started_at->diffInSeconds(now());
        $serviceLog->status = $status;
        $serviceLog->save();
        
        // Обновляем статус оператора
        $this->status = 'available';
        $this->clients_served_today++;
        $this->save();
        
        // Обновляем статус клиента
        $client = $serviceLog->client;
        $client->status = 'served';
        $client->save();
        
        // Обновляем статус позиции, если она существует
        if ($serviceLog->position) {
            $serviceLog->position->status = 'served';
            $serviceLog->position->served_at = now();
            $serviceLog->position->save();
        }
    }
}
