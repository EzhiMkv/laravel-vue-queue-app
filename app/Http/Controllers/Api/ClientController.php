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
    
    /**
     * Получить профиль текущего клиента.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $client = Client::where('email', $user->email)->first();
            
            if (!$client) {
                // Если клиент не найден, создаем его
                $client = $this->clientService->createClient([
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => 'active',
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->only(['id', 'name', 'email', 'avatar', 'phone']),
                    'client' => $client,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении профиля клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении профиля клиента'
            ], 500);
        }
    }
    
    /**
     * Обновить профиль текущего клиента.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'avatar' => 'nullable|string',
            ]);
            
            $user = $request->user();
            $client = Client::where('email', $user->email)->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Обновляем данные пользователя
            if ($request->has('name')) {
                $user->name = $request->name;
                $client->name = $request->name;
            }
            
            if ($request->has('phone')) {
                $user->phone = $request->phone;
                $client->phone = $request->phone;
            }
            
            if ($request->has('avatar')) {
                $user->avatar = $request->avatar;
            }
            
            $user->save();
            $client->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->only(['id', 'name', 'email', 'avatar', 'phone']),
                    'client' => $client->fresh(),
                ],
                'message' => 'Профиль успешно обновлен'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при обновлении профиля клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении профиля клиента'
            ], 500);
        }
    }
    
    /**
     * Получить позиции текущего клиента во всех очередях.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOwnPositions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $client = Client::where('email', $user->email)->first();
            
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
     * Получить позицию текущего клиента в конкретной очереди.
     *
     * @param Request $request
     * @param string $queueId ID очереди
     * @return JsonResponse
     */
    public function getPositionInQueue(Request $request, string $queueId): JsonResponse
    {
        try {
            $user = $request->user();
            $client = Client::where('email', $user->email)->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            $queue = $this->queueService->getQueue($queueId);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $position = $this->queueService->getClientPositionInQueue($queue, $client);
            
            if ($position === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не находится в этой очереди'
                ], 404);
            }
            
            // Получаем примерное время ожидания
            $queueStats = $this->queueService->getQueueStats($queue);
            $estimatedWaitTime = $queueStats['estimated_wait_time'] ?? 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'position' => $position,
                    'queue' => $queue->only(['id', 'name', 'status']),
                    'estimated_wait_time' => $estimatedWaitTime,
                    'estimated_wait_time_formatted' => $queueStats['estimated_wait_time_formatted'] ?? '0 сек',
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении позиции клиента в очереди: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении позиции клиента в очереди'
            ], 500);
        }
    }
    
    /**
     * Получить историю обслуживания текущего клиента.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOwnHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $client = Client::where('email', $user->email)->first();
            
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
