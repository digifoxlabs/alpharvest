@extends('tenant.layout', [
    'title' => $tenant->name.' Orders',
    'heading' => 'Tenant orders',
    'subheading' => 'Review, page through, and update orders without crowding the main tenant overview.',
    'headerBadges' => [
        $orders->total().' orders',
        'Page '.$orders->currentPage(),
        strtoupper($tenant->plan).' plan',
    ],
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Orders workspace</p>
            <h2>Recent and active orders</h2>
            <p class="muted">Each order card keeps delivery details, product lines, and action controls together while filters help with large order queues.</p>
        </div>
        <div class="summary-grid">
            <article class="summary-card">
                <span class="eyebrow">Stores</span>
                <strong>{{ $overview['metrics']['stores'] }}</strong>
                <span class="muted">connected storefronts</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Products</span>
                <strong>{{ $overview['metrics']['products'] }}</strong>
                <span class="muted">catalog products</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Order value</span>
                <strong>{{ $tenant->currency }} {{ number_format((float) $overview['metrics']['recent_order_value'], 2) }}</strong>
                <span class="muted">recent page summary</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Filtered</span>
                <strong>{{ $orders->total() }}</strong>
                <span class="muted">results in view</span>
            </article>
        </div>
    </section>

    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Queue</p>
                <h2>Recent and active orders</h2>
                <p class="muted">Filter by store, order status, or payment status before acting.</p>
            </div>
            <a class="button secondary" href="{{ route('dashboard.show', $tenant) }}">Back to overview</a>
        </div>

        <form class="toolbar toolbar--four" method="GET" action="{{ route('dashboard.orders', $tenant) }}">
            <label class="toolbar-field">
                Search orders
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Order, customer, phone, product">
            </label>
            <label class="toolbar-field">
                Store
                <select name="store_id">
                    <option value="">All stores</option>
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}" @selected($filters['store_id'] === (string) $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="toolbar-field">
                Order status
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach (['awaiting_address', 'pending_payment', 'payment_requested', 'processing', 'completed', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="toolbar-field">
                Payment status
                <select name="payment_status">
                    <option value="">All payment states</option>
                    @foreach (['unpaid', 'pending', 'paid', 'failed'] as $paymentStatus)
                        <option value="{{ $paymentStatus }}" @selected($filters['payment_status'] === $paymentStatus)>{{ ucfirst(str_replace('_', ' ', $paymentStatus)) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="toolbar-actions">
                <button type="submit">Apply filters</button>
                <a class="button secondary" href="{{ route('dashboard.orders', $tenant) }}">Reset</a>
            </div>
        </form>

        <div class="table entity-table entity-table--stacked">
            @forelse ($orders as $order)
                <div class="entity-row entity-row--stacked">
                    <div class="entity-main">
                        <div class="entity-title">
                            <strong>{{ $order->order_number }}</strong>
                            <span class="badge {{ $order->payment_status === 'paid' ? 'success' : ($order->payment_status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</span>
                            <span class="badge subtle">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </div>
                        <div class="entity-meta">
                            <span>{{ $order->customer?->name ?: $order->customer?->phone ?: 'Unknown customer' }}</span>
                            <span>{{ $order->store?->name }}</span>
                            <span>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</span>
                        </div>
                        <p class="entity-copy">Deliver to pincode: {{ data_get($order->metadata, 'delivery.pincode') ?: 'Not saved' }} | City: {{ data_get($order->metadata, 'delivery.city') ?: 'Not saved' }}</p>
                        <p class="entity-copy">Address: {{ data_get($order->metadata, 'delivery.address') ?: 'Not saved' }}</p>
                        <p class="entity-copy">Products: {{ $order->items->map(fn ($item) => $item->quantity.' x '.$item->product_name)->implode(', ') ?: 'No items' }}</p>
                        <div class="chip-row">
                            <span class="badge subtle">Address requested: {{ data_get($order->metadata, 'admin_follow_up.address_requested_at') ? 'Yes' : 'No' }}</span>
                            <span class="badge subtle">Payment link sent: {{ data_get($order->metadata, 'admin_follow_up.payment_link_sent_at') ? 'Yes' : 'No' }}</span>
                        </div>
                        <div class="entity-actions entity-actions--inline">
                            <form method="POST" action="{{ route('dashboard.orders.request-address', [$tenant, $order]) }}">
                                @csrf
                                <input type="hidden" name="redirect_route" value="dashboard.orders">
                                <button class="chip" type="submit">Request Address</button>
                            </form>
                            <form method="POST" action="{{ route('dashboard.orders.send-payment-link', [$tenant, $order]) }}">
                                @csrf
                                <input type="hidden" name="redirect_route" value="dashboard.orders">
                                <button class="chip" type="submit">Send Payment Link</button>
                            </form>
                            <form method="POST" action="{{ route('dashboard.orders.update-status', [$tenant, $order]) }}" class="inline-form">
                                @csrf
                                <input type="hidden" name="redirect_route" value="dashboard.orders">
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
                </div>
            @empty
                <p class="muted">No orders yet.</p>
            @endforelse
        </div>
    </section>

    @include('layouts.panel.pagination', ['paginator' => $orders])
@endsection
