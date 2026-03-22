<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\AgentInboxService;
use App\Services\StoreEngineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TenantStoreController extends Controller
{
    public function __construct(
        protected AgentInboxService $inbox,
        protected StoreEngineService $storeEngine,
    ) {
    }

    public function index(Request $request, Tenant $tenant): View
    {
        $search = trim((string) $request->input('search'));
        $status = (string) $request->input('status', '');

        $storeQuery = $tenant->stores()
            ->withCount(['categories', 'products', 'orders']);

        if ($search !== '') {
            $storeQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('support_phone', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp_brand_name', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $storeQuery->where('is_active', $status === 'active');
        }

        $stores = $storeQuery
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        $stores->getCollection()->transform(function (Store $store) {
            $store->setAttribute('catalog_readiness', $this->storeEngine->whatsappCatalogReadiness($store));

            return $store;
        });

        return view('tenant.stores.index', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
            'stores' => $stores,
            'stats' => [
                'total' => $tenant->stores()->count(),
                'active' => $tenant->stores()->where('is_active', true)->count(),
                'catalog_linked' => $tenant->stores()->whereNotNull('meta_catalog_id')->where('meta_catalog_id', '!=', '')->count(),
                'filtered' => $stores->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validateStore($request);
        $validated['tenant_id'] = $tenant->id;
        $validated = $this->syncStoreImage($request, $validated);
        $validated = $this->syncStoreSettings($validated);

        Store::create($validated);

        return redirect()
            ->route('dashboard.stores.index', $tenant)
            ->with('status', 'Store created.');
    }

    public function create(Tenant $tenant): View
    {
        return view('tenant.stores.create', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
        ]);
    }

    public function edit(Tenant $tenant, Store $store): View
    {
        $this->ensureTenantStore($tenant, $store);

        return view('tenant.stores.edit', [
            'tenant' => $tenant,
            'overview' => $this->inbox->tenantOverview($tenant),
            'store' => $store,
            'catalogReadiness' => $this->storeEngine->whatsappCatalogReadiness($store),
            'deliveryZonesText' => $this->deliveryZonesText($store),
        ]);
    }

    public function update(Request $request, Tenant $tenant, Store $store): RedirectResponse
    {
        $this->ensureTenantStore($tenant, $store);

        $validated = $this->validateStore($request, $store);
        $validated['tenant_id'] = $tenant->id;
        $validated = $this->syncStoreImage($request, $validated, $store);
        $validated = $this->syncStoreSettings($validated, $store);

        $store->update($validated);

        return redirect()
            ->route('dashboard.stores.index', $tenant)
            ->with('status', 'Store updated.');
    }

    public function destroy(Tenant $tenant, Store $store): RedirectResponse
    {
        $this->ensureTenantStore($tenant, $store);

        if ($store->whatsapp_store_image_path) {
            Storage::disk('public')->delete($store->whatsapp_store_image_path);
        }

        $store->delete();

        return redirect()
            ->route('dashboard.stores.index', $tenant)
            ->with('status', 'Store deleted.');
    }

    protected function ensureTenantStore(Tenant $tenant, Store $store): void
    {
        abort_unless($store->tenant_id === $tenant->id, 404);
    }

    protected function validateStore(Request $request, ?Store $store = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('stores', 'slug')->ignore($store?->id)],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'whatsapp_phone_number_id')->ignore($store?->id)],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:255'],
            'meta_catalog_id' => ['nullable', 'string', 'max:255'],
            'meta_access_token' => ['nullable', 'string'],
            'whatsapp_brand_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_welcome_text' => ['nullable', 'string', 'max:1024'],
            'whatsapp_store_intro' => ['nullable', 'string', 'max:2048'],
            'whatsapp_contact_text' => ['nullable', 'string', 'max:1024'],
            'delivery_zones_text' => ['nullable', 'string'],
            'undeliverable_message' => ['nullable', 'string', 'max:1024'],
            'whatsapp_store_image' => ['nullable', 'image', 'max:4096'],
            'remove_whatsapp_store_image' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['remove_whatsapp_store_image'] = $request->boolean('remove_whatsapp_store_image');

        return $validated;
    }

    protected function syncStoreSettings(array $validated, ?Store $store = null): array
    {
        $settings = $store?->settings ?? [];

        $settings['delivery_zones'] = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['delivery_zones_text'] ?? '')) ?: [])
            ->map(function (string $line) {
                $line = trim($line);

                if ($line === '') {
                    return null;
                }

                $parts = preg_split('/\s*[|,]\s*/', $line, 2) ?: [];
                $pincode = trim((string) ($parts[0] ?? ''));
                $city = trim((string) ($parts[1] ?? ''));

                if ($pincode === '' || $city === '') {
                    return null;
                }

                return [
                    'pincode' => $pincode,
                    'city' => $city,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $message = trim((string) ($validated['undeliverable_message'] ?? ''));
        $settings['undeliverable_message'] = $message !== '' ? $message : null;

        $validated['settings'] = $settings;

        unset($validated['delivery_zones_text'], $validated['undeliverable_message']);

        return $validated;
    }

    protected function deliveryZonesText(Store $store): string
    {
        return collect(data_get($store->settings, 'delivery_zones', []))
            ->map(fn (array $zone) => trim(($zone['pincode'] ?? '').' | '.($zone['city'] ?? '')))
            ->filter()
            ->implode("\n");
    }

    protected function syncStoreImage(Request $request, array $validated, ?Store $store = null): array
    {
        if (($validated['remove_whatsapp_store_image'] ?? false) && $store?->whatsapp_store_image_path) {
            Storage::disk('public')->delete($store->whatsapp_store_image_path);
            $validated['whatsapp_store_image_path'] = null;
        }

        if ($request->hasFile('whatsapp_store_image')) {
            if ($store?->whatsapp_store_image_path) {
                Storage::disk('public')->delete($store->whatsapp_store_image_path);
            }

            $validated['whatsapp_store_image_path'] = $request->file('whatsapp_store_image')
                ->store('whatsapp/stores', 'public');
        }

        unset($validated['whatsapp_store_image'], $validated['remove_whatsapp_store_image']);

        return $validated;
    }
}
