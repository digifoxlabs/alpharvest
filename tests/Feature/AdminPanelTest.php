<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_can_manage_tenants_stores_categories_and_products(): void
    {
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
            'description' => 'A WhatsApp wellness storefront.',
            'currency' => 'USD',
            'whatsapp_phone_number_id' => '4477889900',
            'whatsapp_business_account_id' => '99887766',
            'meta_access_token' => 'secret-token',
            'is_active' => '1',
        ])->assertRedirect(route('admin.stores.index'));

        $store = Store::query()->where('slug', 'northwind-wellness')->firstOrFail();

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
            'description' => 'Daily support blend.',
            'price' => '29.99',
            'compare_at_price' => '34.99',
            'inventory_quantity' => 25,
            'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('sku', 'CAL-001')->firstOrFail();

        $this->put(route('admin.products.update', $product), [
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Daily Calm Plus',
            'slug' => 'daily-calm-plus',
            'sku' => 'CAL-001',
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
            'price' => 31.99,
            'inventory_quantity' => 18,
        ]);
    }
}
