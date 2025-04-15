<?php

namespace App\Domain\Services\Client;

use App\Domain\Contracts\Client\ClientServiceInterface;
use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

/**
 * Агрегирующий сервис для работы с клиентами.
 * Реализует все интерфейсы, связанные с клиентами.
 */
class ClientService implements ClientServiceInterface
{
    /**
     * @var ClientManagementService
     */
    private $managementService;

    /**
     * @var ClientQueueInfoService
     */
    private $queueInfoService;

    /**
     * @var ClientHistoryService
     */
    private $historyService;

    /**
     * Конструктор сервиса.
     *
     * @param ClientManagementService $managementService
     * @param ClientQueueInfoService $queueInfoService
     * @param ClientHistoryService $historyService
     */
    public function __construct(
        ClientManagementService $managementService,
        ClientQueueInfoService $queueInfoService,
        ClientHistoryService $historyService
    ) {
        $this->managementService = $managementService;
        $this->queueInfoService = $queueInfoService;
        $this->historyService = $historyService;
    }

    /**
     * {@inheritdoc}
     */
    public function createClient(array $data): Client
    {
        return $this->managementService->createClient($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(int $clientId): ?Client
    {
        return $this->managementService->getClient($clientId);
    }

    /**
     * {@inheritdoc}
     */
    public function updateClient(int $clientId, array $data): ?Client
    {
        return $this->managementService->updateClient($clientId, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getClients(array $filters = [])
    {
        return $this->managementService->getClients($filters);
    }

    /**
     * {@inheritdoc}
     */
    public function getClientPositions(Client $client): array
    {
        return $this->queueInfoService->getClientPositions($client);
    }

    /**
     * {@inheritdoc}
     */
    public function isClientInQueue(Client $client, string $queueId): bool
    {
        return $this->queueInfoService->isClientInQueue($client, $queueId);
    }

    /**
     * {@inheritdoc}
     */
    public function getClientHistory(Client $client)
    {
        return $this->historyService->getClientHistory($client);
    }
}
