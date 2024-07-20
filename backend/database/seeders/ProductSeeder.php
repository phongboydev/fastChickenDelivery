<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Product 1',
                'description' => 'Description 1',
                'status' => 'active',
            ],
            [
                'name' => 'Product 2',
                'description' => 'Description 2',
                'status' => 'active',
            ],
            [
                'name' => 'Product 3',
                'description' => 'Description 3',
                'status' => 'active',
            ],
            [
                'name' => 'Product 4',
                'description' => 'Description 4',
                'status' => 'active',
            ],
            [
                'name' => 'Product 5',
                'description' => 'Description 5',
                'status' => 'active',
            ],
        ];

        Product::insert($products);
    }
}
