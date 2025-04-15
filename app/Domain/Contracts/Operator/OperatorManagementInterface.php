<?php

namespace App\Domain\Contracts\Operator;

use App\Models\Operator;

/**
 * Интерфейс для управления операторами.
 */
interface OperatorManagementInterface
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
}
