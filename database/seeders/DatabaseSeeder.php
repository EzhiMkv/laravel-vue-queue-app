<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Запускаем сидеры в нужном порядке
        $this->call([
            // Сначала создаем роли
            RoleSeeder::class,
            
            // Затем создаем пользователей с разными ролями
            AdminUserSeeder::class,
            OperatorSeeder::class,
            ClientSeeder::class,
        ]);
        
        $this->command->info('🔥 Все данные успешно загружены! 🔥');
    }
}
