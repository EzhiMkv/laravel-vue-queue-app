<?php

namespace App\Domain\Contracts;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Queue;
use App\Models\ServiceLog;

interface OperatorServiceInterface
{
    /**
     * Создать нового оператора.
     *
     * @param array $data Данные оператора
     * @return Operator
     */
    public function createOperator(array $data): Operator;

    /**
     * Получить оператора по ID.
     *
     * @param string $operatorId ID оператора
     * @return Operator|null
     */
    public function getOperator(string $operatorId): ?Operator;

    /**
     * Обновить данные оператора.
     *
     * @param string $operatorId ID оператора
     * @param array $data Новые данные
     * @return Operator|null
     */
    public function updateOperator(string $operatorId, array $data): ?Operator;

    /**
     * Получить список всех операторов.
     *
     * @param array $filters Фильтры
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOperators(array $filters = []);

    /**
     * Назначить оператора на очередь.
     *
     * @param Operator $operator Оператор
     * @param Queue $queue Очередь
     * @return bool
     */
    public function assignOperatorToQueue(Operator $operator, Queue $queue): bool;

    /**
     * Изменить статус оператора.
     *
     * @param Operator $operator Оператор
     * @param string $status Новый статус
     * @return bool
     */
    public function changeOperatorStatus(Operator $operator, string $status): bool;

    /**
     * Начать обслуживание клиента.
     *
     * @param Operator $operator Оператор
     * @param Client $client Клиент
     * @param Queue $queue Очередь
     * @return ServiceLog
     */
    public function startServingClient(Operator $operator, Client $client, Queue $queue): ServiceLog;

    /**
     * Завершить обслуживание клиента.
     *
     * @param Operator $operator Оператор
     * @param ServiceLog $serviceLog Лог обслуживания
     * @param string $status Статус завершения
     * @param array $data Дополнительные данные
     * @return bool
     */
    public function finishServingClient(Operator $operator, ServiceLog $serviceLog, string $status = 'completed', array $data = []): bool;

    /**
     * Получить статистику оператора.
     *
     * @param Operator $operator Оператор
     * @param string $period Период (day, week, month)
     * @return array
     */
    public function getOperatorStats(Operator $operator, string $period = 'day'): array;

    /**
     * Получить доступных операторов для очереди.
     *
     * @param Queue $queue Очередь
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableOperatorsForQueue(Queue $queue);
}
