<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminMessageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $direction = (string) $request->input('direction', '');
        $status = (string) $request->input('status', '');
        $tenantId = (int) $request->input('tenant_id', 0);

        $messageQuery = Message::query()->with(['conversation.customer', 'conversation.store.tenant']);

        if ($search !== '') {
            $messageQuery->where(function ($query) use ($search) {
                $query
                    ->where('body', 'like', "%{$search}%")
                    ->orWhere('whatsapp_message_id', 'like', "%{$search}%")
                    ->orWhereHas('conversation.customer', function ($customerQuery) use ($search) {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('conversation.store', function ($storeQuery) use ($search) {
                        $storeQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if (in_array($direction, ['inbound', 'outbound'], true)) {
            $messageQuery->where('direction', $direction);
        }

        if ($tenantId > 0) {
            $messageQuery->whereHas('conversation.store', fn ($storeQuery) => $storeQuery->where('tenant_id', $tenantId));
        }

        if ($status !== '') {
            match ($status) {
                'received' => $messageQuery->where('direction', 'inbound'),
                'read' => $messageQuery->whereNotNull('read_at'),
                'delivered' => $messageQuery->whereNull('read_at')->whereNotNull('delivered_at'),
                'sent' => $messageQuery
                    ->whereNull('read_at')
                    ->whereNull('delivered_at')
                    ->where(function ($query) {
                        $query
                            ->whereNotNull('sent_at')
                            ->orWhere('payload->status_update->status', 'sent');
                    }),
                'failed' => $messageQuery->where(function ($query) {
                    $query
                        ->where('payload->status_update->status', 'failed')
                        ->orWhere('payload->dispatched', false);
                }),
                'queued' => $messageQuery
                    ->where('direction', 'outbound')
                    ->whereNull('sent_at')
                    ->whereNull('delivered_at')
                    ->whereNull('read_at')
                    ->where(function ($query) {
                        $query
                            ->whereNull('payload->status_update->status')
                            ->where(function ($innerQuery) {
                                $innerQuery
                                    ->whereNull('payload->dispatched')
                                    ->orWhere('payload->dispatched', true);
                            });
                    }),
                default => null,
            };
        }

        $messages = $messageQuery
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'tenants' => Tenant::query()->orderBy('name')->get(),
            'stats' => [
                'total' => Message::query()->count(),
                'inbound' => Message::query()->where('direction', 'inbound')->count(),
                'outbound' => Message::query()->where('direction', 'outbound')->count(),
                'filtered' => $messages->total(),
            ],
            'filters' => [
                'search' => $search,
                'direction' => $direction,
                'status' => $status,
                'tenant_id' => $tenantId > 0 ? (string) $tenantId : '',
            ],
        ]);
    }
}
