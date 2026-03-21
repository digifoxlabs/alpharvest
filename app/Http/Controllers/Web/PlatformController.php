<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\AgentInboxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function __construct(protected AgentInboxService $inbox)
    {
    }

    public function home(): View
    {
        return view('platform-home', [
            'tenants' => Tenant::query()->withCount('stores')->orderBy('name')->get(),
            'stores' => Store::query()
                ->with('tenant')
                ->withCount(['products', 'customers', 'conversations', 'orders'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function dashboard(Tenant $tenant): View
    {
        return view('dashboard', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
        ]);
    }

    public function inbox(Request $request, Tenant $tenant): View
    {
        $overview = $this->inbox->tenantOverview($tenant);
        $filters = [
            'search' => trim((string) $request->input('search')),
            'status' => (string) $request->input('status', ''),
            'store_id' => (string) $request->input('store_id', ''),
        ];

        return view('tenant.inbox', [
            'tenant' => $tenant,
            'overview' => $overview,
            'stores' => $overview['stores'],
            'filters' => $filters,
            'conversations' => $this->inbox->tenantConversationPage($tenant, $filters),
        ]);
    }

    public function orders(Request $request, Tenant $tenant): View
    {
        $overview = $this->inbox->tenantOverview($tenant);
        $filters = [
            'search' => trim((string) $request->input('search')),
            'status' => (string) $request->input('status', ''),
            'payment_status' => (string) $request->input('payment_status', ''),
            'store_id' => (string) $request->input('store_id', ''),
        ];

        return view('tenant.orders', [
            'tenant' => $tenant,
            'overview' => $overview,
            'stores' => $overview['stores'],
            'filters' => $filters,
            'orders' => $this->inbox->tenantOrderPage($tenant, $filters),
        ]);
    }

    public function catalog(Store $store): View
    {
        return view('catalog', [
            'store' => $store->load([
                'categories' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with([
                        'products' => fn ($products) => $products
                            ->where('is_active', true)
                            ->orderBy('name'),
                    ]),
            ]),
        ]);
    }

    public function product(Store $store, Product $product): View
    {
        abort_unless($product->store_id === $store->id, 404);

        return view('product', [
            'store' => $store,
            'product' => $product->loadMissing('category'),
        ]);
    }
}
