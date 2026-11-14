<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockMovement extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'product_id',
        'quantity',
        'type',
        'reference_type',
        'reference_uuid',
        'notes',
        'device_id',
        'version',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            if (empty($movement->uuid)) {
                $movement->uuid = (string) Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
