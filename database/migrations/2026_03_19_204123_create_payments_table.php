<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('gateway', ['MPESA', 'Flutterwave', 'DPO', 'PesaPal']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('transaction_id')->unique()->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('checkout_request_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->json('webhook_data')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['gateway', 'status']);
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
