<?php

namespace App\Http\Controllers\Api;

use App\Domain\Contracts\Queue\QueueServiceInterface;
use App\Domain\Contracts\Client\ClientServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\Queue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class QueueController extends Controller
{
    /**
     * @var QueueServiceInterface
     */
    protected $queueService;

    /**
     * @var ClientServiceInterface
     */
    protected $clientService;

    /**
     * Конструктор контроллера.
     *
     * @param QueueServiceInterface $queueService
     * @param ClientServiceInterface $clientService
     */
    public function __construct(
        QueueServiceInterface $queueService,
        ClientServiceInterface $clientService
    ) {
        $this->queueService = $queueService;
        $this->clientService = $clientService;
    }

    /**
     * Получить список всех активных очередей.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $queues = $this->queueService->getActiveQueues();
            return response()->json([
                'success' => true,
                'data' => $queues
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении списка очередей: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка очередей'
            ], 500);
        }
    }

    /**
     * Получить информацию о конкретной очереди.
     *
     * @param string $queueId ID очереди
     * @return JsonResponse
     */
    public function show(string $queueId): JsonResponse
    {
        try {
            $queue = $this->queueService->getQueue($queueId);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $state = $this->queueService->getQueueState($queue);
            
            return response()->json([
                'success' => true,
                'data' => $state
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации о очереди: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении информации о очереди'
            ], 500);
        }
    }

    /**
     * Вызвать следующего клиента в очереди.
     *
     * @param string $queueId ID очереди
     * @return JsonResponse
     */
    public function callNext(string $queueId): JsonResponse
    {
        try {
            $queue = $this->queueService->getQueue($queueId);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $position = $this->queueService->callNextClient($queue);
            
            if (!$position) {
                return response()->json([
                    'success' => false,
                    'message' => 'В очереди нет клиентов'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'position' => $position,
                    'client' => $position->client
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при вызове следующего клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при вызове следующего клиента'
            ], 500);
        }
    }

    /**
     * Получить следующего клиента в очереди без вызова.
     *
     * @param string $queueId ID очереди
     * @return JsonResponse
     */
    public function getNextClient(string $queueId): JsonResponse
    {
        try {
            $queue = $this->queueService->getQueue($queueId);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $client = $this->queueService->getNextClient($queue);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'В очереди нет клиентов'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $client
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении следующего клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении следующего клиента'
            ], 500);
        }
    }

    /**
     * Добавить клиента в очередь.
     *
     * @param Request $request
     * @param string $queueId ID очереди
     * @return JsonResponse
     */
    public function addClientToQueue(Request $request, string $queueId): JsonResponse
    {
        try {
            $request->validate([
                'client_id' => 'required|exists:clients,id',
                'priority' => 'nullable|in:low,normal,high,vip'
            ]);
            
            $queue = $this->queueService->getQueue($queueId);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $client = $this->clientService->getClient($request->client_id);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            $priority = $request->priority ?? 'normal';
            
            $position = $this->queueService->addClientToQueue($queue, $client, $priority);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'position' => $position,
                    'client' => $client
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ошибка при добавлении клиента в очередь: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить клиента из очереди.
     *
     * @param string $queueId ID очереди
     * @param int $clientId ID клиента
     * @return JsonResponse
     */
    public function removeClientFromQueue(string $queueId, int $clientId): JsonResponse
    {
        try {
            $queue = $this->queueService->getQueue($queueId);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $client = $this->clientService->getClient($clientId);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            $result = $this->queueService->removeClientFromQueue($queue, $client);
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден в очереди'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Клиент успешно удален из очереди'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении клиента из очереди: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении клиента из очереди'
            ], 500);
        }
    }

    /**
     * Получить статистику очереди.
     *
     * @param string $queueId ID очереди
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(string $queueId, Request $request): JsonResponse
    {
        try {
            $period = $request->period ?? 'day';
            
            $queue = $this->queueService->getQueue($queueId);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $stats = $this->queueService->getQueueStats($queue, $period);
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении статистики очереди: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики очереди'
            ], 500);
        }
    }
}
