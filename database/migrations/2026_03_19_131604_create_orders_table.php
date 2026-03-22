<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'delivered'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('payment_method', ['MPESA', 'Flutterwave', 'DPO', 'PesaPal'])->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
