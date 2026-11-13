<?php

namespace App\Services\Pos;

use App\Models\SyncOutbox;
use App\Repositories\Contracts\SyncOutboxRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SyncOutboxService
{
    public function __construct(
        protected SyncOutboxRepositoryInterface $syncOutboxRepo
    ) {}

    public function recordChange(
        int $tenantId,
        string $tableName,
        string $recordUuid,
        string $action,
        array $payload,
        string $deviceId = 'REG-01'
    ): SyncOutbox {
        return $this->syncOutboxRepo->recordOutbox(
            $tenantId,
            $tableName,
            $recordUuid,
            $action,
            $payload,
            $deviceId
        );
    }

    public function getPendingQueue(int $tenantId, int $limit = 50): Collection
    {
        return $this->syncOutboxRepo->getPending($tenantId, $limit);
    }

    public function acknowledgeSync(array $ids): int
    {
        return $this->syncOutboxRepo->markAsSynced($ids);
    }
}

