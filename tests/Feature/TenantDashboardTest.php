<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
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
            ->assertSee('Unpaid');
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
        $this->assertSame('awaiting_address', $order->status);
        $this->assertNotNull(data_get($order->metadata, 'admin_follow_up.address_requested_at'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => "Please reply with your delivery address for order {$order->order_number}.\nSend your 6-digit pincode on line 1 and the full address below it.",
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
}
