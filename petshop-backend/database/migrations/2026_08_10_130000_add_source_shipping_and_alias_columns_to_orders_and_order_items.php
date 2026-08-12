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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'source')) {
                $table->string('source')->nullable()->default('WEB')->after('order_type');
            }
            if (!Schema::hasColumn('orders', 'shipping_fee')) {
                $table->decimal('shipping_fee', 10, 2)->default(0.00)->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'phone')) {
                $table->string('phone')->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('orders', 'city')) {
                $table->string('city')->nullable()->after('customer_city');
            }
            if (!Schema::hasColumn('orders', 'address')) {
                $table->text('address')->nullable()->after('customer_address');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'total')) {
                $table->decimal('total', 10, 2)->nullable()->after('unit_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['source', 'shipping_fee', 'phone', 'city', 'address'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'total')) {
                $table->dropColumn('total');
            }
        });
    }
};
