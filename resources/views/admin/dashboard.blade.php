@extends('admin.layout', [
    'title' => 'Admin Dashboard',
    'heading' => 'Operations overview',
    'subheading' => 'A single control room for tenants, stores, categories, and products across the WhatsApp commerce platform.',
])

@section('content')
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
    </section>

    <section class="grid columns-2">
        <article class="panel">
            <div class="table-header">
                <div>
                    <h2>Recent tenants</h2>
                    <p class="muted">Workspace-level SaaS accounts.</p>
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

        <article class="panel">
            <div class="table-header">
                <div>
                    <h2>Recent stores</h2>
                    <p class="muted">Customer-facing storefronts connected to WhatsApp.</p>
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
    </section>

    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Newest products</h2>
                <p class="muted">Inventory across all tenant stores.</p>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('admin.categories.index') }}">Manage categories</a>
                <a class="button" href="{{ route('admin.products.index') }}">Manage products</a>
            </div>
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
