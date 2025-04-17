<?php

namespace Database\Seeders;

use App\Enums\ProfileStatus;
use App\Enums\ProfileType;
use Illuminate\Database\Seeder;
use App\Domain\Contracts\Services\UserFactoryServiceInterface;
use App\Domain\Exceptions\UserException;
use App\Domain\Exceptions\ProfileException;
use App\Domain\Exceptions\RoleNotFoundException;
use Illuminate\Support\Facades\App;

class AdminUserSeeder extends Seeder
{
    /**
     * @var UserFactoryServiceInterface
     */
    private UserFactoryServiceInterface $userFactory;
    
    /**
     * Конструктор с внедрением зависимостей.
     * 
     * @param UserFactoryServiceInterface $userFactory
     */
    public function __construct(UserFactoryServiceInterface $userFactory)
    {
        $this->userFactory = $userFactory;
    }
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Создаем админа с профилем
            $userWithProfile = $this->userFactory->createAdmin(
                [
                    'name' => 'Администратор',
                    'email' => 'admin@example.com',
                    'password' => 'admin123',
                    'phone' => '+79001234567', // Добавляем телефон
                    'email_verified_at' => now(),
                ],
                [
                    'status' => ProfileStatus::ACTIVE->value,
                    'attributes' => [
                        'permissions' => [
                            'manage_queues', 'manage_operators', 'manage_clients',
                            'view_stats', 'manage_settings', 'manage_system'
                        ],
                        'settings' => [
                            'theme' => 'dark',
                            'notifications' => true
                        ]
                    ]
                ]
            );
            
            $this->command->info('Админ и его профиль успешно созданы! 🔥');
            $this->command->info('ID пользователя: ' . $userWithProfile->getUser()->id);
            $this->command->info('ID профиля: ' . $userWithProfile->getProfile()->id);
        } catch (RoleNotFoundException $e) {
            $this->command->error('Ошибка: Роль админа не найдена! Убедитесь, что роли созданы.');
        } catch (UserException $e) {
            $this->command->error('Ошибка при создании пользователя: ' . $e->getMessage());
        } catch (ProfileException $e) {
            $this->command->error('Ошибка при создании профиля: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->command->error('Неизвестная ошибка: ' . $e->getMessage());
        }
    }
}
