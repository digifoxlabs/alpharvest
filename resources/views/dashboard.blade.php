@extends('tenant.layout', [
    'headerBadges' => [
        strtoupper($tenant->plan).' plan',
        $tenant->timezone,
        'Overview',
    ],
])

@section('content')
    <section id="overview" class="panel spotlight">
        <div>
            <p class="eyebrow">Tenant command center</p>
            <h2>{{ $tenant->name }} SaaS dashboard</h2>
            <p class="muted">
                This overview keeps store health, current conversations, and recent orders within reach,
                while inbox and orders now have their own paged workspaces for high-volume handling.
            </p>
            <div class="actions" style="margin-top: 1rem;">
                <a class="button secondary" href="{{ route('dashboard.inbox', $tenant) }}">Open inbox</a>
                <a class="button" href="{{ route('dashboard.orders', $tenant) }}">Open orders</a>
            </div>
        </div>

        <div class="spotlight-grid">
            <article class="spotlight-stat">
                <span class="eyebrow">Plan</span>
                <strong>{{ strtoupper($tenant->plan) }}</strong>
                <span class="muted">{{ $tenant->currency }} workspace</span>
            </article>
            <article class="spotlight-stat">
                <span class="eyebrow">Timezone</span>
                <strong>{{ $tenant->timezone }}</strong>
                <span class="muted">Operational default</span>
            </article>
        </div>
    </section>

    <section class="metrics">
        <article class="metric">
            <strong>{{ $overview['metrics']['stores'] }}</strong>
            <span class="muted">active stores</span>
        </article>
        <article class="metric">
            <strong>{{ $overview['metrics']['products'] }}</strong>
            <span class="muted">catalog products</span>
        </article>
        <article class="metric">
            <strong>{{ $overview['metrics']['open_conversations'] }}</strong>
            <span class="muted">open WhatsApp conversations</span>
        </article>
        <article class="metric">
            <strong>{{ number_format((float) $overview['metrics']['recent_order_value'], 2) }}</strong>
            <span class="muted">recent order value</span>
        </article>
    </section>

    <section class="grid columns-2">
        <article id="inbox" class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Inbox preview</p>
                    <h2>Open conversations</h2>
                    <p class="muted">Newest activity first.</p>
                </div>
                <a class="button secondary" href="{{ route('dashboard.inbox', $tenant) }}">View all inbox items</a>
            </div>

            <div class="table">
                @forelse ($overview['open_conversations'] as $conversation)
                    <div class="table-row">
                        <div class="actions">
                            <strong>{{ $conversation->customer?->name ?: $conversation->customer?->phone }}</strong>
                            <span class="badge {{ $conversation->status === 'open' ? 'success' : 'warning' }}">{{ ucfirst($conversation->status) }}</span>
                        </div>
                        <span class="muted">{{ $conversation->store?->name }} | {{ optional($conversation->last_message_at)->diffForHumans() }} | {{ $conversation->messages_count }} messages</span>
                    </div>
                @empty
                    <p class="muted">No open conversations yet.</p>
                @endforelse
            </div>
        </article>

        <article id="orders" class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Orders preview</p>
                    <h2>Recent orders</h2>
                    <p class="muted">Use the orders page for full paging and handling.</p>
                </div>
                <a class="button secondary" href="{{ route('dashboard.orders', $tenant) }}">View all orders</a>
            </div>

            <div class="table">
                @forelse ($overview['recent_orders'] as $order)
                    <div class="table-row">
                        <strong>{{ $order->order_number }} | {{ $order->customer?->name ?: $order->customer?->phone ?: 'Unknown customer' }}</strong>
                        <span class="muted">{{ $order->store?->name }} | {{ ucfirst(str_replace('_', ' ', $order->status)) }} | {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }} | {{ $order->currency }} {{ number_format((float) $order->total, 2) }}</span>
                        <span class="muted">
                            Deliver to pincode: {{ data_get($order->metadata, 'delivery.pincode') ?: 'Not saved' }}
                            | Address: {{ data_get($order->metadata, 'delivery.address') ?: 'Not saved' }}
                        </span>
                        <span class="muted">
                            Products:
                            {{ $order->items->map(fn ($item) => $item->quantity.' x '.$item->product_name)->implode(', ') ?: 'No items' }}
                        </span>
                        <span class="muted">
                            Customer: {{ $order->customer?->phone ?: 'No phone' }}
                            | Address requested: {{ data_get($order->metadata, 'admin_follow_up.address_requested_at') ? 'Yes' : 'No' }}
                            | Payment link sent: {{ data_get($order->metadata, 'admin_follow_up.payment_link_sent_at') ? 'Yes' : 'No' }}
                        </span>
                        <div class="actions">
                            <form method="POST" action="{{ route('dashboard.orders.request-address', [$tenant, $order]) }}">
                                @csrf
                                <button class="chip" type="submit">Request Address</button>
                            </form>
                            <form method="POST" action="{{ route('dashboard.orders.send-payment-link', [$tenant, $order]) }}">
                                @csrf
                                <button class="chip" type="submit">Send Payment Link</button>
                            </form>
                            <form method="POST" action="{{ route('dashboard.orders.update-status', [$tenant, $order]) }}" class="inline-form">
                                @csrf
                                <select name="status" aria-label="Order status">
                                    @foreach (['awaiting_address', 'pending_payment', 'payment_requested', 'processing', 'completed', 'cancelled'] as $status)
                                        <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                    @endforeach
                                </select>
                                <select name="payment_status" aria-label="Payment status">
                                    @foreach (['unpaid', 'pending', 'paid', 'failed'] as $paymentStatus)
                                        <option value="{{ $paymentStatus }}" @selected($order->payment_status === $paymentStatus)>{{ ucfirst(str_replace('_', ' ', $paymentStatus)) }}</option>
                                    @endforeach
                                </select>
                                <button class="chip" type="submit">Update Status</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="muted">No orders yet.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section id="stores" class="grid columns-2">
        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Stores</p>
                    <h2>Store performance</h2>
                </div>
            </div>

            <div class="table">
                @forelse ($overview['stores'] as $store)
                    <div class="table-row">
                        <strong>{{ $store->name }}</strong>
                        <span class="muted">{{ $store->products_count }} products | {{ $store->customers_count }} customers | {{ $store->orders_count }} orders</span>
                    </div>
                @empty
                    <p class="muted">No stores connected yet.</p>
                @endforelse
            </div>
        </article>

        <article class="panel panel-dark">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Useful endpoints</p>
                    <h2>Backend API</h2>
                </div>
            </div>

            <div class="code-stack">
                <code>GET /api/dashboard/{{ $tenant->slug }}/overview</code>
                <code>GET /api/dashboard/{{ $tenant->slug }}/conversations</code>
                <code>GET /api/dashboard/{{ $tenant->slug }}/orders</code>
            </div>
        </article>
    </section>
@endsection
