<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name'); // Ex: Royal Canin Maroc SARL
            $table->string('contact_name')->nullable(); // Ex: Reda Karim
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('ice')->nullable(); // Identifiant Commun de l'Entreprise
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add supplier_id to stock_movements table for BL tracking
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('bl_number')->nullable()->after('supplier_id'); // N° Bon de Livraison
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'bl_number']);
        });
        Schema::dropIfExists('suppliers');
    }
};
