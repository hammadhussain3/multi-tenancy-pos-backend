<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'customer_id',
        'entry_date',
        'reference_type',
        'reference_no',
        'reference_uuid',
        'debit',
        'credit',
        'running_balance',
        'narration',
        'device_id',
        'version',
    ];

    protected $casts = [
        'entry_date' => 'datetime',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerLedgerEntry $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

