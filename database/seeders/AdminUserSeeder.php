<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Получаем роль админа
        $adminRole = Role::where('slug', 'admin')->first();

        if (!$adminRole) {
            $this->command->error('Роль админа не найдена. Сначала выполните RoleSeeder.');
            return;
        }

        // Создаем админа
        User::create([
            'name' => 'Админ',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
        ]);

        $this->command->info('Админ успешно создан!');
    }
}
