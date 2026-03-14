<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
