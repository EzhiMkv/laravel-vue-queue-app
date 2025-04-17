<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Администратор',
                'slug' => 'admin',
                'description' => 'Полный доступ ко всем функциям системы',
                'permissions' => [
                    'queue.view', 'queue.create', 'queue.edit', 'queue.delete',
                    'client.view', 'client.create', 'client.edit', 'client.delete',
                    'operator.view', 'operator.create', 'operator.edit', 'operator.delete',
                    'stats.view', 'settings.edit',
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
                ],
            ],
            [
                'name' => 'Оператор',
                'slug' => 'operator',
                'description' => 'Обслуживание клиентов в очереди',
                'permissions' => [
                    'queue.view',
                    'client.view',
                    'client.serve',
                    'operator.profile',
                    'stats.view.own',
                ],
            ],
            [
                'name' => 'Клиент',
                'slug' => 'client',
                'description' => 'Доступ к просмотру очередей и записи в них',
                'permissions' => [
                    'queue.view',
                    'queue.join',
                    'queue.leave',
                    'client.profile',
                ],
            ],
        ];

        // Сначала очистим таблицу ролей, чтобы ID были последовательными
        Role::truncate();
        
        // Теперь создадим роли с автоинкрементными ID
        foreach ($roles as $index => $roleData) {
            Role::create([
                'id' => $index + 1, // Явно указываем ID, начиная с 1
                'slug' => $roleData['slug'],
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'permissions' => $roleData['permissions'],
            ]);
        }
    }
}
