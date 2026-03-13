<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminStoreController extends Controller
{
    public function index(): View
    {
        return view('admin.stores.index', [
            'stores' => Store::query()
                ->with('tenant')
                ->withCount(['categories', 'products', 'orders'])
                ->orderBy('name')
                ->get(),
            'tenants' => Tenant::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateStore($request);

        Store::create($validated);

        return redirect()
            ->route('admin.stores.index')
            ->with('status', 'Store created.');
    }

    public function edit(Store $store): View
    {
        return view('admin.stores.edit', [
            'store' => $store,
            'tenants' => Tenant::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $validated = $this->validateStore($request, $store);

        $store->update($validated);

        return redirect()
            ->route('admin.stores.index')
            ->with('status', 'Store updated.');
    }

    public function destroy(Store $store): RedirectResponse
    {
        $store->delete();

        return redirect()
            ->route('admin.stores.index')
            ->with('status', 'Store deleted.');
    }

    protected function validateStore(Request $request, ?Store $store = null): array
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('stores', 'slug')->ignore($store?->id)],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'whatsapp_phone_number_id')->ignore($store?->id)],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:255'],
            'meta_access_token' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
