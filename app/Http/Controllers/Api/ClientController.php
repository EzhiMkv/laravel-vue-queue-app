<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use App\Services\QueueService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    protected ClientService $clientService;

    protected QueueService $queueService;

    public function __construct(ClientService $clientService, QueueService $queueService)
    {
        $this->clientService = $clientService;
        $this->queueService = $queueService;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClientRequest $request): \Illuminate\Http\JsonResponse
    {
        $client = $this->clientService->create($request);
        return response()->json($client, 201);
    }

    public function getClientQueuePosition(Request $request, Client $client)
    {
        return response()->json($this->queueService->getClientPosition($client->id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Client::destroy($id);
        return response()->json(null, 204);
    }
}
