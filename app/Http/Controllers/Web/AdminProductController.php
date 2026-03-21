<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $status = (string) $request->input('status', '');
        $storeId = (int) $request->input('store_id', 0);
        $categoryId = (int) $request->input('category_id', 0);

        $productQuery = Product::query()->with(['store.tenant', 'category']);

        if ($search !== '') {
            $productQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('meta_retailer_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', "%{$search}%"));
                    })
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

        return view('admin.products.index', [
            'products' => $products,
            'stores' => Store::query()->with('tenant')->orderBy('name')->get(),
            'categories' => ProductCategory::query()->with('store')->orderBy('name')->get(),
            'stats' => [
                'total' => Product::query()->count(),
                'active' => Product::query()->where('is_active', true)->count(),
                'low_stock' => Product::query()->where('inventory_quantity', '<=', 10)->count(),
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        $validated = $this->syncProductImage($request, $validated);

        Product::create($validated);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product->load('store', 'category'),
            'stores' => Store::query()->with('tenant')->orderBy('name')->get(),
            'categories' => ProductCategory::query()->with('store')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product);
        $validated = $this->syncProductImage($request, $validated, $product);

        $product->update($validated);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    protected function validateProduct(Request $request, ?Product $product = null): array
    {
        $storeId = (int) $request->input('store_id');

        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
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
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
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
