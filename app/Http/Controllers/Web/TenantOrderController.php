<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\TenantOrderActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantOrderController extends Controller
{
    protected const ORDER_STATUS_OPTIONS = [
        'awaiting_address',
        'pending_payment',
        'payment_requested',
        'processing',
        'completed',
        'cancelled',
    ];

    protected const PAYMENT_STATUS_OPTIONS = [
        'unpaid',
        'pending',
        'paid',
        'failed',
    ];

    public function __construct(protected TenantOrderActionService $orderActions)
    {
    }

    public function requestAddress(Request $request, Tenant $tenant, Order $order): RedirectResponse
    {
        abort_unless($order->store && (int) $order->store->tenant_id === (int) $tenant->id, 404);

        $this->orderActions->requestAddress($order->loadMissing('store', 'customer', 'conversation'));

        return $this->redirectToTenantPage($request, $tenant)
            ->with('status', "Address request sent for {$order->order_number}.");
    }

    public function sendPaymentLink(Request $request, Tenant $tenant, Order $order): RedirectResponse
    {
        abort_unless($order->store && (int) $order->store->tenant_id === (int) $tenant->id, 404);

        $this->orderActions->sendPaymentLink($order->loadMissing('store', 'customer', 'conversation'));

        return $this->redirectToTenantPage($request, $tenant)
            ->with('status', "Payment link sent for {$order->order_number}.");
    }

    public function updateStatus(Request $request, Tenant $tenant, Order $order): RedirectResponse
    {
        abort_unless($order->store && (int) $order->store->tenant_id === (int) $tenant->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::ORDER_STATUS_OPTIONS)],
            'payment_status' => ['required', 'in:'.implode(',', self::PAYMENT_STATUS_OPTIONS)],
        ]);

        $order->forceFill([
            'status' => $validated['status'],
            'payment_status' => $validated['payment_status'],
            'paid_at' => $validated['payment_status'] === 'paid' ? ($order->paid_at ?: now()) : ($validated['payment_status'] === 'unpaid' ? null : $order->paid_at),
        ])->save();

        return $this->redirectToTenantPage($request, $tenant)
            ->with('status', "Statuses updated for {$order->order_number}.");
    }

    protected function redirectToTenantPage(Request $request, Tenant $tenant): RedirectResponse
    {
        $routeName = (string) $request->input('redirect_route', 'dashboard.show');

        if (in_array($routeName, ['dashboard.show', 'dashboard.inbox', 'dashboard.orders'], true)) {
            return redirect()->route($routeName, $tenant);
        }

        return redirect()->route('dashboard.show', $tenant);
    }
}
