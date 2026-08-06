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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('barcode')->unique(); // Vital for fast scan
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price_buy', 10, 2);  // Prix d'achat
            $table->decimal('price_sell', 10, 2); // Prix de vente
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_alert')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Index for lightning fast barcode lookup
            $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
