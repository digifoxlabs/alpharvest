<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'metrics' => [
                'tenants' => Tenant::query()->count(),
                'stores' => Store::query()->count(),
                'categories' => ProductCategory::query()->count(),
                'products' => Product::query()->count(),
            ],
            'tenants' => Tenant::query()
                ->withCount('stores')
                ->latest('id')
                ->limit(6)
                ->get(),
            'stores' => Store::query()
                ->with('tenant')
                ->withCount(['categories', 'products', 'orders'])
                ->latest('id')
                ->limit(6)
                ->get(),
            'products' => Product::query()
                ->with(['store.tenant', 'category'])
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
