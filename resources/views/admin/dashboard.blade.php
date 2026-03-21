@extends('admin.layout', [
    'title' => 'Admin Dashboard',
    'heading' => 'Operations overview',
    'subheading' => 'A Material-style control room for tenants, stores, categories, products, and WhatsApp message activity.',
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Platform control room</p>
            <h2>Admin workspace</h2>
            <p class="muted">Create and manage tenant workspaces, storefronts, catalog structure, and products from one card-based backend.</p>
        </div>
        <div class="spotlight-grid">
            <article class="spotlight-stat">
                <span class="eyebrow">Manage</span>
                <strong>CRUD modules</strong>
                <span class="muted">Tenants, stores, categories, products</span>
            </article>
            <article class="spotlight-stat">
                <span class="eyebrow">Monitor</span>
                <strong>{{ $metrics['messages'] }} messages</strong>
                <span class="muted">WhatsApp delivery and inbox activity</span>
            </article>
        </div>
    </section>

    <section class="metrics">
        <article class="metric">
            <strong>{{ $metrics['tenants'] }}</strong>
            <span class="muted">tenants</span>
        </article>
        <article class="metric">
            <strong>{{ $metrics['stores'] }}</strong>
            <span class="muted">stores</span>
        </article>
        <article class="metric">
            <strong>{{ $metrics['categories'] }}</strong>
            <span class="muted">categories</span>
        </article>
        <article class="metric">
            <strong>{{ $metrics['products'] }}</strong>
            <span class="muted">products</span>
        </article>
        <article class="metric">
            <strong>{{ $metrics['messages'] }}</strong>
            <span class="muted">messages</span>
        </article>
    </section>

    <section class="grid columns-2">
        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">CRUD modules</p>
                    <h2>Manage platform entities</h2>
                    <p class="muted">Jump straight into the create and update workflows.</p>
                </div>
            </div>

            <div class="table">
                <div class="table-row">
                    <strong>Tenants</strong>
                    <span class="muted">Create SaaS workspaces, update settings, and manage active plans.</span>
                    <a class="button secondary" href="{{ route('admin.tenants.index') }}">Manage tenants</a>
                </div>
                <div class="table-row">
                    <strong>Stores</strong>
                    <span class="muted">Configure WhatsApp storefronts, delivery zones, and native catalog readiness.</span>
                    <a class="button secondary" href="{{ route('admin.stores.index') }}">Manage stores</a>
                </div>
                <div class="table-row">
                    <strong>Categories and products</strong>
                    <span class="muted">Keep catalog taxonomy and sellable inventory in sync.</span>
                    <div class="actions">
                        <a class="button secondary" href="{{ route('admin.categories.index') }}">Manage categories</a>
                        <a class="button" href="{{ route('admin.products.index') }}">Manage products</a>
                    </div>
                </div>
            </div>
        </article>

        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Recent tenants</p>
                    <h2>Workspace accounts</h2>
                </div>
                <a class="button secondary" href="{{ route('admin.tenants.index') }}">Manage tenants</a>
            </div>

            <div class="table">
                @forelse ($tenants as $tenant)
                    <div class="table-row">
                        <strong>{{ $tenant->name }}</strong>
                        <span class="muted">{{ strtoupper($tenant->plan) }} | {{ $tenant->currency }} | {{ $tenant->stores_count }} stores</span>
                    </div>
                @empty
                    <p class="muted">No tenants created yet.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="grid columns-2">
        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Recent stores</p>
                    <h2>Storefronts</h2>
                </div>
                <a class="button secondary" href="{{ route('admin.stores.index') }}">Manage stores</a>
            </div>

            <div class="table">
                @forelse ($stores as $store)
                    <div class="table-row">
                        <strong>{{ $store->name }}</strong>
                        <span class="muted">{{ $store->tenant?->name }} | {{ $store->products_count }} products | {{ $store->orders_count }} orders</span>
                    </div>
                @empty
                    <p class="muted">No stores created yet.</p>
                @endforelse
            </div>
        </article>

        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Latest message activity</p>
                    <h2>WhatsApp status feed</h2>
                </div>
                <a class="button secondary" href="{{ route('admin.messages.index') }}">Open messages</a>
            </div>

            <div class="table">
                @forelse ($recentMessages as $message)
                    <div class="table-row">
                        <div class="actions">
                            <strong>{{ $message->conversation?->store?->name ?: 'Unknown store' }}</strong>
                            <span class="badge {{ $message->status_tone }}">{{ $message->status_label }}</span>
                        </div>
                        <span class="muted">{{ ucfirst($message->direction) }} | {{ $message->type }} | {{ $message->conversation?->customer?->phone ?: 'Unknown customer' }}</span>
                        <span class="muted">{{ $message->body ?: 'No message body stored.' }}</span>
                    </div>
                @empty
                    <p class="muted">No messages yet.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Newest products</p>
                <h2>Inventory across stores</h2>
                <p class="muted">Manage products from the dedicated CRUD surface.</p>
            </div>
            <a class="button" href="{{ route('admin.products.index') }}">Manage products</a>
        </div>

        <div class="table">
            @forelse ($products as $product)
                <div class="table-row">
                    <strong>{{ $product->name }}</strong>
                    <span class="muted">{{ $product->store?->tenant?->name }} | {{ $product->store?->name }} | {{ $product->category?->name ?: 'Uncategorized' }} | {{ $product->sku }} | {{ $product->store?->currency }} {{ number_format((float) $product->price, 2) }}</span>
                </div>
            @empty
                <p class="muted">No products yet.</p>
            @endforelse
        </div>
    </section>
@endsection
