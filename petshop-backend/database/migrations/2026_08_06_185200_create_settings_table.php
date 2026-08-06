<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name')->default('Petshop Boutique');
            $table->string('support_email')->nullable();
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();
            $table->text('store_description')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('currency')->default('DH');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
