<?php

namespace App\Repositories\Eloquent;

use App\Models\SyncOutbox;
use App\Repositories\Contracts\SyncOutboxRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SyncOutboxRepository implements SyncOutboxRepositoryInterface
{
    public function recordOutbox(
        int $tenantId,
        string $table,
        string $recordUuid,
        string $action,
        array $payload,
        string $deviceId = 'REG-01'
    ): SyncOutbox {
        return SyncOutbox::create([
            'tenant_id' => $tenantId,
            'table_name' => $table,
            'record_uuid' => $recordUuid,
            'action' => $action,
            'payload_json' => $payload,
            'status' => 'pending',
            'device_id' => $deviceId,
        ]);
    }

    public function getPending(int $tenantId, int $limit = 50): Collection
    {
        return SyncOutbox::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }

    public function markAsSynced(array $ids): int
    {
        return SyncOutbox::whereIn('id', $ids)->update(['status' => 'synced']);
    }
}

