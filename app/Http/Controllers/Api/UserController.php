<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Получить список всех пользователей.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $users = User::with('role')->get();
            
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении списка пользователей: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка пользователей'
            ], 500);
        }
    }

    /**
     * Создать нового пользователя.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'role_id' => 'required|exists:roles,id',
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
                'phone' => $request->phone,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно создан',
                'data' => $user->load('role')
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ошибка при создании пользователя: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании пользователя'
            ], 500);
        }
    }

    /**
     * Получить информацию о конкретном пользователе.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $user = User::with('role')->find($id);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации о пользователе: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении информации о пользователе'
            ], 500);
        }
    }

    /**
     * Обновить информацию о пользователе.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|string|email|max:255|unique:users,email,' . $id,
                'password' => 'nullable|string|min:8',
                'role_id' => 'nullable|exists:roles,id',
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            if ($request->has('name')) {
                $user->name = $request->name;
            }
            
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }
            
            if ($request->has('role_id')) {
                $user->role_id = $request->role_id;
            }
            
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }
            
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно обновлен',
                'data' => $user->load('role')
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении пользователя: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении пользователя'
            ], 500);
        }
    }

    /**
     * Удалить пользователя.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $user = User::find($id);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ], 404);
            }
            
            $user->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно удален'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении пользователя: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении пользователя'
            ], 500);
        }
    }
}
