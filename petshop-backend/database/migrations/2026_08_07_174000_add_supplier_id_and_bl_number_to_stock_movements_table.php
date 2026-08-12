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
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('user_id')->constrained('suppliers')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_movements', 'bl_number')) {
                $table->string('bl_number')->nullable()->after('supplier_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            }
            if (Schema::hasColumn('stock_movements', 'bl_number')) {
                $table->dropColumn('bl_number');
            }
        });
    }
};
