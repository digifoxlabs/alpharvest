<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $status = (string) $request->input('status', '');
        $storeId = (int) $request->input('store_id', 0);

        $categoryQuery = ProductCategory::query()
            ->with('store.tenant')
            ->withCount('products');

        if ($search !== '') {
            $categoryQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', "%{$search}%"));
                    });
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

        return view('admin.categories.index', [
            'categories' => $categories,
            'stores' => Store::query()->with('tenant')->orderBy('name')->get(),
            'stats' => [
                'total' => ProductCategory::query()->count(),
                'active' => ProductCategory::query()->where('is_active', true)->count(),
                'stores' => ProductCategory::query()->distinct('store_id')->count('store_id'),
                'filtered' => $categories->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'store_id' => $storeId > 0 ? (string) $storeId : '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);

        ProductCategory::create($validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category created.');
    }

    public function edit(ProductCategory $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category->load('store'),
            'stores' => Store::query()->with('tenant')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category);

        $category->update($validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category updated.');
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category deleted.');
    }

    protected function validateCategory(Request $request, ?ProductCategory $category = null): array
    {
        $storeId = (int) $request->input('store_id');

        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
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
