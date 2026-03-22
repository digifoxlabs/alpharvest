<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price', 12, 2)->nullable()->after('price');
            $table->string('color')->nullable()->after('description');
            $table->string('size')->nullable()->after('color');
            $table->decimal('shipping_weight', 8, 2)->nullable()->after('size');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('compare_at_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('compare_at_price', 12, 2)->nullable()->after('sale_price');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'color', 'size', 'shipping_weight']);
        });
    }
};
