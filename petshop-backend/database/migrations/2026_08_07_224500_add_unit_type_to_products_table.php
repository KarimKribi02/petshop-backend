<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 'PIECE' (Unité classique) ou 'WEIGHT' (Au kilo / gramme)
            if (!Schema::hasColumn('products', 'unit_type')) {
                $table->enum('unit_type', ['PIECE', 'WEIGHT'])->default('PIECE')->after('stock_quantity');
            }
            if (!Schema::hasColumn('products', 'price_per_kg')) {
                $table->decimal('price_per_kg', 10, 2)->nullable()->after('price_sell');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unit_type')) {
                $table->dropColumn('unit_type');
            }
            if (Schema::hasColumn('products', 'price_per_kg')) {
                $table->dropColumn('price_per_kg');
            }
        });
    }
};
