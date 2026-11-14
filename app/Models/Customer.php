<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'phone',
        'city',
        'credit_limit',
        'notes',
        'version',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->uuid)) {
                $customer->uuid = (string) Str::uuid();
            }
        });
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class)->orderBy('entry_date', 'desc');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Outstanding Khata balance derived from double-entry ledger.
     * Balance = Sum(Debit) - Sum(Credit)
     */
    public function getCurrentBalanceAttribute(): float
    {
        $debit = (float) $this->ledgerEntries()->sum('debit');
        $credit = (float) $this->ledgerEntries()->sum('credit');

        return round($debit - $credit, 2);
    }
}
