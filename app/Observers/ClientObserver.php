<?php

namespace App\Observers;

use App\Events\ClientCreated;
use App\Events\ClientDestroyed;
use App\Models\Client;
use Illuminate\Support\Facades\Log;

class ClientObserver
{
    /**
     * Handle the Client "created" event.
     */
    public function created(Client $client): void
    {
        ClientCreated::dispatch($client);
    }

    /**
     * Handle the Client "deleting" event.
     */
    public function deleting(Client $client): void
    {
        ClientDestroyed::dispatch($client);
    }

}
