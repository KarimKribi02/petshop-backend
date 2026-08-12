<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. All existing orders created without shipping city/address are POS Caisse sales
        DB::table('orders')
            ->where(function ($q) {
                $q->whereNull('city')->orWhere('city', '');
            })
            ->where(function ($q) {
                $q->whereNull('customer_city')->orWhere('customer_city', '');
            })
            ->update([
                'source'     => 'POS',
                'order_type' => 'POS',
            ]);

        // 2. Orders that have city & address filled are E-Commerce website orders
        DB::table('orders')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('city')->where('city', '!=', '');
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('customer_city')->where('customer_city', '!=', '');
                });
            })
            ->update([
                'source'     => 'WEB',
                'order_type' => 'WEB',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
