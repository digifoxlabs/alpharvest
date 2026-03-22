<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Services\AgentInboxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TenantProductController extends Controller
{
    public function __construct(protected AgentInboxService $inbox)
    {
    }

    public function index(Request $request, Tenant $tenant): View
    {
        $search = trim((string) $request->input('search'));
        $status = (string) $request->input('status', '');
        $storeId = (int) $request->input('store_id', 0);
        $categoryId = (int) $request->input('category_id', 0);

        $storeIds = $tenant->stores()->pluck('id');

        $productQuery = Product::query()
            ->whereIn('store_id', $storeIds)
            ->with(['store', 'category']);

        if ($search !== '') {
            $productQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('meta_retailer_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%")
                    ->orWhere('size', 'like', "%{$search}%")
                    ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $productQuery->where('is_active', $status === 'active');
        }

        if ($storeId > 0) {
            $productQuery->where('store_id', $storeId);
        }

        if ($categoryId > 0) {
            $productQuery->where('product_category_id', $categoryId);
        }

        $products = $productQuery
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $stores = $tenant->stores()->orderBy('name')->get();
        $categories = ProductCategory::query()->whereIn('store_id', $storeIds)->with('store')->orderBy('name')->get();

        return view('tenant.products.index', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
            'products' => $products,
            'stores' => $stores,
            'categories' => $categories,
            'stats' => [
                'total' => Product::query()->whereIn('store_id', $storeIds)->count(),
                'active' => Product::query()->whereIn('store_id', $storeIds)->where('is_active', true)->count(),
                'low_stock' => Product::query()->whereIn('store_id', $storeIds)->where('inventory_quantity', '<=', 10)->count(),
                'filtered' => $products->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'store_id' => $storeId > 0 ? (string) $storeId : '',
                'category_id' => $categoryId > 0 ? (string) $categoryId : '',
            ],
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validateProduct($request, $tenant);
        $validated = $this->syncProductImage($request, $validated);

        Product::create($validated);

        return redirect()
            ->route('dashboard.products.index', $tenant)
            ->with('status', 'Product created.');
    }

    public function create(Tenant $tenant): View
    {
        $storeIds = $tenant->stores()->pluck('id');

        return view('tenant.products.create', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
            'stores' => $tenant->stores()->orderBy('name')->get(),
            'categories' => ProductCategory::query()->whereIn('store_id', $storeIds)->with('store')->orderBy('name')->get(),
        ]);
    }

    public function edit(Tenant $tenant, Product $product): View
    {
        $this->ensureTenantProduct($tenant, $product);

        $storeIds = $tenant->stores()->pluck('id');

        return view('tenant.products.edit', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
            'product' => $product->load('store', 'category'),
            'stores' => $tenant->stores()->orderBy('name')->get(),
            'categories' => ProductCategory::query()->whereIn('store_id', $storeIds)->with('store')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Tenant $tenant, Product $product): RedirectResponse
    {
        $this->ensureTenantProduct($tenant, $product);

        $validated = $this->validateProduct($request, $tenant, $product);
        $validated = $this->syncProductImage($request, $validated, $product);

        $product->update($validated);

        return redirect()
            ->route('dashboard.products.index', $tenant)
            ->with('status', 'Product updated.');
    }

    public function destroy(Tenant $tenant, Product $product): RedirectResponse
    {
        $this->ensureTenantProduct($tenant, $product);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return redirect()
            ->route('dashboard.products.index', $tenant)
            ->with('status', 'Product deleted.');
    }

    protected function ensureTenantProduct(Tenant $tenant, Product $product): void
    {
        abort_unless($product->store()->where('tenant_id', $tenant->id)->exists(), 404);
    }

    protected function validateProduct(Request $request, Tenant $tenant, ?Product $product = null): array
    {
        $storeId = (int) $request->input('store_id');

        $validated = $request->validate([
            'store_id' => [
                'required',
                Rule::exists('stores', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'product_category_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query->where('store_id', $storeId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'slug')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($product?->id),
            ],
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($product?->id),
            ],
            'meta_retailer_id' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'shipping_weight' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'inventory_quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['remove_image'] = $request->boolean('remove_image');

        return $validated;
    }

    protected function syncProductImage(Request $request, array $validated, ?Product $product = null): array
    {
        if (($validated['remove_image'] ?? false) && $product?->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $validated['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            if ($product?->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        unset($validated['image'], $validated['remove_image']);

        return $validated;
    }
}
