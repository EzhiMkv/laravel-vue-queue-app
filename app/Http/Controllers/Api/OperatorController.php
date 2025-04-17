<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use App\Models\Role;
use App\Models\ServiceLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class OperatorController extends Controller
{
    /**
     * Получить список всех операторов.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Получаем ID роли оператора
            $operatorRole = Role::where('slug', 'operator')->first();
            
            if (!$operatorRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Роль оператора не найдена'
                ], 404);
            }
            
            // Получаем пользователей с ролью оператора
            $query = User::where('role_id', $operatorRole->id)
                ->with('profile', 'role');
            
            // Применяем фильтры
            if ($request->has('status')) {
                $query->whereHas('profile', function($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
            
            // Сортировка
            $sortField = $request->sort_field ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy($sortField, $sortDirection);
            
            // Пагинация
            if ($request->has('per_page')) {
                $operators = $query->paginate($request->per_page);
            } else {
                $operators = $query->get();
            }
            
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
     * @param int $operatorId ID оператора
     * @return JsonResponse
     */
    public function show(int $operatorId): JsonResponse
    {
        try {
            $operator = User::with('profile', 'role')
                ->whereHas('role', function($q) {
                    $q->where('slug', 'operator');
                })
                ->find($operatorId);
            
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
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:6',
                'status' => 'nullable|string|in:available,busy,offline',
                'metadata' => 'nullable|array'
            ]);
            
            // Получаем роль оператора
            $operatorRole = Role::where('slug', 'operator')->first();
            
            if (!$operatorRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Роль оператора не найдена'
                ], 404);
            }
            
            // Создаем пользователя
            $operator = new User();
            $operator->name = $request->name;
            $operator->email = $request->email;
            $operator->phone = $request->phone;
            $operator->password = Hash::make($request->password);
            $operator->role_id = $operatorRole->id;
            $operator->metadata = $request->metadata;
            $operator->save();
            
            // Создаем профиль
            $profile = new Profile();
            $profile->user_id = $operator->id;
            $profile->type = 'operator';
            $profile->status = $request->status ?? 'offline';
            $profile->attributes = [
                'current_queue_id' => null,
                'stats' => [
                    'clients_served_today' => 0,
                    'clients_served_week' => 0,
                    'clients_served_month' => 0,
                    'clients_served_total' => 0,
                    'average_service_time' => 0,
                ],
                'preferences' => [],
                'skills' => [],
                'schedule' => [],
            ];
            $profile->save();
            
            return response()->json([
                'success' => true,
                'data' => $operator->load('profile', 'role')
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ошибка при создании оператора: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании оператора: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить информацию об операторе.
     *
     * @param Request $request
     * @param int $operatorId ID оператора
     * @return JsonResponse
     */
    public function update(Request $request, int $operatorId): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $operatorId,
                'phone' => 'sometimes|nullable|string|max:20',
                'password' => 'sometimes|string|min:6',
                'status' => 'sometimes|string|in:available,busy,offline',
                'metadata' => 'sometimes|nullable|array'
            ]);
            
            $operator = User::with('profile')
                ->whereHas('role', function($q) {
                    $q->where('slug', 'operator');
                })
                ->find($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            // Обновляем данные пользователя
            if ($request->has('name')) $operator->name = $request->name;
            if ($request->has('email')) $operator->email = $request->email;
            if ($request->has('phone')) $operator->phone = $request->phone;
            if ($request->has('password')) $operator->password = Hash::make($request->password);
            if ($request->has('metadata')) $operator->metadata = $request->metadata;
            $operator->save();
            
            // Обновляем профиль
            if ($request->has('status') && $operator->profile) {
                $operator->profile->status = $request->status;
                $operator->profile->save();
            }
            
            return response()->json([
                'success' => true,
                'data' => $operator->fresh(['profile', 'role'])
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении оператора: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении оператора: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить оператора.
     *
     * @param int $operatorId ID оператора
     * @return JsonResponse
     */
    public function destroy(int $operatorId): JsonResponse
    {
        try {
            $operator = User::whereHas('role', function($q) {
                    $q->where('slug', 'operator');
                })
                ->find($operatorId);
            
            if (!$operator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оператор не найден'
                ], 404);
            }
            
            // Удаляем профиль
            if ($operator->profile) {
                $operator->profile->delete();
            }
            
            // Удаляем пользователя
            $operator->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Оператор успешно удален'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении оператора: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении оператора: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику всех операторов.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllStats(Request $request): JsonResponse
    {
        try {
            $period = $request->period ?? 'day';
            
            // Получаем всех операторов
            $operators = User::whereHas('role', function($q) {
                $q->where('slug', 'operator');
            })->with('profile')->get();
            
            $stats = [];
            foreach ($operators as $operator) {
                $operatorStats = [
                    'clients_served_period' => 0,
                    'period_average_service_time' => 0,
                    'total_service_time' => 0,
                ];
                
                // Здесь можно добавить логику расчета статистики для каждого оператора
                // на основе данных из профиля или других источников
                
                if ($operator->profile && isset($operator->profile->attributes['stats'])) {
                    $profileStats = json_decode($operator->profile->attributes, true)['stats'] ?? [];
                    $operatorStats['clients_served_period'] = $profileStats['clients_served_today'] ?? 0;
                    $operatorStats['period_average_service_time'] = $profileStats['average_service_time'] ?? 0;
                }
                
                $stats[] = [
                    'operator' => $operator->only(['id', 'name', 'email', 'phone']),
                    'profile' => $operator->profile,
                    'stats' => $operatorStats,
                ];
            }
            
            // Суммарная статистика
            $totalClientsServed = array_sum(array_column($stats, 'stats.clients_served_period'));
            $totalServiceTime = array_sum(array_column($stats, 'stats.period_average_service_time')) * count($operators);
            $averageServiceTime = count($operators) > 0 ? $totalServiceTime / count($operators) : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'operators' => $stats,
                    'summary' => [
                        'total_operators' => count($operators),
                        'active_operators' => $operators->whereIn('profile.status', ['available', 'busy'])->count(),
                        'total_clients_served' => $totalClientsServed,
                        'average_service_time' => round($averageServiceTime),
                        'period' => $period,
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении статистики операторов: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики операторов'
            ], 500);
        }
    }
}
