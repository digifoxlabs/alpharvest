<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_hi_message_returns_the_main_whatsapp_menu(): void
    {
        $counter = 0;

        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.base_url' => 'https://graph.facebook.com/v20.0',
        ]);

        Http::fake(function () use (&$counter) {
            $counter++;

            return Http::response([
                'messages' => [
                    ['id' => 'wamid.outbound.'.$counter],
                ],
            ], 200);
        });

        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_phone_number_id' => '1234567890',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
            'whatsapp_welcome_text' => 'Hi! Choose Visit Store, Orders, or Contact.',
        ]);

        $payload = [
            'entry' => [[
                'id' => 'entry-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '1234567890',
                        ],
                        'contacts' => [[
                            'profile' => [
                                'name' => 'Riya Sharma',
                            ],
                        ]],
                        'messages' => [[
                            'id' => 'wamid.inbound.1',
                            'from' => '15551234567',
                            'type' => 'text',
                            'text' => [
                                'body' => 'Hi',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response->assertOk()->assertJson(['status' => 'accepted']);

        Http::assertSent(function (Request $request) {
            $buttons = collect($request->data()['interactive']['action']['buttons'] ?? [])
                ->pluck('reply.title')
                ->all();

            return ($request->data()['type'] ?? null) === 'interactive'
                && ($request->data()['interactive']['type'] ?? null) === 'button'
                && ($request->data()['interactive']['body']['text'] ?? null) === 'Hi! Choose Visit Store, Orders, or Contact.'
                && $buttons === ['Visit Store', 'Orders', 'Contact'];
        });

        $this->assertDatabaseHas('messages', [
            'direction' => 'outbound',
            'type' => 'interactive',
            'body' => "AlphaHarvest Store\nHi! Choose Visit Store, Orders, or Contact.\nChoose an option to continue.",
        ]);
    }

    public function test_visit_store_and_add_to_cart_flow_uses_native_catalog_storefront_when_configured(): void
    {
        $counter = 0;

        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.base_url' => 'https://graph.facebook.com/v20.0',
        ]);

        Http::fake(function () use (&$counter) {
            $counter++;

            return Http::response([
                'messages' => [
                    ['id' => 'wamid.outbound.'.$counter],
                ],
            ], 200);
        });

        $tenant = Tenant::factory()->create();

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_phone_number_id' => '1234567890',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
            'whatsapp_store_intro' => 'Browse our featured wellness products below.',
            'meta_catalog_id' => '5566778899',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'slug' => 'wellness',
        ]);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Morning Lift Coffee',
            'slug' => 'morning-lift-coffee',
            'sku' => 'COF-250',
            'meta_retailer_id' => 'catalog-COF-250',
            'price' => 18.50,
            'inventory_quantity' => 10,
        ]);

        $visitStorePayload = [
            'entry' => [[
                'id' => 'entry-visit-store',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '1234567890',
                        ],
                        'contacts' => [[
                            'profile' => [
                                'name' => 'Riya Sharma',
                            ],
                        ]],
                        'messages' => [[
                            'id' => 'wamid.inbound.visit-store',
                            'from' => '15551234567',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'button_reply',
                                'button_reply' => [
                                    'id' => 'visit_store',
                                    'title' => 'Visit Store',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $visitStorePayload)->assertOk();

        Http::assertSent(function (Request $request) {
            $sections = $request->data()['interactive']['action']['sections'] ?? [];
            $retailerIds = collect($sections)
                ->flatMap(fn ($section) => $section['product_items'] ?? [])
                ->pluck('product_retailer_id')
                ->all();

            return ($request->data()['interactive']['type'] ?? null) === 'product_list'
                && ($request->data()['interactive']['action']['catalog_id'] ?? null) === '5566778899'
                && str_contains($request->data()['interactive']['body']['text'] ?? '', 'Browse our featured wellness products below.')
                && in_array('catalog-COF-250', $retailerIds, true);
        });

        $addToCartPayload = [
            'entry' => [[
                'id' => 'entry-add-cart',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '1234567890',
                        ],
                        'contacts' => [[
                            'profile' => [
                                'name' => 'Riya Sharma',
                            ],
                        ]],
                        'messages' => [[
                            'id' => 'wamid.inbound.add-to-cart',
                            'from' => '15551234567',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'button_reply',
                                'button_reply' => [
                                    'id' => 'add_to_cart:'.$product->id,
                                    'title' => 'Add to Cart',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $addToCartPayload)->assertOk();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::assertSent(function (Request $request) {
            $buttons = collect($request->data()['interactive']['action']['buttons'] ?? [])
                ->pluck('reply.title')
                ->all();

            return str_contains($request->data()['interactive']['body']['text'] ?? '', 'Added to cart.')
                && $buttons === ['Checkout', 'Visit Store', 'Orders'];
        });
    }
}
