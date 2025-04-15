<?php

namespace App\Http\Controllers\Api;

use App\Domain\Contracts\Client\ClientServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    /**
     * @var ClientServiceInterface
     */
    protected $clientService;

    /**
     * Конструктор контроллера.
     *
     * @param ClientServiceInterface $clientService
     */
    public function __construct(ClientServiceInterface $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Получить список всех клиентов.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->all(['status', 'search', 'sort_field', 'sort_direction', 'per_page']);
            $clients = $this->clientService->getClients($filters);
            
            return response()->json([
                'success' => true,
                'data' => $clients
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении списка клиентов: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка клиентов'
            ], 500);
        }
    }

    /**
     * Получить информацию о конкретном клиенте.
     *
     * @param int $clientId ID клиента
     * @return JsonResponse
     */
    public function show(int $clientId): JsonResponse
    {
        try {
            $client = $this->clientService->getClient($clientId);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $client
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации о клиенте: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении информации о клиенте'
            ], 500);
        }
    }

    /**
     * Создать нового клиента.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'metadata' => 'nullable|array'
            ]);
            
            $client = $this->clientService->createClient($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $client
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ошибка при создании клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить данные клиента.
     *
     * @param Request $request
     * @param int $clientId ID клиента
     * @return JsonResponse
     */
    public function update(Request $request, int $clientId): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'status' => 'nullable|string|in:waiting,serving,served,cancelled,skipped',
                'metadata' => 'nullable|array'
            ]);
            
            $client = $this->clientService->updateClient($clientId, $request->all());
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $client
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить клиента.
     *
     * @param int $clientId ID клиента
     * @return JsonResponse
     */
    public function destroy(int $clientId): JsonResponse
    {
        try {
            $client = $this->clientService->getClient($clientId);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            $client->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Клиент успешно удален'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении клиента'
            ], 500);
        }
    }

    /**
     * Получить позиции клиента во всех очередях.
     *
     * @param int $clientId ID клиента
     * @return JsonResponse
     */
    public function getClientPositions(int $clientId): JsonResponse
    {
        try {
            $client = $this->clientService->getClient($clientId);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            $positions = $this->clientService->getClientPositions($client);
            
            return response()->json([
                'success' => true,
                'data' => $positions
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении позиций клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении позиций клиента'
            ], 500);
        }
    }

    /**
     * Получить историю обслуживания клиента.
     *
     * @param int $clientId ID клиента
     * @return JsonResponse
     */
    public function getClientHistory(int $clientId): JsonResponse
    {
        try {
            $client = $this->clientService->getClient($clientId);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            $history = $this->clientService->getClientHistory($client);
            
            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении истории клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении истории клиента'
            ], 500);
        }
    }
}
