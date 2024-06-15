<?php

namespace App\Repositories;

use App\Models\Client;

class ClientRepository
{
    public function create(array $data)
    {
        return Client::create($data);
    }

}
