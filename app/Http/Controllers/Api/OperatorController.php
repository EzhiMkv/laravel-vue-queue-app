<?php

namespace App\Http\Controllers\Api;

use App\Domain\Contracts\Operator\OperatorServiceInterface;
use App\Domain\Contracts\Queue\QueueServiceInterface;
use App\Domain\Contracts\Client\ClientServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\ServiceLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OperatorController extends Controller
{
    /**
     * @var OperatorServiceInterface
     */
    protected $operatorService;

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
     * @param OperatorServiceInterface $operatorService
     * @param QueueServiceInterface $queueService
     * @param ClientServiceInterface $clientService
     */
    public function __construct(
        OperatorServiceInterface $operatorService,
        QueueServiceInterface $queueService,
        ClientServiceInterface $clientService
    ) {
        $this->operatorService = $operatorService;
        $this->queueService = $queueService;
        $this->clientService = $clientService;
    }

    /**
     * Получить список всех операторов.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->all(['status', 'queue_id', 'user_id', 'sort_field', 'sort_direction', 'per_page']);
            $operators = $this->operatorService->getOperators($filters);
            
            return response()->json([
                'success' => true,
                'data' => $operators
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении списка операторов: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка операторов'
            ], 500);
        }
    }

    /**
     * Получить информацию о конкретном операторе.
     *
     * @param string $operatorId ID оператора
     * @return JsonResponse
     */
    public function show(string $operatorId): JsonResponse
    {
        try {
            $operator = $this->operatorService->getOperator($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $operator->load(['user', 'currentQueue'])
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации об операторе: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении информации об операторе'
            ], 500);
        }
    }

    /**
     * Создать нового оператора.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'status' => 'nullable|in:available,busy,offline',
                'current_queue_id' => 'nullable|exists:queues,id',
                'max_clients_per_day' => 'nullable|integer|min:0',
                'skills' => 'nullable|array'
            ]);
            
            $operator = $this->operatorService->createOperator($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $operator
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ошибка при создании оператора: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить данные оператора.
     *
     * @param Request $request
     * @param string $operatorId ID оператора
     * @return JsonResponse
     */
    public function update(Request $request, string $operatorId): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'nullable|in:available,busy,offline',
                'current_queue_id' => 'nullable|exists:queues,id',
                'max_clients_per_day' => 'nullable|integer|min:0',
                'skills' => 'nullable|array'
            ]);
            
            $operator = $this->operatorService->updateOperator($operatorId, $request->all());
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $operator
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении оператора: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить оператора.
     *
     * @param string $operatorId ID оператора
     * @return JsonResponse
     */
    public function destroy(string $operatorId): JsonResponse
    {
        try {
            $operator = $this->operatorService->getOperator($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            $operator->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Оператор успешно удален'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении оператора: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении оператора'
            ], 500);
        }
    }

    /**
     * Назначить оператора на очередь.
     *
     * @param string $operatorId ID оператора
     * @param string $queueId ID очереди
     * @return JsonResponse
     */
    public function assignToQueue(string $operatorId, string $queueId): JsonResponse
    {
        try {
            $operator = $this->operatorService->getOperator($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            $queue = $this->queueService->getQueue($queueId);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $result = $this->operatorService->assignOperatorToQueue($operator, $queue);
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось назначить оператора на очередь'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Оператор успешно назначен на очередь'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при назначении оператора на очередь: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при назначении оператора на очередь'
            ], 500);
        }
    }

    /**
     * Изменить статус оператора.
     *
     * @param Request $request
     * @param string $operatorId ID оператора
     * @return JsonResponse
     */
    public function changeStatus(Request $request, string $operatorId): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:available,busy,offline'
            ]);
            
            $operator = $this->operatorService->getOperator($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            $result = $this->operatorService->changeOperatorStatus($operator, $request->status);
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось изменить статус оператора'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Статус оператора успешно изменен'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при изменении статуса оператора: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при изменении статуса оператора'
            ], 500);
        }
    }

    /**
     * Начать обслуживание клиента.
     *
     * @param string $operatorId ID оператора
     * @param int $clientId ID клиента
     * @param Request $request
     * @return JsonResponse
     */
    public function startServingClient(string $operatorId, int $clientId, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id'
            ]);
            
            $operator = $this->operatorService->getOperator($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            $client = $this->clientService->getClient($clientId);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            $queue = $this->queueService->getQueue($request->queue_id);
            
            if (!$queue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Очередь не найдена'
                ], 404);
            }
            
            $serviceLog = $this->operatorService->startServingClient($operator, $client, $queue);
            
            return response()->json([
                'success' => true,
                'data' => $serviceLog
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ошибка при начале обслуживания клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Завершить обслуживание клиента.
     *
     * @param string $operatorId ID оператора
     * @param string $serviceLogId ID лога обслуживания
     * @param Request $request
     * @return JsonResponse
     */
    public function finishServingClient(string $operatorId, string $serviceLogId, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:completed,cancelled,redirected',
                'notes' => 'nullable|string',
                'metadata' => 'nullable|array'
            ]);
            
            $operator = $this->operatorService->getOperator($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            $serviceLog = ServiceLog::find($serviceLogId);
            
            if (!$serviceLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Лог обслуживания не найден'
                ], 404);
            }
            
            $result = $this->operatorService->finishServingClient(
                $operator,
                $serviceLog,
                $request->status,
                $request->only(['notes', 'metadata'])
            );
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось завершить обслуживание клиента'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Обслуживание клиента успешно завершено'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при завершении обслуживания клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику оператора.
     *
     * @param string $operatorId ID оператора
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(string $operatorId, Request $request): JsonResponse
    {
        try {
            $period = $request->period ?? 'day';
            
            $operator = $this->operatorService->getOperator($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            $stats = $this->operatorService->getOperatorStats($operator, $period);
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении статистики оператора: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики оператора'
            ], 500);
        }
    }
}
