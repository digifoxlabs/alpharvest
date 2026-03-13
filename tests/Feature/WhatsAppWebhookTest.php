<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_whatsapp_message_creates_customer_cart_and_outbound_reply(): void
    {
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.base_url' => 'https://graph.facebook.com/v20.0',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.outbound.1'],
                ],
            ], 200),
        ]);

        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_phone_number_id' => '1234567890',
            'slug' => 'chat-store',
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
                                'body' => 'ADD COF-250 2',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('customers', [
            'store_id' => $store->id,
            'phone' => '15551234567',
            'name' => 'Riya Sharma',
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('messages', [
            'direction' => 'outbound',
            'body' => "Morning Lift Coffee added to your cart.\nQty: 2\nCart total: USD 37.00\nReply CART or CHECKOUT.",
        ]);
    }
}
