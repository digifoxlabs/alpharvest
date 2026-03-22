@extends('tenant.layout', [
    'title' => $tenant->name.' Stores',
    'heading' => 'Manage stores',
    'subheading' => 'Create and maintain storefronts for '.$tenant->name.' without leaving the tenant workspace.',
    'headerBadges' => [
        $stores->total().' stores',
        strtoupper($tenant->plan).' plan',
        'Store management',
    ],
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Store operations</p>
            <h2>{{ $tenant->name }} storefronts</h2>
            <p class="muted">Provision storefronts, configure WhatsApp details, and keep delivery settings visible from the tenant side.</p>
        </div>
        <div class="summary-grid">
            <article class="summary-card">
                <span class="eyebrow">Stores</span>
                <strong>{{ $stats['total'] }}</strong>
                <span class="muted">connected storefronts</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Active</span>
                <strong>{{ $stats['active'] }}</strong>
                <span class="muted">currently selling</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Catalogs</span>
                <strong>{{ $stats['catalog_linked'] }}</strong>
                <span class="muted">linked Meta catalogs</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Filtered</span>
                <strong>{{ $stats['filtered'] }}</strong>
                <span class="muted">results in view</span>
            </article>
        </div>
    </section>

    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Directory</p>
                <h2>Existing stores</h2>
                <p class="muted">Filter the tenant storefronts by keyword or status before editing.</p>
            </div>
            <div class="actions">
                <span class="badge subtle">{{ $stores->total() }} matching</span>
                <a class="button" href="{{ route('dashboard.stores.create', $tenant) }}">Create new store</a>
            </div>
        </div>

        <form class="toolbar toolbar--three" method="GET" action="{{ route('dashboard.stores.index', $tenant) }}">
            <label class="toolbar-field">
                Search stores
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Store, contact, brand name">
            </label>
            <label class="toolbar-field">
                Status
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
            </label>
            <div class="toolbar-actions">
                <button type="submit">Apply filters</button>
                <a class="button secondary" href="{{ route('dashboard.stores.index', $tenant) }}">Reset</a>
            </div>
        </form>

        <div class="table entity-table">
            @forelse ($stores as $store)
                @php($readiness = $store->catalog_readiness)
                <div class="entity-row">
                    <div class="entity-main">
                        <div class="entity-title">
                            <strong>{{ $store->name }}</strong>
                            <span class="badge {{ $readiness['ready'] ? 'success' : 'warning' }}">{{ $readiness['ready'] ? 'Native catalog ready' : 'Native catalog needs setup' }}</span>
                            <span class="badge {{ $store->is_active ? 'success' : 'warning' }}">{{ $store->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <div class="entity-meta">
                            <span>{{ $store->slug }}</span>
                            <span>{{ $store->currency }}</span>
                            <span>{{ $store->contact_phone ?: $store->support_phone ?: 'No phone' }}</span>
                        </div>
                        <p class="entity-copy">{{ $store->description ?: 'No store description added yet.' }}</p>
                        <div class="chip-row">
                            <span class="badge subtle">{{ $store->categories_count }} categories</span>
                            <span class="badge subtle">{{ $store->products_count }} products</span>
                            <span class="badge subtle">{{ $store->orders_count }} orders</span>
                            <span class="badge subtle">Catalog: {{ $store->meta_catalog_id ?: 'Not linked' }}</span>
                            <span class="badge subtle">Deliverable areas: {{ count(data_get($store->settings, 'delivery_zones', [])) }}</span>
                        </div>
                        <div class="chip-row">
                            <span class="badge {{ $readiness['checks']['phone_number_id'] ? 'success' : 'warning' }}">Phone ID</span>
                            <span class="badge {{ $readiness['checks']['access_token'] ? 'success' : 'warning' }}">Access token</span>
                            <span class="badge {{ $readiness['checks']['meta_catalog_id'] ? 'success' : 'warning' }}">Catalog ID</span>
                            <span class="badge {{ $readiness['checks']['active_products'] ? 'success' : 'warning' }}">Active products {{ $readiness['active_products'] }}</span>
                        </div>
                        <p class="muted">{{ $store->contact_email ?: 'No email' }}</p>
                        <p class="muted">{{ $readiness['issues'] !== [] ? implode(' ', $readiness['issues']) : 'This store meets the app-side checks for the native WhatsApp multi-product catalog.' }}</p>
                        @if ($store->whatsapp_store_image_url)
                            <img src="{{ $store->whatsapp_store_image_url }}" alt="{{ $store->name }}" class="thumb">
                        @endif
                    </div>
                    <div class="entity-actions">
                        <a class="button secondary" href="{{ route('dashboard.stores.edit', [$tenant, $store]) }}">Edit</a>
                        <form method="POST" action="{{ route('dashboard.stores.destroy', [$tenant, $store]) }}" onsubmit="return confirm('Delete this store and all dependent data?');">
                            @csrf
                            @method('DELETE')
                            <button class="danger" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="muted">No stores yet.</p>
            @endforelse
        </div>

        @include('layouts.panel.pagination', ['paginator' => $stores])
    </section>
@endsection
