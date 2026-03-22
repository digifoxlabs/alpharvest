<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_dashboard_shows_customer_address_products_and_payment_status(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'northwind-commerce',
            'name' => 'Northwind Commerce',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
        ]);

        $customer = Customer::factory()->create([
            'store_id' => $store->id,
            'name' => 'Riya Sharma',
            'phone' => '15551234567',
            'metadata' => [
                'delivery' => [
                    'pincode' => '700001',
                    'city' => 'Kolkata',
                    'address' => "221B Market Road\nKolkata",
                ],
            ],
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => 'NORTHWIND-00001',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 51.00,
            'total' => 51.00,
            'metadata' => [
                'delivery' => [
                    'pincode' => '700001',
                    'city' => 'Kolkata',
                    'address' => "221B Market Road\nKolkata",
                ],
            ],
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Morning Lift Coffee',
            'sku' => 'COF-250',
            'quantity' => 2,
            'unit_price' => 18.50,
            'total_price' => 37.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Evening Calm Tea',
            'sku' => 'TEA-180',
            'quantity' => 1,
            'unit_price' => 14.00,
            'total_price' => 14.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'provider' => 'manual_link',
            'reference' => 'PAY-1234567890',
            'status' => 'pending',
            'amount' => 51.00,
            'currency' => 'USD',
            'payment_url' => 'https://example.test/pay/PAY-1234567890',
        ]);

        $this->get(route('dashboard.show', $tenant))
            ->assertOk()
            ->assertSee('Riya Sharma')
            ->assertSee('700001')
            ->assertSee('221B Market Road')
            ->assertSee('Kolkata')
            ->assertSee('2 x Morning Lift Coffee')
            ->assertSee('1 x Evening Calm Tea')
            ->assertSee('Unpaid')
            ->assertSee('Request Address')
            ->assertSee('Send Payment Link')
            ->assertSee('Update Status');
    }

    public function test_tenant_dashboard_can_request_address_and_send_payment_link(): void
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

        $tenant = Tenant::factory()->create([
            'slug' => 'northwind-commerce',
            'name' => 'Northwind Commerce',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
            'whatsapp_phone_number_id' => '1234567890',
        ]);

        $customer = Customer::factory()->create([
            'store_id' => $store->id,
            'name' => 'Riya Sharma',
            'phone' => '15551234567',
        ]);

        $conversation = Conversation::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'source' => 'whatsapp',
            'last_message_at' => now(),
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'order_number' => 'NORTHWIND-00001',
            'status' => 'awaiting_address',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 51.00,
            'total' => 51.00,
            'placed_at' => now(),
        ]);

        $this->post(route('dashboard.orders.request-address', [$tenant, $order]))
            ->assertRedirect(route('dashboard.show', $tenant));

        $conversation->refresh();
        $order->refresh();

        $this->assertTrue((bool) data_get($conversation->context, 'awaiting_address'));
        $this->assertSame($order->id, data_get($conversation->context, 'awaiting_order_id'));
        $this->assertSame('awaiting_address', $order->status);
        $this->assertNotNull(data_get($order->metadata, 'admin_follow_up.address_requested_at'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => "Please reply with your delivery address for order {$order->order_number}.\nSend your 6-digit pincode on line 1, city on line 2, and the full address below it.",
        ]);

        $this->post(route('dashboard.orders.send-payment-link', [$tenant, $order]))
            ->assertRedirect(route('dashboard.show', $tenant));

        $order->refresh();

        $this->assertSame('payment_requested', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNotNull(data_get($order->metadata, 'admin_follow_up.payment_link_sent_at'));

        $payment = Payment::query()->where('order_id', $order->id)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => "Payment for order {$order->order_number}\nAmount due: 51.00 USD\nSecure payment link: {$payment->payment_url}",
        ]);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains(data_get($request->data(), 'text.body', ''), $order->order_number));
    }

    public function test_tenant_dashboard_can_update_order_and_payment_status(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'northwind-commerce',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $customer = Customer::factory()->create([
            'store_id' => $store->id,
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => 'NORTHWIND-00001',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 51.00,
            'total' => 51.00,
            'placed_at' => now(),
        ]);

        $this->post(route('dashboard.orders.update-status', [$tenant, $order]), [
            'status' => 'completed',
            'payment_status' => 'paid',
        ])->assertRedirect(route('dashboard.show', $tenant));

        $order->refresh();

        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_customer_address_reply_after_dashboard_request_is_saved_on_that_order(): void
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

        $tenant = Tenant::factory()->create([
            'slug' => 'northwind-commerce',
            'name' => 'Northwind Commerce',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
            'whatsapp_phone_number_id' => '1234567890',
            'whatsapp_brand_name' => 'Northwind Wellness',
        ]);

        $customer = Customer::factory()->create([
            'store_id' => $store->id,
            'name' => 'Riya Sharma',
            'phone' => '15551234567',
        ]);

        $conversation = Conversation::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'source' => 'whatsapp',
            'last_message_at' => now(),
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'order_number' => 'NORTHWIND-00001',
            'status' => 'awaiting_address',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 51.00,
            'total' => 51.00,
            'placed_at' => now(),
        ]);

        $this->post(route('dashboard.orders.request-address', [$tenant, $order]))
            ->assertRedirect(route('dashboard.show', $tenant));

        $payload = [
            'entry' => [[
                'id' => 'entry-address-reply',
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
                            'id' => 'wamid.inbound.address-reply',
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

        $this->postJson('/api/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $conversation->refresh();
        $order->refresh();

        $this->assertFalse((bool) data_get($conversation->context, 'awaiting_address'));
        $this->assertNull(data_get($conversation->context, 'awaiting_order_id'));
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('700001', data_get($order->metadata, 'delivery.pincode'));
        $this->assertSame('Kolkata', data_get($order->metadata, 'delivery.city'));
        $this->assertSame("221B Market Road\nNear Central Metro", data_get($order->metadata, 'delivery.address'));
        $this->assertNotNull(data_get($order->metadata, 'admin_follow_up.address_received_at'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => "NORTHWIND-00001\nDelivery details saved.\nDeliver to pincode: 700001\nCity: Kolkata\nAddress: 221B Market Road\nNear Central Metro\nOur store team will send your payment link shortly.\nAddress saved for this order.",
        ]);
    }
    public function test_tenant_sidebar_shows_store_category_and_product_links(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'northwind-commerce',
            'name' => 'Northwind Commerce',
        ]);

        Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
        ]);

        $this->get(route('dashboard.show', $tenant))
            ->assertOk()
            ->assertSee('Stores')
            ->assertSee('Categories')
            ->assertSee('Products')
            ->assertSee('Manage stores')
            ->assertSee('Manage categories')
            ->assertSee('Manage products');
    }

    public function test_tenant_can_manage_only_its_stores_categories_and_products(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'northwind-commerce',
            'name' => 'Northwind Commerce',
            'currency' => 'USD',
        ]);

        $otherTenant = Tenant::factory()->create([
            'slug' => 'southwind-commerce',
            'name' => 'Southwind Commerce',
            'currency' => 'USD',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
            'slug' => 'northwind-wellness',
            'currency' => 'USD',
        ]);

        $otherStore = Store::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Southwind Wellness',
            'slug' => 'southwind-wellness',
            'currency' => 'USD',
        ]);

        $category = ProductCategory::factory()->create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
        ]);

        Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Focus Shot',
            'slug' => 'focus-shot',
            'sku' => 'FOC-001',
        ]);

        ProductCategory::factory()->create([
            'store_id' => $otherStore->id,
            'name' => 'Tea',
            'slug' => 'tea',
        ]);

        Product::factory()->create([
            'store_id' => $otherStore->id,
            'name' => 'Southwind Tea',
            'slug' => 'southwind-tea',
            'sku' => 'SWT-001',
        ]);

        $this->get(route('dashboard.stores.index', $tenant))
            ->assertOk()
            ->assertSee('Northwind Wellness')
            ->assertDontSee('Southwind Wellness');

        $this->get(route('dashboard.categories.index', $tenant))
            ->assertOk()
            ->assertSee('Wellness')
            ->assertDontSee('Southwind Wellness');

        $this->get(route('dashboard.products.index', $tenant))
            ->assertOk()
            ->assertSee('Focus Shot')
            ->assertDontSee('Southwind Tea')
            ->assertSee('Create new product')
            ->assertSee('data-category-store', false)
            ->assertSee('data-category-target', false)
            ->assertSee('data-store-id="'.$category->store_id.'"', false);

        $this->get(route('dashboard.stores.create', $tenant))
            ->assertOk()
            ->assertSee('New store')
            ->assertSee('Store name');

        $this->get(route('dashboard.products.create', $tenant))
            ->assertOk()
            ->assertSee('New product')
            ->assertSee('Create or manage stores')
            ->assertSee('Create or manage categories')
            ->assertSee('Product name');

        $this->post(route('dashboard.stores.store', $tenant), [
            'name' => 'Northwind Express',
            'slug' => 'northwind-express',
            'currency' => 'usd',
            'description' => 'Second storefront',
            'is_active' => '1',
        ])->assertRedirect(route('dashboard.stores.index', $tenant));

        $createdStore = Store::query()->where('slug', 'northwind-express')->firstOrFail();
        $this->assertSame($tenant->id, $createdStore->tenant_id);
        $this->assertSame('USD', $createdStore->currency);

        $this->post(route('dashboard.categories.store', $tenant), [
            'store_id' => $store->id,
            'name' => 'Boosters',
            'slug' => 'boosters',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('dashboard.categories.index', $tenant));

        $this->assertDatabaseHas('product_categories', [
            'store_id' => $store->id,
            'name' => 'Boosters',
            'slug' => 'boosters',
        ]);

        $this->post(route('dashboard.products.store', $tenant), [
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Calm Drops',
            'slug' => 'calm-drops',
            'sku' => 'CALM-001',
            'price' => '12.50',
            'inventory_quantity' => 15,
            'is_active' => '1',
        ])->assertRedirect(route('dashboard.products.index', $tenant));

        $this->assertDatabaseHas('products', [
            'store_id' => $store->id,
            'name' => 'Calm Drops',
            'slug' => 'calm-drops',
            'sku' => 'CALM-001',
        ]);

        $this->post(route('dashboard.categories.store', $tenant), [
            'store_id' => $otherStore->id,
            'name' => 'Leak Attempt',
            'slug' => 'leak-attempt',
            'sort_order' => 2,
        ])->assertSessionHasErrors('store_id');
    }
    public function test_tenant_inbox_and_orders_have_dedicated_paginated_pages(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'northwind-commerce',
            'name' => 'Northwind Commerce',
        ]);

        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Wellness',
        ]);

        for ($i = 1; $i <= 13; $i++) {
            $customer = Customer::factory()->create([
                'store_id' => $store->id,
                'name' => sprintf('Customer %02d', $i),
                'phone' => '1555000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]);

            Conversation::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'status' => 'open',
                'source' => 'whatsapp',
                'last_message_at' => now()->subMinutes($i),
            ]);

            Order::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'order_number' => sprintf('NORTHWIND-%05d', $i),
                'status' => 'pending_payment',
                'payment_status' => 'unpaid',
                'currency' => 'USD',
                'subtotal' => 20,
                'total' => 20,
                'placed_at' => now()->subMinutes($i),
            ]);
        }

        $this->get(route('dashboard.inbox', $tenant))
            ->assertOk()
            ->assertSee('Tenant inbox')
            ->assertSee('Showing 1 to 12 of 13 results')
            ->assertSee('Customer 01')
            ->assertDontSee('Customer 13');

        $this->get(route('dashboard.inbox', [$tenant, 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 13 to 13 of 13 results')
            ->assertSee('Customer 13');

        $this->get(route('dashboard.orders', $tenant))
            ->assertOk()
            ->assertSee('Tenant orders')
            ->assertSee('Showing 1 to 10 of 13 results')
            ->assertSee('NORTHWIND-00001')
            ->assertDontSee('NORTHWIND-00013');

        $this->get(route('dashboard.orders', [$tenant, 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 11 to 13 of 13 results')
            ->assertSee('NORTHWIND-00013');
    }
}





