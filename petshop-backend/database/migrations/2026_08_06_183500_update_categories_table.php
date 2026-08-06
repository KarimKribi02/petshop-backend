<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('categories', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('categories', 'image')) {
                $table->string('image')->nullable();
            }
            if (! Schema::hasColumn('categories', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('categories', 'image')) {
                $table->dropColumn('image');
            }
            if (Schema::hasColumn('categories', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
