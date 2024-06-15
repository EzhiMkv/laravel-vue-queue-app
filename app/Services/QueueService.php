<?php

namespace App\Services;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\QueuePosition;
use App\Repositories\ClientRepository;
use Illuminate\Support\Facades\DB;

class QueueService
{
    public function __construct(){
    }
    public function addClientToQueue(Client $client): void
    {
        $position = 1;
        $last_position = QueuePosition::orderBy('position', 'desc')->first();
        if($last_position){
            $position = $last_position->position + 1;
        }
        QueuePosition::create(['client_id'=>$client->id, 'position'=>$position]);
    }

    public function removeClientFromQueue(Client $client): void
    {
        $client_position = $client->position->position;
        DB::statement('UPDATE queue SET position = position - 1 WHERE position > :client_position', ['client_position'=>$client_position]);
    }

    public function proceed(){
        $first_client_position = QueuePosition::orderBy('position', 'asc')->first();
        if($first_client_position){
            $first_client_position->client->delete();
        }
        return $this->getFullQueue();
    }

    public function getNextClient(){
        $next_client_position = QueuePosition::orderBy('position', 'asc')->first();
        if($next_client_position){
            return $next_client_position->client;
        }
        return false;
    }

    public function getClientPosition($client_id){
        $client_position = QueuePosition::where('client_id', $client_id)->first();
        if($client_position){
            return ['position'=>$client_position->position];
        }
        return false;
    }

    public function getFullQueue(){
        return QueuePosition::with('client')->orderBy('position', 'asc')->get();
    }

}
