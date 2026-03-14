<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\Tenant;

class AgentInboxService
{
    public function tenantOverview(Tenant $tenant): array
    {
        $storeIds = $tenant->stores()->pluck('id');

        $openConversations = Conversation::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('status', ['open', 'pending'])
            ->with(['customer', 'store', 'assignedUser'])
            ->latest('last_message_at')
            ->limit(8)
            ->get();

        $recentOrders = Order::query()
            ->whereIn('store_id', $storeIds)
            ->with(['customer', 'store', 'payments', 'items'])
            ->latest('id')
            ->limit(8)
            ->get();

        return [
            'metrics' => [
                'stores' => $storeIds->count(),
                'products' => $tenant->stores()->withCount('products')->get()->sum('products_count'),
                'open_conversations' => $openConversations->count(),
                'recent_order_value' => $recentOrders->sum('total'),
            ],
            'stores' => $tenant->stores()
                ->withCount(['products', 'customers', 'conversations', 'orders'])
                ->orderBy('name')
                ->get(),
            'open_conversations' => $openConversations,
            'recent_orders' => $recentOrders,
        ];
    }
}
