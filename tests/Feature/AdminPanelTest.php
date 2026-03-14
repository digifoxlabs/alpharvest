<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_loads(): void
    {
        $response = $this->get('/admin');

        $response->assertOk()
            ->assertSee('Operations overview')
            ->assertSee('Manage tenants')
            ->assertSee('Manage products');
    }

    public function test_admin_can_manage_tenants_stores_categories_products_and_images(): void
    {
        Storage::fake('public');
        Http::fake();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->post(route('admin.tenants.store'), [
            'name' => 'Northwind Commerce',
            'slug' => 'northwind-commerce',
            'plan' => 'growth',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'is_active' => '1',
        ])->assertRedirect(route('admin.tenants.index'));

        $tenant = Tenant::query()->where('slug', 'northwind-commerce')->firstOrFail();

        $this->post(route('admin.stores.store'), [
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
            'slug' => 'northwind-wellness',
            'support_phone' => '+15551110000',
            'contact_email' => 'owner@northwind.test',
            'contact_phone' => '+15552223333',
            'description' => 'A WhatsApp wellness storefront.',
            'currency' => 'USD',
            'whatsapp_phone_number_id' => '4477889900',
            'whatsapp_business_account_id' => '99887766',
            'meta_catalog_id' => '5544332211',
            'meta_access_token' => 'secret-token',
            'whatsapp_brand_name' => 'Northwind Store',
            'whatsapp_welcome_text' => 'Hi! Choose Visit Store, Orders, or Contact.',
            'whatsapp_store_intro' => 'Browse our store in WhatsApp.',
            'whatsapp_contact_text' => 'Support responds within one business day.',
            'whatsapp_store_image' => UploadedFile::fake()->image('store-front.png'),
            'is_active' => '1',
        ])->assertRedirect(route('admin.stores.index'));

        $store = Store::query()->where('slug', 'northwind-wellness')->firstOrFail();

        $this->assertNotNull($store->whatsapp_store_image_path);
        Storage::disk('public')->assertExists($store->whatsapp_store_image_path);

        $this->post(route('admin.categories.store'), [
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
            'description' => 'Adaptogens and daily support.',
            'sort_order' => 2,
            'is_active' => '1',
        ])->assertRedirect(route('admin.categories.index'));

        $category = ProductCategory::query()->where('slug', 'wellness')->firstOrFail();

        $this->post(route('admin.products.store'), [
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Daily Calm',
            'slug' => 'daily-calm',
            'sku' => 'CAL-001',
            'meta_retailer_id' => 'catalog-CAL-001',
            'description' => 'Daily support blend.',
            'image' => UploadedFile::fake()->image('daily-calm.png'),
            'price' => '29.99',
            'compare_at_price' => '34.99',
            'inventory_quantity' => 25,
            'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('sku', 'CAL-001')->firstOrFail();

        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);

        $this->put(route('admin.products.update', $product), [
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Daily Calm Plus',
            'slug' => 'daily-calm-plus',
            'sku' => 'CAL-001',
            'meta_retailer_id' => 'catalog-CAL-001',
            'description' => 'Updated formula.',
            'price' => '31.99',
            'compare_at_price' => '36.99',
            'inventory_quantity' => 18,
            'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Northwind Commerce',
        ]);

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
            'contact_email' => 'owner@northwind.test',
            'contact_phone' => '+15552223333',
            'whatsapp_brand_name' => 'Northwind Store',
            'meta_catalog_id' => '5544332211',
        ]);

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'store_id' => $store->id,
            'name' => 'Wellness',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Daily Calm Plus',
            'slug' => 'daily-calm-plus',
            'meta_retailer_id' => 'catalog-CAL-001',
            'price' => 31.99,
            'inventory_quantity' => 18,
        ]);
    }

    public function test_admin_can_view_catalog_readiness_and_message_statuses(): void
    {
        Http::fake();

        $tenant = Tenant::factory()->create([
            'name' => 'Northwind Commerce',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
            'slug' => 'northwind-wellness',
            'whatsapp_phone_number_id' => '4477889900',
            'meta_catalog_id' => '5544332211',
            'meta_access_token' => 'secret-token',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Daily Calm',
            'slug' => 'daily-calm',
            'sku' => 'CAL-001',
            'meta_retailer_id' => 'catalog-CAL-001',
            'inventory_quantity' => 12,
        ]);

        $customer = Customer::factory()->create([
            'store_id' => $store->id,
            'phone' => '15551234567',
        ]);

        $conversation = Conversation::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'source' => 'whatsapp',
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'interactive',
            'whatsapp_message_id' => 'wamid.outbound.1',
            'body' => 'Catalog sent.',
            'payload' => ['dispatched' => true],
            'sent_at' => now()->subMinute(),
            'delivered_at' => now(),
        ]);

        $this->get(route('admin.stores.index'))
            ->assertOk()
            ->assertSee('Native catalog ready')
            ->assertSee('Mapped products 1/1');

        $this->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Message statuses')
            ->assertSee('Delivered')
            ->assertSee('Catalog sent.');
    }
}
