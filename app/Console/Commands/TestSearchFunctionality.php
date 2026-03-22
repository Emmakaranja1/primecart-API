<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:test-search-functionality')]
#[Description('Test the enhanced search functionality')]
class TestSearchFunctionality extends Command
{
    
    public function handle()
    {
        $this->info('Testing Enhanced Search Functionality');
        $this->info('=====================================');
        $this->newLine();

        // Create test products
        $this->info('Creating test products...');
        $product1 = Product::create([
            'title' => 'Apple iPhone 15',
            'description' => 'Latest Apple smartphone',
            'price' => 999.99,
            'quantity' => 50,
            'category' => 'Electronics',
            'brand' => 'Apple',
            'is_active' => true,
            'featured' => true,
        ]);

        $product2 = Product::create([
            'title' => 'Samsung Galaxy S24',
            'description' => 'Latest Samsung smartphone',
            'price' => 899.99,
            'quantity' => 30,
            'category' => 'Electronics',
            'brand' => 'Samsung',
            'is_active' => true,
            'featured' => false,
        ]);

        $product3 = Product::create([
            'title' => 'Sony Headphones',
            'description' => 'Noise cancelling headphones',
            'price' => 299.99,
            'quantity' => 25,
            'category' => 'Audio',
            'brand' => 'Sony',
            'is_active' => true,
            'featured' => true,
        ]);

        $this->info('✓ Test products created');
        $this->newLine();

        // Test product search by title
        $this->info('1. Testing product search by title (iphone):');
        $results = Product::active()->search('iphone')->get();
        foreach ($results as $product) {
            $this->info("  - {$product->title} ({$product->brand})");
        }
        $this->newLine();

        // Test product search by category
        $this->info('2. Testing product search by category (electronics):');
        $results = Product::active()->search('electronics')->get();
        foreach ($results as $product) {
            $this->info("  - {$product->title} ({$product->category})");
        }
        $this->newLine();

        // Test product search by brand
        $this->info('3. Testing product search by brand (apple):');
        $results = Product::active()->search('apple')->get();
        foreach ($results as $product) {
            $this->info("  - {$product->title} ({$product->brand})");
        }
        $this->newLine();

        // Test product filtering
        $this->info('4. Testing product filtering:');
        $results = Product::active()
            ->byCategory('Electronics')
            ->byBrand('Apple')
            ->featured()
            ->get();
        
        foreach ($results as $product) {
            $this->info("  - {$product->title} ({$product->category}, {$product->brand}, Featured: {$product->featured})");
        }
        $this->newLine();

        // Test order search (create a test order first)
        $this->info('5. Testing order search functionality:');
        
        // Get or create a test user
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'testsearch@example.com'],
            [
                'username' => 'testsearch_user',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'user',
                'status' => 'active',
            ]
        );

        // Create test order
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 999.99,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'MPESA',
            'shipping_address' => '123 Test Street',
            'transaction_reference' => 'TEST-SEARCH-' . time(),
        ]);

        // Test order search by ID
        $this->info('  Search by order ID:');
        $results = Order::search($order->id)->get();
        foreach ($results as $orderResult) {
            $this->info("    - Order #{$orderResult->id} ({$orderResult->payment_method})");
        }

        // Test order search by payment method
        $this->info('  Search by payment method (mpesa):');
        $results = Order::search('mpesa')->get();
        foreach ($results as $orderResult) {
            $this->info("    - Order #{$orderResult->id} ({$orderResult->payment_method})");
        }

        // Test order search by user email
        $this->info('  Search by user email:');
        $results = Order::search('testsearch@example.com')->get();
        foreach ($results as $orderResult) {
            $this->info("    - Order #{$orderResult->id} (User: {$orderResult->user->email})");
        }
        $this->newLine();

        $this->info('✅ Search functionality test completed successfully!');

        // Cleanup
        $this->newLine();
        $this->info('Cleaning up test data...');
        $order->delete();
        $user->delete();
        $product1->delete();
        $product2->delete();
        $product3->delete();
        $this->info('✓ Test data cleaned up');

        return Command::SUCCESS;
    }
}