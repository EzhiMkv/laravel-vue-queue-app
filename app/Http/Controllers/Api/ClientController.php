<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\User;
use App\Models\Profile;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
{
    /**
     * Получить список всех клиентов.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Получаем ID роли клиента
            $clientRole = Role::where('slug', 'client')->first();
            
            if (!$clientRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Роль клиента не найдена'
                ], 404);
            }
            
            // Получаем пользователей с ролью клиента
            $query = User::where('role_id', $clientRole->id)
                ->with('profile');
            
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
                $clients = $query->paginate($request->per_page);
            } else {
                $clients = $query->get();
            }
            
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
            $client = User::with('profile', 'role')
                ->whereHas('role', function($q) {
                    $q->where('slug', 'client');
                })
                ->find($clientId);
            
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
                'email' => 'nullable|email|max:255|unique:users,email',
                'metadata' => 'nullable|array'
            ]);
            
            // Получаем роль клиента
            $clientRole = Role::where('slug', 'client')->first();
            
            if (!$clientRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Роль клиента не найдена'
                ], 404);
            }
            
            // Создаем пользователя
            $client = new User();
            $client->name = $request->name;
            $client->email = $request->email;
            $client->phone = $request->phone;
            $client->password = Hash::make(str_random(10)); // Генерируем случайный пароль
            $client->role_id = $clientRole->id;
            $client->metadata = $request->metadata;
            $client->save();
            
            // Создаем профиль
            $profile = new Profile();
            $profile->user_id = $client->id;
            $profile->type = 'client';
            $profile->status = 'waiting';
            $profile->attributes = [
                'preferences' => [],
                'history' => [],
                'visits_count' => 0,
                'last_visit_date' => null,
                'satisfaction_rating' => null,
            ];
            $profile->save();
            
            return response()->json([
                'success' => true,
                'data' => $client->load('profile')
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ошибка при создании клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании клиента: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить информацию о клиенте.
     *
     * @param Request $request
     * @param int $clientId ID клиента
     * @return JsonResponse
     */
    public function update(Request $request, int $clientId): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|nullable|string|max:20',
                'email' => 'sometimes|nullable|email|max:255|unique:users,email,' . $clientId,
                'status' => 'sometimes|string|in:waiting,active,blocked',
                'metadata' => 'sometimes|nullable|array'
            ]);
            
            $client = User::with('profile')
                ->whereHas('role', function($q) {
                    $q->where('slug', 'client');
                })
                ->find($clientId);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            // Обновляем данные пользователя
            if ($request->has('name')) $client->name = $request->name;
            if ($request->has('email')) $client->email = $request->email;
            if ($request->has('phone')) $client->phone = $request->phone;
            if ($request->has('metadata')) $client->metadata = $request->metadata;
            $client->save();
            
            // Обновляем профиль
            if ($request->has('status') && $client->profile) {
                $client->profile->status = $request->status;
                $client->profile->save();
            }
            
            return response()->json([
                'success' => true,
                'data' => $client->fresh(['profile'])
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении клиента: ' . $e->getMessage()
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
            $client = User::whereHas('role', function($q) {
                    $q->where('slug', 'client');
                })
                ->find($clientId);
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            // Удаляем профиль
            if ($client->profile) {
                $client->profile->delete();
            }
            
            // Удаляем пользователя
            $client->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Клиент успешно удален'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении клиента: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении клиента: ' . $e->getMessage()
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
            $client = User::where('email', $user->email)->first();
            
            if (!$client) {
                // Если клиент не найден, создаем его
                $client = new User();
                $client->name = $user->name;
                $client->email = $user->email;
                $client->phone = $user->phone;
                $client->password = Hash::make(str_random(10)); // Генерируем случайный пароль
                $client->role_id = Role::where('slug', 'client')->first()->id;
                $client->save();
                
                // Создаем профиль
                $profile = new Profile();
                $profile->user_id = $client->id;
                $profile->type = 'client';
                $profile->status = 'waiting';
                $profile->attributes = [
                    'preferences' => [],
                    'history' => [],
                    'visits_count' => 0,
                    'last_visit_date' => null,
                    'satisfaction_rating' => null,
                ];
                $profile->save();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->only(['id', 'name', 'email', 'avatar', 'phone']),
                    'client' => $client->load('profile'),
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
            $client = User::where('email', $user->email)->first();
            
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
                    'client' => $client->fresh(['profile']),
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
            $client = User::where('email', $user->email)->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            // TODO: реализовать получение позиций клиента во всех очередях
            
            return response()->json([
                'success' => true,
                'data' => []
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
            $client = User::where('email', $user->email)->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            // TODO: реализовать получение позиции клиента в конкретной очереди
            
            return response()->json([
                'success' => true,
                'data' => []
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
            $client = User::where('email', $user->email)->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент не найден'
                ], 404);
            }
            
            // TODO: реализовать получение истории обслуживания клиента
            
            return response()->json([
                'success' => true,
                'data' => []
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
