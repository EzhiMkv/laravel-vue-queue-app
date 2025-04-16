<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Client;
use App\Models\Operator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Сначала создаем роли
        $this->call([
            RoleSeeder::class,
        ]);
        
        // Получаем роли
        $adminRole = Role::where('slug', 'admin')->first();
        $operatorRole = Role::where('slug', 'operator')->first();
        $clientRole = Role::where('slug', 'client')->first();
        
        // Создаем администратора
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'id' => Str::uuid(),
                'name' => 'Администратор',
                'password' => bcrypt('admin123'),
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
            ]
        );
        
        // Создаем операторов
        $operators = [
            [
                'email' => 'operator1@example.com',
                'name' => 'Оператор 1',
                'password' => bcrypt('operator123'),
            ],
            [
                'email' => 'operator2@example.com',
                'name' => 'Оператор 2',
                'password' => bcrypt('operator123'),
            ],
        ];
        
        foreach ($operators as $operatorData) {
            $user = User::updateOrCreate(
                ['email' => $operatorData['email']],
                [
                    'id' => Str::uuid(),
                    'name' => $operatorData['name'],
                    'password' => $operatorData['password'],
                    'role_id' => $operatorRole->id,
                    'email_verified_at' => now(),
                ]
            );
            
            // Создаем запись оператора
            Operator::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'id' => Str::uuid(),
                    'status' => 'available',
                    'max_clients_per_day' => 50,
                    'skills' => ['general', 'support'],
                    'metadata' => [],
                ]
            );
        }
        
        // Создаем клиентов
        $clients = [
            [
                'email' => 'client1@example.com',
                'name' => 'Клиент 1',
                'password' => bcrypt('client123'),
            ],
            [
                'email' => 'client2@example.com',
                'name' => 'Клиент 2',
                'password' => bcrypt('client123'),
            ],
        ];
        
        foreach ($clients as $clientData) {
            $user = User::updateOrCreate(
                ['email' => $clientData['email']],
                [
                    'id' => Str::uuid(),
                    'name' => $clientData['name'],
                    'password' => $clientData['password'],
                    'role_id' => $clientRole->id,
                    'email_verified_at' => now(),
                ]
            );
            
            // Создаем запись клиента
            Client::updateOrCreate(
                ['email' => $user->email],
                [
                    'id' => Str::uuid(),
                    'name' => $user->name,
                    'phone' => '+7' . rand(9000000000, 9999999999),
                    'status' => 'active',
                    'metadata' => [],
                ]
            );
        }
        
        // Создаем дополнительных клиентов без пользователей
        Client::factory(10)->create();
    }
}
