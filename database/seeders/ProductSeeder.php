<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'title' => 'iPhone 15 Pro',
                'description' => 'Latest iPhone with A17 Pro chip',
                'price' => 999.99,
                'quantity' => 50,
                'category' => 'Smartphones',
                'brand' => 'Apple',
                'is_active' => true,
                'featured' => true,
            ],
            [
                'title' => 'Samsung Galaxy S24',
                'description' => 'Flagship Android phone',
                'price' => 899.99,
                'quantity' => 30,
                'category' => 'Smartphones',
                'brand' => 'Samsung',
                'is_active' => true,
                'featured' => true,
            ],
            [
                'title' => 'Nike Air Max',
                'description' => 'Comfortable running shoes',
                'price' => 129.99,
                'quantity' => 100,
                'category' => 'Shoes',
                'brand' => 'Nike',
                'is_active' => true,
                'featured' => false,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}