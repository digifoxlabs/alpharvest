<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function show(Payment $payment): View
    {
        return view('payments.checkout', [
            'payment' => $payment->load('order.store', 'order.customer'),
        ]);
    }

    public function confirm(Payment $payment, PaymentLinkService $payments): RedirectResponse
    {
        if ($payment->status !== 'paid') {
            $payments->markPaid($payment, [
                'confirmed_via' => 'checkout_page',
            ]);
        }

        return redirect()
            ->route('payments.show', $payment)
            ->with('status', 'Payment confirmed.');
    }
}
