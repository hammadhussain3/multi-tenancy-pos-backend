<?php

namespace App\Repositories\Contracts;

use App\Models\SyncOutbox;
use Illuminate\Database\Eloquent\Collection;

interface SyncOutboxRepositoryInterface
{
    public function recordOutbox(
        int $tenantId,
        string $table,
        string $recordUuid,
        string $action,
        array $payload,
        string $deviceId = 'REG-01'
    ): SyncOutbox;

    public function getPending(int $tenantId, int $limit = 50): Collection;

    public function markAsSynced(array $ids): int;
}

