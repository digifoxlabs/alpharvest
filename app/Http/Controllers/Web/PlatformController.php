<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\AgentInboxService;
use Illuminate\Contracts\View\View;

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
}
