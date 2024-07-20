<?php

namespace Database\Seeders;

use App\Models\ProductByDay;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductByDaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productByDays = [
            [
                'id' => Str::uuid(),
                'date' => '2024-07-19',
                'product_id' => 1,
                'price' => 200000,
                'status' => 'active',
            ],
            [
                'id' => Str::uuid(),
                'date' => '2024-07-19',
                'product_id' => 2,
                'price' => 300000,
                'status' => 'active',
            ],
            [
                'id' => Str::uuid(),
                'date' => '2021-01-01',
                'product_id' => 3,
                'price' => 100000,
                'status' => 'active',
            ],
            [
                'id' => Str::uuid(),
                'date' => '2021-01-01',
                'product_id' => 4,
                'price' => 10000,
                'status' => 'active',
            ],
        ];

        ProductByDay::insert($productByDays);
    }
}
