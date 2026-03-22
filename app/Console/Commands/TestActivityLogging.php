<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('app:test-activity-logging')]
#[Description('Test the activity logging system functionality')]
class TestActivityLogging extends Command
{
   
    public function handle()
    {
        $this->info('Testing Activity Logging System');
        $this->info('===============================');
        $this->newLine();

        // Test 1: Create a test user and log registration
        $this->info('1. Testing user registration activity logging...');
        $testUser = User::create([
            'username' => 'test_activity_user_' . time(),
            'email' => 'testactivity' . time() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 'active',
        ]);

        ActivityLog::log(
            $testUser->id,
            'registration',
            'user',
            $testUser->id,
            '127.0.0.1'
        );

        $this->info('✓ User registration logged');
        $this->info("  User ID: {$testUser->id}");
        $this->info('  Action: registration');
        $this->newLine();

        // Test 2: Log login activity
        $this->info('2. Testing login activity logging...');
        ActivityLog::log(
            $testUser->id,
            'login',
            'user',
            $testUser->id,
            '127.0.0.1'
        );

        $this->info('✓ User login logged');
        $this->newLine();

        // Test 3: Test product creation logging
        $this->info('3. Testing product creation activity logging...');
        $product = Product::create([
            'title' => 'Test Activity Product ' . time(),
            'description' => 'Product for testing activity logging',
            'price' => 99.99,
            'quantity' => 10,
            'category' => 'Electronics',
            'brand' => 'TestBrand',
            'is_active' => true,
            'featured' => false,
        ]);

        ActivityLog::log(
            $testUser->id,
            'product_created',
            'product',
            $product->id,
            '127.0.0.1'
        );

        $this->info('✓ Product creation logged');
        $this->info("  Product ID: {$product->id}");
        $this->info('  Action: product_created');
        $this->newLine();

        // Test 4: Retrieve and display activity logs
        $this->info('4. Retrieving activity logs...');
        $logs = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($logs as $log) {
            $this->info("Log ID: {$log->id}");
            $this->info("User: {$log->user->username} ({$log->user->email})");
            $this->info("Action: {$log->action}");
            $this->info("Entity: {$log->entity} (ID: {$log->entity_id})");
            $this->info("IP Address: {$log->ip_address}");
            $this->info("Created At: {$log->created_at}");
            $this->info('-------------------');
        }

        $this->newLine();
        $this->info('✅ Activity logging system test completed successfully!');

        // Cleanup test data
        $this->newLine();
        $this->info('Cleaning up test data...');
        ActivityLog::where('user_id', $testUser->id)->delete();
        $product->delete();
        $testUser->delete();
        $this->info('✓ Test data cleaned up');

        return Command::SUCCESS;
    }
}