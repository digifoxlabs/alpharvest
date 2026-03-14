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

    public function test_visit_store_uses_native_catalog_message_when_configured(): void
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
            return ($request->data()['interactive']['type'] ?? null) === 'catalog_message'
                && ($request->data()['interactive']['action']['name'] ?? null) === 'catalog_message'
                && str_contains($request->data()['interactive']['body']['text'] ?? '', 'Browse our featured wellness products below.')
                && ($request->data()['recipient_type'] ?? null) === 'individual';
        });
    }

    public function test_visit_store_catalog_message_trims_oversized_intro_and_footer(): void
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
            'whatsapp_brand_name' => str_repeat('AlphaHarvest ', 8),
            'whatsapp_store_intro' => str_repeat('This is a very long storefront intro message. ', 40),
            'meta_catalog_id' => '5566778899',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'name' => 'Catalog Ready Product',
            'slug' => 'catalog-ready-product',
            'sku' => 'CAT-001',
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

        $request = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->first(fn (Request $request) => str_contains($request->url(), '/messages'));

        $this->assertNotNull($request);
        $body = $request->data()['interactive']['body']['text'] ?? '';
        $footer = $request->data()['interactive']['footer']['text'] ?? '';

        $this->assertSame('catalog_message', $request->data()['interactive']['type'] ?? null);
        $this->assertLessThanOrEqual(1024, strlen($body));
        $this->assertLessThanOrEqual(60, strlen($footer));
    }

    public function test_add_to_cart_button_flow_updates_cart(): void
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
                && $buttons === ['Browse More', 'Checkout', 'Clear Cart'];
        });
    }

    public function test_cart_persists_across_conversations_and_can_be_cleared(): void
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
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'slug' => 'wellness',
        ]);

        $productA = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Morning Lift Coffee',
            'slug' => 'morning-lift-coffee',
            'sku' => 'COF-250',
            'price' => 18.50,
            'inventory_quantity' => 10,
        ]);

        $productB = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Evening Calm Tea',
            'slug' => 'evening-calm-tea',
            'sku' => 'TEA-180',
            'price' => 14.00,
            'inventory_quantity' => 10,
        ]);

        $sendPayload = function (string $messageId, string $buttonId, string $title) {
            return [
                'entry' => [[
                    'id' => 'entry-'.$messageId,
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
                                'id' => $messageId,
                                'from' => '15551234567',
                                'type' => 'interactive',
                                'interactive' => [
                                    'type' => 'button_reply',
                                    'button_reply' => [
                                        'id' => $buttonId,
                                        'title' => $title,
                                    ],
                                ],
                            ]],
                        ],
                    ]],
                ]],
            ];
        };

        $this->postJson('/api/whatsapp/webhook', $sendPayload('wamid.inbound.add-a-1', 'add_to_cart:'.$productA->id, 'Add to Cart'))->assertOk();
        $this->postJson('/api/whatsapp/webhook', $sendPayload('wamid.inbound.add-a-2', 'add_to_cart:'.$productA->id, 'Add to Cart'))->assertOk();
        $this->postJson('/api/whatsapp/webhook', $sendPayload('wamid.inbound.add-b-1', 'add_to_cart:'.$productB->id, 'Add to Cart'))->assertOk();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $productA->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $productB->id,
            'quantity' => 1,
        ]);

        $conversation = \App\Models\Conversation::query()->latest('id')->firstOrFail();
        $conversation->update([
            'status' => 'closed',
        ]);

        $ordersPayload = [
            'entry' => [[
                'id' => 'entry-orders',
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
                            'id' => 'wamid.inbound.orders',
                            'from' => '15551234567',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'button_reply',
                                'button_reply' => [
                                    'id' => 'orders',
                                    'title' => 'Orders',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $ordersPayload)->assertOk();

        $this->assertDatabaseHas('messages', [
            'direction' => 'outbound',
            'body' => "AlphaHarvest Store\nYour cart:\n2 x Morning Lift Coffee (COF-250) = USD 37.00\n1 x Evening Calm Tea (TEA-180) = USD 14.00\n\nTotal: USD 51.00\nChoose Checkout when you are ready.\nOrder details inside WhatsApp.",
        ]);

        $this->postJson('/api/whatsapp/webhook', $sendPayload('wamid.inbound.clear-cart', 'clear_cart', 'Clear Cart'))->assertOk();

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $productA->id,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $productB->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'direction' => 'outbound',
            'body' => "AlphaHarvest Store\nYour cart has been cleared.\nTap Visit Store to start a new basket.\nCart updated.",
        ]);
    }

    public function test_status_webhooks_update_outbound_message_delivery_state(): void
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
        Store::factory()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_phone_number_id' => '1234567890',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
        ]);

        $hiPayload = [
            'entry' => [[
                'id' => 'entry-hi',
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

        $this->postJson('/api/whatsapp/webhook', $hiPayload)->assertOk();

        $statusPayload = [
            'entry' => [[
                'id' => 'entry-status',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '1234567890',
                        ],
                        'statuses' => [[
                            'id' => 'wamid.outbound.1',
                            'status' => 'read',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $statusPayload)
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('messages', [
            'whatsapp_message_id' => 'wamid.outbound.1',
        ]);

        $message = \App\Models\Message::query()
            ->where('whatsapp_message_id', 'wamid.outbound.1')
            ->firstOrFail();

        $this->assertNotNull($message->read_at);
        $this->assertNotNull($message->delivered_at);
        $this->assertSame('read', data_get($message->payload, 'status_update.status'));
    }
}
