<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'barcode',
        'sku',
        'name',
        'name_urdu',
        'category',
        'unit',
        'purchase_price',
        'retail_price',
        'min_stock_alert',
        'tax_rate',
        'is_active',
        'version',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'min_stock_alert' => 'integer',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Calculated stock based on the append-only stock movement ledger.
     */
    public function getCurrentStockAttribute(): int
    {
        return (int) $this->stockMovements()->sum('quantity');
    }
}

