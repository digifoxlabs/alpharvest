<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('support_phone');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->string('whatsapp_brand_name')->nullable()->after('meta_access_token');
            $table->text('whatsapp_welcome_text')->nullable()->after('whatsapp_brand_name');
            $table->text('whatsapp_store_intro')->nullable()->after('whatsapp_welcome_text');
            $table->text('whatsapp_contact_text')->nullable()->after('whatsapp_store_intro');
            $table->string('whatsapp_store_image_path')->nullable()->after('whatsapp_contact_text');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'contact_email',
                'contact_phone',
                'whatsapp_brand_name',
                'whatsapp_welcome_text',
                'whatsapp_store_intro',
                'whatsapp_contact_text',
                'whatsapp_store_image_path',
            ]);
        });
    }
};
