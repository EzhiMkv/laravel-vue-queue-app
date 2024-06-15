<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use App\Services\QueueService;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    protected QueueService $queueService;

    public function __construct(QueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json($this->queueService->getFullQueue());
    }

    public function proceed()
    {
        return response()->json($this->queueService->proceed());
    }

    public function getNextClient()
    {
        return response()->json($this->queueService->getNextClient());
    }
}
