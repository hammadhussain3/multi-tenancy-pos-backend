<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'subdomain',
        'branch',
        'address',
        'phone',
        'ntn',
        'strn',
        'fbr_pos_id',
        'currency',
        'status',
        'plan',
        'modules',
        'expires_at',
    ];

    protected $casts = [
        'modules' => 'array',
        'expires_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
            if (empty($tenant->modules)) {
                $tenant->modules = ['pos', 'inventory', 'khata', 'reports'];
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && Carbon::parse($this->expires_at)->endOfDay()->isPast();
    }

    public function hasModule(string $module): bool
    {
        if (empty($this->modules)) {
            return false;
        }
        return in_array($module, $this->modules, true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
