<?php

namespace App\Services;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Repositories\ClientRepository;

class ClientService
{
    protected ClientRepository $clientRepository;

    public function __construct(ClientRepository $clientRepository){
        $this->clientRepository = $clientRepository;
    }
    public function create(ClientRequest $request)
    {
        return Client::create($request->validated());
    }

}
