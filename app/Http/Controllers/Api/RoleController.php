<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Получить список всех ролей.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $roles = Role::withCount('users')->get();
            
            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении списка ролей: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка ролей: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Создать новую роль.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:roles,name',
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $role = new Role();
            $role->name = $request->name;
            $role->slug = Str::slug($request->name);
            $role->description = $request->description;
            $role->permissions = $request->permissions ?? [];
            $role->save();

            return response()->json([
                'success' => true,
                'message' => 'Роль успешно создана',
                'data' => $role
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ошибка при создании роли: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании роли: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить информацию о конкретной роли.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $role = Role::withCount('users')->find($id);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Роль не найдена'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $role
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации о роли: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении информации о роли: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить информацию о роли.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $role = Role::find($id);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Роль не найдена'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255|unique:roles,name,' . $id,
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            if ($request->has('name')) {
                $role->name = $request->name;
                $role->slug = Str::slug($request->name);
            }
            
            if ($request->has('description')) {
                $role->description = $request->description;
            }
            
            if ($request->has('permissions')) {
                $role->permissions = $request->permissions;
            }
            
            $role->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Роль успешно обновлена',
                'data' => $role
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении роли: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении роли: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить роль.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $role = Role::find($id);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Роль не найдена'
                ], 404);
            }
            
            // Проверяем, есть ли пользователи с этой ролью
            $usersCount = $role->users()->count();
            if ($usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить роль, так как она назначена ' . $usersCount . ' пользователям'
                ], 400);
            }
            
            $role->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Роль успешно удалена'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении роли: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении роли: ' . $e->getMessage()
            ], 500);
        }
    }
}
