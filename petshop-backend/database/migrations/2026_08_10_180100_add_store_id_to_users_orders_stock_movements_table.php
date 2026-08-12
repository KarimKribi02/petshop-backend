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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'store_id')) {
                $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'store_id')) {
                $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'store_id')) {
                $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'store_id')) {
                $table->dropConstrainedForeignId('store_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'store_id')) {
                $table->dropConstrainedForeignId('store_id');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'store_id')) {
                $table->dropConstrainedForeignId('store_id');
            }
        });
    }
};
