<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Al-Madina Cash & Carry',
                'branch' => 'Main Boulevard, Gulberg III, Lahore',
                'currency' => 'PKR',
                'status' => 'active',
            ]);
        }

        // 1. Admin / Manager User
        User::updateOrCreate(
            ['email' => 'admin@posify.pk'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Hammad Hussain',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'pin_code' => Hash::make('1234'),
                'is_active' => true,
            ]
        );

        // 2. Cashier User
        User::updateOrCreate(
            ['email' => 'cashier1@posify.pk'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Bilal Ahmed',
                'role' => 'cashier',
                'password' => Hash::make('password'),
                'pin_code' => Hash::make('7788'),
                'is_active' => true,
            ]
        );
    }
}
