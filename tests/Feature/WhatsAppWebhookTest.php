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

    public function test_visit_store_uses_multi_product_catalog_message_when_catalog_mapping_is_configured(): void
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
            'name' => 'Wellness',
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

        $request = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->first(fn (Request $request) => ($request->data()['interactive']['type'] ?? null) === 'product_list');

        $this->assertNotNull($request);

        $sections = $request->data()['interactive']['action']['sections'] ?? [];
        $retailerIds = collect($sections)
            ->flatMap(fn (array $section) => $section['product_items'] ?? [])
            ->pluck('product_retailer_id')
            ->all();
        $sectionTitles = collect($sections)->pluck('title')->all();

        $this->assertSame('5566778899', $request->data()['interactive']['action']['catalog_id'] ?? null);
        $this->assertStringContainsString('Browse our featured wellness products below.', $request->data()['interactive']['body']['text'] ?? '');
        $this->assertContains('catalog-COF-250', $retailerIds);
        $this->assertContains('Wellness', $sectionTitles);
        $this->assertContains('See All', $sectionTitles);
        $this->assertSame('individual', $request->data()['recipient_type'] ?? null);
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
            'meta_retailer_id' => null,
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
                && $buttons === ['Browse More', 'View Cart', 'Checkout'];
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

        $cartMessage = \App\Models\Message::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', "%Your cart:%")
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('2 x Morning Lift Coffee (COF-250) = USD 37.00', $cartMessage->body);
        $this->assertStringContainsString('1 x Evening Calm Tea (TEA-180) = USD 14.00', $cartMessage->body);
        $this->assertStringContainsString('Total: USD 51.00', $cartMessage->body);
        $this->assertStringContainsString('Deliver to pincode: not saved yet.', $cartMessage->body);

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

    public function test_checkout_requires_saved_address_then_creates_order_with_delivery_details(): void
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
            'price' => 18.50,
            'inventory_quantity' => 10,
        ]);

        $addPayload = [
            'entry' => [[
                'id' => 'entry-add',
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
                            'id' => 'wamid.inbound.add',
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

        $checkoutPayload = [
            'entry' => [[
                'id' => 'entry-checkout',
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
                            'id' => 'wamid.inbound.checkout',
                            'from' => '15551234567',
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'button_reply',
                                'button_reply' => [
                                    'id' => 'checkout',
                                    'title' => 'Checkout',
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $addressPayload = [
            'entry' => [[
                'id' => 'entry-address',
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
                            'id' => 'wamid.inbound.address',
                            'from' => '15551234567',
                            'type' => 'text',
                            'text' => [
                                'body' => "700001\nKolkata\n221B Market Road\nNear Central Metro",
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $addPayload)->assertOk();
        $this->postJson('/api/whatsapp/webhook', $checkoutPayload)->assertOk();

        $this->assertDatabaseHas('messages', [
            'direction' => 'outbound',
            'body' => "AlphaHarvest Store\nSave delivery details before checkout.\n\nSend your delivery details in this format:\n\n700001\nKolkata\n221B Market Road\nNear Central Metro\nPincode line 1, city line 2.",
        ]);

        $this->postJson('/api/whatsapp/webhook', $addressPayload)->assertOk();

        $this->assertDatabaseHas('customers', [
            'phone' => '15551234567',
        ]);

        $customer = \App\Models\Customer::query()->where('phone', '15551234567')->firstOrFail();

        $this->assertSame('700001', data_get($customer->metadata, 'delivery.pincode'));
        $this->assertSame('Kolkata', data_get($customer->metadata, 'delivery.city'));
        $this->assertSame("221B Market Road\nNear Central Metro", data_get($customer->metadata, 'delivery.address'));

        $order = \App\Models\Order::query()->latest('id')->firstOrFail();

        $this->assertSame('700001', data_get($order->metadata, 'delivery.pincode'));
        $this->assertSame('Kolkata', data_get($order->metadata, 'delivery.city'));
        $this->assertSame("221B Market Road\nNear Central Metro", data_get($order->metadata, 'delivery.address'));
        $this->assertSame('unpaid', $order->payment_status);

        $this->assertDatabaseHas('messages', [
            'direction' => 'outbound',
            'body' => "CHAT-STORE-00001\nDelivery details saved.\nDeliver to pincode: 700001\nCity: Kolkata\nAddress: 221B Market Road\nNear Central Metro\nOrder created: CHAT-STORE-00001\nOur store team will send your payment link shortly.\nAddress saved for this order.",
        ]);
    }

    public function test_catalog_order_payload_creates_an_order_for_admin_follow_up(): void
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
            'meta_catalog_id' => '5566778899',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Morning Lift Coffee',
            'slug' => 'morning-lift-coffee',
            'sku' => 'COF-250',
            'meta_retailer_id' => 'COF-250',
            'price' => 18.50,
            'inventory_quantity' => 10,
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Focus Shot',
            'slug' => 'focus-shot',
            'sku' => 'FOC-100',
            'meta_retailer_id' => 'FOC-100',
            'price' => 12.00,
            'inventory_quantity' => 10,
        ]);

        $orderPayload = [
            'entry' => [[
                'id' => 'entry-native-cart',
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
                            'id' => 'wamid.inbound.native-cart',
                            'from' => '15551234567',
                            'type' => 'order',
                            'order' => [
                                'catalog_id' => '5566778899',
                                'product_items' => [
                                    [
                                        'product_retailer_id' => 'COF-250',
                                        'quantity' => '2',
                                    ],
                                    [
                                        'product_retailer_id' => 'FOC-100',
                                        'quantity' => '1',
                                    ],
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $orderPayload)->assertOk();

        $this->assertDatabaseHas('cart_items', [
            'quantity' => 2,
            'unit_price' => 18.50,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'quantity' => 1,
            'unit_price' => 12.00,
        ]);

        $order = \App\Models\Order::query()->latest('id')->firstOrFail();

        $this->assertSame('awaiting_address', $order->status);
        $this->assertSame('unpaid', $order->payment_status);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => 'Morning Lift Coffee',
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => 'Focus Shot',
            'quantity' => 1,
        ]);

        $confirmationMessage = \App\Models\Message::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', '%Your order has been placed successfully.%')
            ->latest('id')
            ->firstOrFail();

        $addressPromptMessage = \App\Models\Message::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', '%Please share your delivery address for order%')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('Please confirm the delivery address for this order.', $confirmationMessage->body);
        $this->assertStringContainsString('USD 49.00', $confirmationMessage->body);
        $this->assertStringContainsString("700001\nKolkata\n221B Market Road\nNear Central Metro", $addressPromptMessage->body);
        $this->assertDatabaseHas('conversations', [
            'store_id' => $store->id,
            'customer_id' => \App\Models\Customer::query()->where('phone', '15551234567')->value('id'),
        ]);
    }

    public function test_repeat_catalog_order_shows_saved_addresses_and_accepts_numeric_selection(): void
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
            'meta_catalog_id' => '5566778899',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
            'settings' => [
                'delivery_zones' => [
                    ['pincode' => '700001', 'city' => 'Kolkata'],
                    ['pincode' => '700002', 'city' => 'Howrah'],
                ],
            ],
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Morning Lift Coffee',
            'slug' => 'morning-lift-coffee',
            'sku' => 'COF-250',
            'meta_retailer_id' => 'COF-250',
            'price' => 18.50,
            'inventory_quantity' => 10,
        ]);

        $customer = \App\Models\Customer::factory()->create([
            'store_id' => $store->id,
            'phone' => '15551234567',
            'metadata' => [
                'delivery' => [
                    'id' => 'addr-1',
                    'pincode' => '700001',
                    'city' => 'Kolkata',
                    'address' => "221B Market Road\nNear Central Metro",
                    'saved_at' => now()->subDay()->toIso8601String(),
                    'address_book' => [
                        [
                            'id' => 'addr-1',
                            'pincode' => '700001',
                            'city' => 'Kolkata',
                            'address' => "221B Market Road\nNear Central Metro",
                            'saved_at' => now()->subDay()->toIso8601String(),
                        ],
                        [
                            'id' => 'addr-2',
                            'pincode' => '700002',
                            'city' => 'Howrah',
                            'address' => "88 River Road\nOpposite Ferry Gate",
                            'saved_at' => now()->subDays(2)->toIso8601String(),
                        ],
                    ],
                ],
            ],
        ]);

        $orderPayload = [
            'entry' => [[
                'id' => 'entry-native-cart-repeat',
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
                            'id' => 'wamid.inbound.native-cart-repeat',
                            'from' => $customer->phone,
                            'type' => 'order',
                            'order' => [
                                'catalog_id' => '5566778899',
                                'product_items' => [[
                                    'product_retailer_id' => 'COF-250',
                                    'quantity' => '1',
                                ]],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $orderPayload)->assertOk();

        $listRequest = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->first(fn (Request $request) => ($request->data()['interactive']['type'] ?? null) === 'list');

        $this->assertNotNull($listRequest);
        $rows = data_get($listRequest->data(), 'interactive.action.sections.0.rows', []);
        $rowIds = collect($rows)->pluck('id')->all();

        $this->assertContains('select_address:addr-1', $rowIds);
        $this->assertContains('select_address:addr-2', $rowIds);
        $this->assertContains('new_address', $rowIds);

        $choicePayload = [
            'entry' => [[
                'id' => 'entry-address-choice',
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
                            'id' => 'wamid.inbound.address-choice',
                            'from' => $customer->phone,
                            'type' => 'text',
                            'text' => [
                                'body' => '2',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $choicePayload)->assertOk();

        $order = \App\Models\Order::query()->latest('id')->firstOrFail();
        $this->assertSame('700002', data_get($order->metadata, 'delivery.pincode'));
        $this->assertSame('Howrah', data_get($order->metadata, 'delivery.city'));
        $this->assertSame("88 River Road\nOpposite Ferry Gate", data_get($order->metadata, 'delivery.address'));
    }

    public function test_undeliverable_address_sends_custom_store_message_and_keeps_order_waiting(): void
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
            'meta_catalog_id' => '5566778899',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
            'settings' => [
                'delivery_zones' => [
                    ['pincode' => '700001', 'city' => 'Kolkata'],
                ],
                'undeliverable_message' => 'Sorry, this area is outside our delivery service zone.',
            ],
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Morning Lift Coffee',
            'slug' => 'morning-lift-coffee',
            'sku' => 'COF-250',
            'meta_retailer_id' => 'COF-250',
            'price' => 18.50,
            'inventory_quantity' => 10,
        ]);

        $orderPayload = [
            'entry' => [[
                'id' => 'entry-native-cart-undeliverable',
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
                            'id' => 'wamid.inbound.native-cart-undeliverable',
                            'from' => '15551234567',
                            'type' => 'order',
                            'order' => [
                                'catalog_id' => '5566778899',
                                'product_items' => [[
                                    'product_retailer_id' => 'COF-250',
                                    'quantity' => '1',
                                ]],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $orderPayload)->assertOk();

        $addressPayload = [
            'entry' => [[
                'id' => 'entry-undeliverable-address',
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
                            'id' => 'wamid.inbound.undeliverable-address',
                            'from' => '15551234567',
                            'type' => 'text',
                            'text' => [
                                'body' => "500001\nHyderabad\n88 Market Street\nNear Clock Tower",
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $addressPayload)->assertOk();

        $order = \App\Models\Order::query()->latest('id')->firstOrFail();
        $this->assertSame('awaiting_address', $order->status);
        $this->assertNull(data_get($order->metadata, 'delivery.pincode'));

        $this->assertDatabaseHas('messages', [
            'direction' => 'outbound',
            'body' => "AlphaHarvest Store\nSorry, this area is outside our delivery service zone.\nOutside delivery area.",
        ]);
    }

    public function test_view_cart_waits_for_native_catalog_sync_instead_of_showing_a_stale_fallback_cart(): void
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
            'meta_catalog_id' => '5566778899',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
        ]);

        $fallbackProduct = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Fallback Product',
            'slug' => 'fallback-product',
            'sku' => 'FBK-001',
            'price' => 10.00,
            'inventory_quantity' => 10,
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Catalog Product',
            'slug' => 'catalog-product',
            'sku' => 'CAT-001',
            'meta_retailer_id' => 'CAT-001',
            'price' => 15.00,
            'inventory_quantity' => 10,
        ]);

        $sendPayload = function (string $messageId, array $message) {
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
                            'messages' => [$message],
                        ],
                    ]],
                ]],
            ];
        };

        $this->postJson('/api/whatsapp/webhook', $sendPayload('add-fallback', [
            'id' => 'wamid.inbound.add-fallback',
            'from' => '15551234567',
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button_reply',
                'button_reply' => [
                    'id' => 'add_to_cart:'.$fallbackProduct->id,
                    'title' => 'Add to Cart',
                ],
            ],
        ]))->assertOk();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $fallbackProduct->id,
            'quantity' => 1,
        ]);

        $this->postJson('/api/whatsapp/webhook', $sendPayload('visit-store', [
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
        ]))->assertOk();

        $this->postJson('/api/whatsapp/webhook', $sendPayload('view-cart', [
            'id' => 'wamid.inbound.view-cart',
            'from' => '15551234567',
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button_reply',
                'button_reply' => [
                    'id' => 'view_cart',
                    'title' => 'View Cart',
                ],
            ],
        ]))->assertOk();

        $pendingMessage = \App\Models\Message::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', '%Waiting for catalog cart sync.%')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('Your catalog selections have not been synced into the bot cart yet.', $pendingMessage->body);
        $this->assertStringNotContainsString('Fallback Product', $pendingMessage->body);
    }

    public function test_catalog_order_sync_replaces_stale_fallback_cart_items(): void
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
            'meta_catalog_id' => '5566778899',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
        ]);

        $fallbackProduct = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Fallback Product',
            'slug' => 'fallback-product',
            'sku' => 'FBK-001',
            'price' => 10.00,
            'inventory_quantity' => 10,
        ]);

        $catalogProduct = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Catalog Product',
            'slug' => 'catalog-product',
            'sku' => 'CAT-001',
            'meta_retailer_id' => 'CAT-001',
            'price' => 15.00,
            'inventory_quantity' => 10,
        ]);

        $sendPayload = function (string $messageId, array $message) {
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
                            'messages' => [$message],
                        ],
                    ]],
                ]],
            ];
        };

        $this->postJson('/api/whatsapp/webhook', $sendPayload('add-fallback', [
            'id' => 'wamid.inbound.add-fallback',
            'from' => '15551234567',
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button_reply',
                'button_reply' => [
                    'id' => 'add_to_cart:'.$fallbackProduct->id,
                    'title' => 'Add to Cart',
                ],
            ],
        ]))->assertOk();

        $this->postJson('/api/whatsapp/webhook', $sendPayload('native-cart', [
            'id' => 'wamid.inbound.native-cart',
            'from' => '15551234567',
            'type' => 'order',
            'order' => [
                'catalog_id' => '5566778899',
                'product_items' => [
                    [
                        'product_retailer_id' => 'CAT-001',
                        'quantity' => '2',
                    ],
                ],
            ],
        ]))->assertOk();

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $fallbackProduct->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $catalogProduct->id,
            'quantity' => 2,
        ]);

        $confirmationMessage = \App\Models\Message::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', '%Your order has been placed successfully.%')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('USD 30.00', $confirmationMessage->body);
        $this->assertStringContainsString('Please confirm the delivery address for this order.', $confirmationMessage->body);
        $this->assertStringNotContainsString('Fallback Product', $confirmationMessage->body);
    }
}
