<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_confirmation_marks_order_as_paid(): void
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create([
            'store_id' => $store->id,
            'phone' => '15550000001',
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-10001',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 49.99,
            'total' => 49.99,
            'placed_at' => now(),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'manual_link',
            'reference' => 'PAY-ORDER10001',
            'status' => 'pending',
            'amount' => 49.99,
            'currency' => 'USD',
        ]);

        $response = $this
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->post(route('payments.confirm', $payment));

        $response->assertRedirect(route('payments.show', $payment));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
            'payment_status' => 'paid',
        ]);
    }
}
