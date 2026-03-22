<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\AgentInboxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantCategoryController extends Controller
{
    public function __construct(protected AgentInboxService $inbox)
    {
    }

    public function index(Request $request, Tenant $tenant): View
    {
        $search = trim((string) $request->input('search'));
        $status = (string) $request->input('status', '');
        $storeId = (int) $request->input('store_id', 0);

        $storeIds = $tenant->stores()->pluck('id');

        $categoryQuery = ProductCategory::query()
            ->whereIn('store_id', $storeIds)
            ->with('store')
            ->withCount('products');

        if ($search !== '') {
            $categoryQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $categoryQuery->where('is_active', $status === 'active');
        }

        if ($storeId > 0) {
            $categoryQuery->where('store_id', $storeId);
        }

        $categories = $categoryQuery
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $stores = $tenant->stores()->orderBy('name')->get();

        return view('tenant.categories.index', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
            'categories' => $categories,
            'stores' => $stores,
            'stats' => [
                'total' => ProductCategory::query()->whereIn('store_id', $storeIds)->count(),
                'active' => ProductCategory::query()->whereIn('store_id', $storeIds)->where('is_active', true)->count(),
                'stores' => ProductCategory::query()->whereIn('store_id', $storeIds)->distinct('store_id')->count('store_id'),
                'filtered' => $categories->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'store_id' => $storeId > 0 ? (string) $storeId : '',
            ],
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validateCategory($request, $tenant);

        ProductCategory::create($validated);

        return redirect()
            ->route('dashboard.categories.index', $tenant)
            ->with('status', 'Category created.');
    }

    public function edit(Tenant $tenant, ProductCategory $category): View
    {
        $this->ensureTenantCategory($tenant, $category);

        return view('tenant.categories.edit', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
            'category' => $category->load('store'),
            'stores' => $tenant->stores()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Tenant $tenant, ProductCategory $category): RedirectResponse
    {
        $this->ensureTenantCategory($tenant, $category);

        $validated = $this->validateCategory($request, $tenant, $category);
        $category->update($validated);

        return redirect()
            ->route('dashboard.categories.index', $tenant)
            ->with('status', 'Category updated.');
    }

    public function destroy(Tenant $tenant, ProductCategory $category): RedirectResponse
    {
        $this->ensureTenantCategory($tenant, $category);

        $category->delete();

        return redirect()
            ->route('dashboard.categories.index', $tenant)
            ->with('status', 'Category deleted.');
    }

    protected function ensureTenantCategory(Tenant $tenant, ProductCategory $category): void
    {
        abort_unless($category->store()->where('tenant_id', $tenant->id)->exists(), 404);
    }

    protected function validateCategory(Request $request, Tenant $tenant, ?ProductCategory $category = null): array
    {
        $storeId = (int) $request->input('store_id');

        $validated = $request->validate([
            'store_id' => [
                'required',
                Rule::exists('stores', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'slug')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($category?->id),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
