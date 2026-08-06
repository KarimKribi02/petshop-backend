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
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Null if Web customer
            $table->enum('order_type', ['POS', 'WEB']);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['PENDING', 'COMPLETED', 'CANCELLED'])->default('COMPLETED');
            $table->string('payment_method')->default('CASH'); // CASH, CARD, ONLINE
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
