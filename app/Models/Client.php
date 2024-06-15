<?php

namespace App\Models;

use App\Observers\ClientObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy([ClientObserver::class])]
class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function position(): HasOne
    {
        return $this->hasOne(QueuePosition::class);
    }
}
