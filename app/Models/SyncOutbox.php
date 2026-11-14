<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SyncOutbox extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sync_outbox';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'table_name',
        'record_uuid',
        'action',
        'payload_json',
        'status',
        'device_id',
        'version',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SyncOutbox $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
        });
    }
}
