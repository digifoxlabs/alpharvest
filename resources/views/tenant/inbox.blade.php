@extends('tenant.layout', [
    'title' => $tenant->name.' Inbox',
    'heading' => 'Tenant inbox',
    'subheading' => 'Handle recent WhatsApp conversations on a dedicated, filterable page.',
    'headerBadges' => [
        $conversations->total().' conversations',
        'Page '.$conversations->currentPage(),
        strtoupper($tenant->plan).' plan',
    ],
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Inbox workspace</p>
            <h2>Conversation queue</h2>
            <p class="muted">Handle recent WhatsApp conversations without overloading the overview page, and filter by store or status when the queue grows.</p>
        </div>
        <div class="summary-grid">
            <article class="summary-card">
                <span class="eyebrow">Open queue</span>
                <strong>{{ $overview['metrics']['open_conversations'] }}</strong>
                <span class="muted">recent open threads</span>
            </article>
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
                <span class="eyebrow">Filtered</span>
                <strong>{{ $conversations->total() }}</strong>
                <span class="muted">results in view</span>
            </article>
        </div>
    </section>

    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Queue</p>
                <h2>Conversation queue</h2>
                <p class="muted">Use quick filters to focus on one store or only pending threads.</p>
            </div>
            <a class="button secondary" href="{{ route('dashboard.show', $tenant) }}">Back to overview</a>
        </div>

        <form class="toolbar toolbar--three" method="GET" action="{{ route('dashboard.inbox', $tenant) }}">
            <label class="toolbar-field">
                Search conversations
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Customer, phone, store, assignee">
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
                Status
                <select name="status">
                    <option value="">Open and pending</option>
                    <option value="open" @selected($filters['status'] === 'open')>Open</option>
                    <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                </select>
            </label>
            <div class="toolbar-actions">
                <button type="submit">Apply filters</button>
                <a class="button secondary" href="{{ route('dashboard.inbox', $tenant) }}">Reset</a>
            </div>
        </form>

        <div class="table entity-table">
            @forelse ($conversations as $conversation)
                <div class="entity-row entity-row--stacked mb-2">
                    <div class="entity-main">
                        <div class="entity-title">
                            <strong>{{ $conversation->customer?->name ?: $conversation->customer?->phone }}</strong>
                            <span class="badge {{ $conversation->status === 'open' ? 'success' : 'warning' }}">{{ ucfirst($conversation->status) }}</span>
                            <span class="badge subtle">{{ $conversation->messages_count }} messages</span>
                        </div>
                        <div class="entity-meta">
                            <span>{{ $conversation->store?->name }}</span>
                            <span>{{ $conversation->customer?->phone ?: 'No phone' }}</span>
                            <span>Last activity {{ optional($conversation->last_message_at)->diffForHumans() }}</span>
                        </div>
                        <p class="entity-copy">Assigned user: {{ $conversation->assignedUser?->name ?: 'Unassigned' }}</p>
                    </div>
                </div>
            @empty
                <p class="muted">No open conversations right now.</p>
            @endforelse
        </div>
    </section>

    @include('layouts.panel.pagination', ['paginator' => $conversations])
@endsection
