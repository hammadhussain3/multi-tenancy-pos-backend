<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RetailPosSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Tenant
        $tenant = Tenant::firstOrCreate(
            ['name' => 'Al-Madina Cash & Carry'],
            [
                'uuid' => (string) Str::uuid(),
                'branch' => 'Main Boulevard, Gulberg III, Lahore',
                'address' => 'Shop 14-16, Commercial Zone, Lahore',
                'phone' => '+92 42 35789012',
                'ntn' => '7823910-4',
                'strn' => '3277876123456',
                'fbr_pos_id' => 'POS-LHR-8821',
                'currency' => 'PKR',
                'status' => 'active',
            ]
        );

        // 2. Create Products with Initial Stock Movements
        $productsData = [
            [
                'barcode' => '8964000123456',
                'sku' => 'BEV-TAPAL-450G',
                'name' => 'Tapal Danedar Tea 450g',
                'name_urdu' => 'ٹپال دانہ دار چائے ۴۵۰ گرام',
                'category' => 'Beverages',
                'unit' => 'pack',
                'purchase_price' => 620.00,
                'retail_price' => 710.00,
                'min_stock_alert' => 10,
                'initial_stock' => 50,
            ],
            [
                'barcode' => '8964000234567',
                'sku' => 'BEV-MILKPAK-1L',
                'name' => 'Nestlé MilkPak 1 Litre',
                'name_urdu' => 'نسلے ملک پیک ۱ لیٹر',
                'category' => 'Dairy',
                'unit' => 'ltr',
                'purchase_price' => 285.00,
                'retail_price' => 320.00,
                'min_stock_alert' => 20,
                'initial_stock' => 100,
            ],
            [
                'barcode' => '8964000345678',
                'sku' => 'GROC-DALDA-5L',
                'name' => 'Dalda Cooking Oil 5 Litre Tin',
                'name_urdu' => 'ڈالڈا کوکنگ آئل ۵ لیٹر',
                'category' => 'Grocery',
                'unit' => 'box',
                'purchase_price' => 2650.00,
                'retail_price' => 2890.00,
                'min_stock_alert' => 5,
                'initial_stock' => 20,
            ],
            [
                'barcode' => '8964000456789',
                'sku' => 'GROC-GUARD-RICE-5K',
                'name' => 'Guard Super Basmati Rice 5kg',
                'name_urdu' => 'گارڈ سپر باسمتی چاول ۵ کلو',
                'category' => 'Grocery',
                'unit' => 'pack',
                'purchase_price' => 1750.00,
                'retail_price' => 1980.00,
                'min_stock_alert' => 8,
                'initial_stock' => 35,
            ],
            [
                'barcode' => '8964000567890',
                'sku' => 'SNK-LU-PRINCE',
                'name' => 'LU Prince Chocolate Biscuits Half Roll',
                'name_urdu' => 'پرنس چاکلیٹ بسکٹ',
                'category' => 'Snacks',
                'unit' => 'pack',
                'purchase_price' => 42.00,
                'retail_price' => 50.00,
                'min_stock_alert' => 25,
                'initial_stock' => 150,
            ],
        ];

        foreach ($productsData as $p) {
            $initialStock = $p['initial_stock'];
            unset($p['initial_stock']);

            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'barcode' => $p['barcode']],
                array_merge($p, [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'tax_rate' => 0.00,
                    'is_active' => true,
                ])
            );

            // Record append-only stock movement for initial stock
            StockMovement::firstOrCreate(
                ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'type' => 'purchase'],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'quantity' => $initialStock,
                    'type' => 'purchase',
                    'notes' => 'Opening stock balance',
                    'device_id' => 'REG-01',
                ]
            );
        }

        // 3. Create Sample Khata Customers & Ledger Entries
        $customer1 = Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '0300-4567890'],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Haji Mohammad Rafique',
                'phone' => '0300-4567890',
                'city' => 'Lahore',
                'credit_limit' => 50000.00,
                'notes' => 'Trusted customer, monthly clearance on 5th',
            ]
        );

        CustomerLedgerEntry::firstOrCreate(
            ['tenant_id' => $tenant->id, 'customer_id' => $customer1->id, 'reference_no' => 'INV-20261101-002'],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'customer_id' => $customer1->id,
                'entry_date' => Carbon::parse('2026-11-01 14:22:00'),
                'reference_type' => 'sale',
                'reference_no' => 'INV-20261101-002',
                'debit' => 18500.00,
                'credit' => 0.00,
                'running_balance' => 18500.00,
                'narration' => 'Grocery bulk order (Dalda, Rice, Tapal)',
                'device_id' => 'REG-01',
            ]
        );

        CustomerLedgerEntry::firstOrCreate(
            ['tenant_id' => $tenant->id, 'customer_id' => $customer1->id, 'reference_no' => 'REC-20261105-001'],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'customer_id' => $customer1->id,
                'entry_date' => Carbon::parse('2026-11-05 18:30:00'),
                'reference_type' => 'payment_received',
                'reference_no' => 'REC-20261105-001',
                'debit' => 0.00,
                'credit' => 10000.00,
                'running_balance' => 8500.00,
                'narration' => 'Cash received via shop counter',
                'device_id' => 'REG-01',
            ]
        );
    }
}

