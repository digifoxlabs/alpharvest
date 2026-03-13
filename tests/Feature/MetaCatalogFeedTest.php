<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetaCatalogFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_products_feed_returns_csv_and_writes_feed_file(): void
    {
        Storage::fake('public');

        $store = Store::factory()->create([
            'slug' => 'meta-demo-store',
            'name' => 'Meta Demo Store',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Coffee',
            'slug' => 'coffee',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Meta Roast',
            'slug' => 'meta-roast',
            'sku' => 'MTA-001',
            'meta_retailer_id' => 'catalog-MTA-001',
            'price' => 22.50,
            'inventory_quantity' => 15,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Inactive Roast',
            'slug' => 'inactive-roast',
            'sku' => 'MTA-002',
            'is_active' => false,
        ]);

        $response = $this->get(route('feeds.meta-products'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertSee('id,title,description,availability,condition,price', false);
        $response->assertSee('catalog-MTA-001', false);
        $response->assertSee('22.50 USD', false);
        $response->assertDontSee('MTA-002', false);

        Storage::disk('public')->assertExists('feeds/meta-products.csv');
    }

    public function test_product_lifecycle_rewrites_feed_and_pushes_meta_batch_updates(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['success' => true], 200),
        ]);

        $store = Store::factory()->create([
            'name' => 'Sync Store',
            'slug' => 'sync-store',
            'currency' => 'USD',
            'meta_catalog_id' => '9988776655',
            'meta_access_token' => 'store-secret-token',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
        ]);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Daily Sync',
            'slug' => 'daily-sync',
            'sku' => 'SYNC-001',
            'meta_retailer_id' => 'catalog-SYNC-001',
            'price' => 19.99,
            'inventory_quantity' => 10,
        ]);

        $feedPath = 'feeds/meta-products.csv';

        $this->assertStringContainsString('Daily Sync', Storage::disk('public')->get($feedPath));

        $product->update([
            'name' => 'Daily Sync Plus',
            'price' => 24.99,
        ]);

        $this->assertStringContainsString('Daily Sync Plus', Storage::disk('public')->get($feedPath));
        $this->assertStringNotContainsString('Daily Sync,', Storage::disk('public')->get($feedPath));

        $product->delete();

        $this->assertStringNotContainsString('Daily Sync Plus', Storage::disk('public')->get($feedPath));

        Http::assertSentCount(3);
        Http::assertSent(function ($request) use ($store) {
            $data = $request->data();

            return $request->url() === 'https://graph.facebook.com/v20.0/'.$store->meta_catalog_id.'/items_batch'
                && $data['access_token'] === 'store-secret-token'
                && $data['requests'][0]['method'] === 'UPDATE'
                && $data['requests'][0]['data']['retailer_id'] === 'catalog-SYNC-001';
        });
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['requests'][0]['method'] === 'DELETE'
                && $data['requests'][0]['retailer_id'] === 'catalog-SYNC-001';
        });
    }
}
