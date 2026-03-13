<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AgentInboxService;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(protected AgentInboxService $inbox)
    {
    }

    public function overview(Tenant $tenant): JsonResponse
    {
        return response()->json($this->inbox->tenantOverview($tenant));
    }

    public function conversations(Tenant $tenant): JsonResponse
    {
        $overview = $this->inbox->tenantOverview($tenant);

        return response()->json([
            'tenant' => $tenant->only(['name', 'slug', 'plan']),
            'conversations' => $overview['open_conversations'],
        ]);
    }

    public function orders(Tenant $tenant): JsonResponse
    {
        $overview = $this->inbox->tenantOverview($tenant);

        return response()->json([
            'tenant' => $tenant->only(['name', 'slug', 'plan']),
            'orders' => $overview['recent_orders'],
        ]);
    }
}
