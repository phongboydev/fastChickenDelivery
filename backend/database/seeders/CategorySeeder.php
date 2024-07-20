<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Category 1',
                'description' => 'Description 1',
                'status' => 'active',
            ],
            [
                'name' => 'Category 2',
                'description' => 'Description 2',
                'status' => 'active',
            ],
            [
                'name' => 'Category 3',
                'description' => 'Description 3',
                'status' => 'active',
            ],
            [
                'name' => 'Category 4',
                'description' => 'Description 4',
                'status' => 'active',
            ],
            [
                'name' => 'Category 5',
                'description' => 'Description 5',
                'status' => 'active',
            ],
        ];

        Category::insert($categories);
    }
}
