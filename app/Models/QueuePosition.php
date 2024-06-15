<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueuePosition extends Model
{
    use HasFactory;

    protected $table = 'queue';

    protected $fillable = ['client_id', 'position'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
