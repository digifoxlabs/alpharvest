<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\TenantOrderActionService;
use Illuminate\Http\RedirectResponse;

class TenantOrderController extends Controller
{
    public function __construct(protected TenantOrderActionService $orderActions)
    {
    }

    public function requestAddress(Tenant $tenant, Order $order): RedirectResponse
    {
        abort_unless($order->store && (int) $order->store->tenant_id === (int) $tenant->id, 404);

        $this->orderActions->requestAddress($order->loadMissing('store', 'customer', 'conversation'));

        return redirect()
            ->route('dashboard.show', $tenant)
            ->with('status', "Address request sent for {$order->order_number}.");
    }

    public function sendPaymentLink(Tenant $tenant, Order $order): RedirectResponse
    {
        abort_unless($order->store && (int) $order->store->tenant_id === (int) $tenant->id, 404);

        $this->orderActions->sendPaymentLink($order->loadMissing('store', 'customer', 'conversation'));

        return redirect()
            ->route('dashboard.show', $tenant)
            ->with('status', "Payment link sent for {$order->order_number}.");
    }
}
