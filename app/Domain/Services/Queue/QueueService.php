<?php

namespace App\Domain\Services\Queue;

use App\Domain\Contracts\Queue\QueueServiceInterface;
use App\Models\Client;
use App\Models\Queue;
use App\Models\QueuePosition;

/**
 * Агрегирующий сервис для работы с очередями.
 * Реализует все интерфейсы, связанные с очередями.
 */
class QueueService implements QueueServiceInterface
{
    /**
     * @var QueueManagementService
     */
    private $managementService;

    /**
     * @var QueueClientOperationsService
     */
    private $clientOperationsService;

    /**
     * @var QueueAnalyticsService
     */
    private $analyticsService;

    /**
     * Конструктор сервиса.
     *
     * @param QueueManagementService $managementService
     * @param QueueClientOperationsService $clientOperationsService
     * @param QueueAnalyticsService $analyticsService
     */
    public function __construct(
        QueueManagementService $managementService,
        QueueClientOperationsService $clientOperationsService,
        QueueAnalyticsService $analyticsService
    ) {
        $this->managementService = $managementService;
        $this->clientOperationsService = $clientOperationsService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * {@inheritdoc}
     */
    public function createQueue(array $data): Queue
    {
        return $this->managementService->createQueue($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueue(string $queueId): ?Queue
    {
        return $this->managementService->getQueue($queueId);
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveQueues()
    {
        return $this->managementService->getActiveQueues();
    }

    /**
     * {@inheritdoc}
     */
    public function getQueueState(Queue $queue): array
    {
        return $this->managementService->getQueueState($queue);
    }

    /**
     * {@inheritdoc}
     */
    public function addClientToQueue(Queue $queue, Client $client, string $priority = 'normal'): QueuePosition
    {
        return $this->clientOperationsService->addClientToQueue($queue, $client, $priority);
    }

    /**
     * {@inheritdoc}
     */
    public function removeClientFromQueue(Queue $queue, Client $client): bool
    {
        return $this->clientOperationsService->removeClientFromQueue($queue, $client);
    }

    /**
     * {@inheritdoc}
     */
    public function getNextClient(Queue $queue): ?Client
    {
        return $this->clientOperationsService->getNextClient($queue);
    }

    /**
     * {@inheritdoc}
     */
    public function callNextClient(Queue $queue): ?QueuePosition
    {
        return $this->clientOperationsService->callNextClient($queue);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueueStats(Queue $queue, string $period = 'day'): array
    {
        return $this->analyticsService->getQueueStats($queue, $period);
    }
}
