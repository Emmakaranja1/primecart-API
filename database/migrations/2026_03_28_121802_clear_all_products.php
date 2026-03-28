<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clear all existing products
        DB::table('products')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is one-way, no rollback needed
        // Products can be recreated manually if needed
    }
};
