<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTenantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $status = (string) $request->input('status', '');
        $plan = (string) $request->input('plan', '');

        $tenantQuery = Tenant::query()->withCount(['stores', 'users']);

        if ($search !== '') {
            $tenantQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('plan', 'like', "%{$search}%")
                    ->orWhere('timezone', 'like', "%{$search}%")
                    ->orWhere('currency', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $tenantQuery->where('is_active', $status === 'active');
        }

        if ($plan !== '') {
            $tenantQuery->where('plan', $plan);
        }

        $tenants = $tenantQuery
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'plans' => Tenant::query()
                ->select('plan')
                ->distinct()
                ->orderBy('plan')
                ->pluck('plan')
                ->filter()
                ->values(),
            'stats' => [
                'total' => Tenant::query()->count(),
                'active' => Tenant::query()->where('is_active', true)->count(),
                'plans' => Tenant::query()->distinct('plan')->count('plan'),
                'filtered' => $tenants->total(),
            ],
            'filters' => compact('search', 'status', 'plan'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTenant($request);

        Tenant::create($validated);

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', 'Tenant created.');
    }

    public function edit(Tenant $tenant): View
    {
        return view('admin.tenants.edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validateTenant($request, $tenant);

        $tenant->update($validated);

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', 'Tenant deleted.');
    }

    protected function validateTenant(Request $request, ?Tenant $tenant = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tenants', 'slug')->ignore($tenant?->id)],
            'plan' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
