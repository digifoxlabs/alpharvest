<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentLinkService
{
    public function createOrReuse(Order $order): Payment
    {
        $payment = $order->payments()
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($payment) {
            return $payment;
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => config('services.payments.default_provider', 'manual_link'),
            'reference' => $this->nextReference(),
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        $payment->forceFill([
            'payment_url' => route('payments.show', $payment),
        ])->save();

        return $payment->fresh();
    }

    public function markPaid(Payment $payment, array $payload = []): Payment
    {
        return DB::transaction(function () use ($payment, $payload) {
            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'payload' => array_merge($payment->payload ?? [], $payload),
            ])->save();

            $payment->order->forceFill([
                'status' => 'paid',
                'payment_status' => 'paid',
                'paid_at' => now(),
            ])->save();

            return $payment->fresh('order');
        });
    }

    protected function nextReference(): string
    {
        return 'PAY-'.Str::upper(Str::random(10));
    }
}
