<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AgentInboxService
{
    public function tenantOverview(Tenant $tenant): array
    {
        $openConversations = $this->tenantConversationQuery($tenant)
            ->limit(6)
            ->get();

        $recentOrders = $this->tenantOrderQuery($tenant)
            ->limit(6)
            ->get();

        return [
            'metrics' => [
                'stores' => $tenant->stores()->count(),
                'products' => $tenant->stores()->withCount('products')->get()->sum('products_count'),
                'open_conversations' => $openConversations->count(),
                'recent_order_value' => $recentOrders->sum('total'),
            ],
            'stores' => $this->tenantStores($tenant),
            'open_conversations' => $openConversations,
            'recent_orders' => $recentOrders,
        ];
    }

    public function tenantStores(Tenant $tenant): Collection
    {
        return $tenant->stores()
            ->withCount(['products', 'customers', 'conversations', 'orders'])
            ->orderBy('name')
            ->get();
    }

    public function tenantConversationPage(Tenant $tenant, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->tenantConversationQuery($tenant, $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function tenantOrderPage(Tenant $tenant, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->tenantOrderQuery($tenant, $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function tenantConversationQuery(Tenant $tenant, array $filters = []): Builder
    {
        $query = Conversation::query()
            ->whereIn('store_id', $tenant->stores()->select('id'))
            ->whereIn('status', ['open', 'pending'])
            ->with(['customer', 'store', 'assignedUser'])
            ->withCount('messages')
            ->latest('last_message_at');

        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $storeId = (int) ($filters['store_id'] ?? 0);

        if ($search !== '') {
            $query->where(function ($conversationQuery) use ($search) {
                $conversationQuery
                    ->whereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('assignedUser', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if (in_array($status, ['open', 'pending'], true)) {
            $query->where('status', $status);
        }

        if ($storeId > 0) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }

    protected function tenantOrderQuery(Tenant $tenant, array $filters = []): Builder
    {
        $query = Order::query()
            ->whereIn('store_id', $tenant->stores()->select('id'))
            ->with(['customer', 'store', 'payments', 'items'])
            ->orderByDesc('placed_at')
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $paymentStatus = (string) ($filters['payment_status'] ?? '');
        $storeId = (int) ($filters['store_id'] ?? 0);

        if ($search !== '') {
            $query->where(function ($orderQuery) use ($search) {
                $orderQuery
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items', fn ($itemQuery) => $itemQuery->where('product_name', 'like', "%{$search}%"));
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($paymentStatus !== '') {
            $query->where('payment_status', $paymentStatus);
        }

        if ($storeId > 0) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }
}
