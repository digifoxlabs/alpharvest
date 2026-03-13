<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('meta_catalog_id')->nullable()->after('whatsapp_business_account_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_retailer_id')->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('meta_retailer_id');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('meta_catalog_id');
        });
    }
};
