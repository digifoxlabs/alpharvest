<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_endpoint_returns_active_catalog(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'tenant-one',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'demo-store',
            'currency' => 'USD',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Coffee',
            'slug' => 'coffee',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Morning Lift Coffee',
            'slug' => 'morning-lift-coffee',
            'sku' => 'COF-250',
            'price' => 18.50,
            'inventory_quantity' => 22,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Hidden Product',
            'slug' => 'hidden-product',
            'sku' => 'HDN-001',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/storefront/{$store->slug}");

        $response->assertOk()
            ->assertJsonPath('store.slug', $store->slug)
            ->assertJsonPath('categories.0.slug', 'coffee')
            ->assertJsonPath('categories.0.products.0.sku', 'COF-250')
            ->assertJsonMissing(['sku' => 'HDN-001']);
    }

    public function test_web_catalog_page_renders_categories_and_products(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'tenant-one',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'demo-store',
            'currency' => 'INR',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Rice',
            'slug' => 'rice',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Joha Rice',
            'slug' => 'joha-rice',
            'sku' => 'RIC-JOH',
            'price' => 629.00,
            'inventory_quantity' => 22,
            'is_active' => true,
        ]);

        $this->get(route('platform.catalog', $store))
            ->assertOk()
            ->assertSee('AlphaHarvest Store')
            ->assertSee('Rice')
            ->assertSee('Joha Rice')
            ->assertSee('RIC-JOH');
    }
}
