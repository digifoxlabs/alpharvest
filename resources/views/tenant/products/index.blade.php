@extends('tenant.layout', [
    'title' => $tenant->name.' Products',
    'heading' => 'Manage products',
    'subheading' => 'Control inventory, pricing, visuals, and WhatsApp-ready product cards for '.$tenant->name.'.',
    'headerBadges' => [
        $products->total().' products',
        strtoupper($tenant->plan).' plan',
        'Inventory control',
    ],
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Inventory control</p>
            <h2>{{ $tenant->name }} products</h2>
            <p class="muted">Create sellable products, keep retailer IDs in sync for WhatsApp catalogs, and page through inventory by store or category.</p>
        </div>
        <div class="summary-grid">
            <article class="summary-card">
                <span class="eyebrow">Products</span>
                <strong>{{ $stats['total'] }}</strong>
                <span class="muted">catalog items</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Active</span>
                <strong>{{ $stats['active'] }}</strong>
                <span class="muted">currently available</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Low stock</span>
                <strong>{{ $stats['low_stock'] }}</strong>
                <span class="muted">10 units or fewer</span>
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
                <h2>Existing products</h2>
                <p class="muted">Filter the tenant catalog by store, category, keyword, or active state and page through larger inventories.</p>
            </div>
            <div class="actions">
                <span class="badge subtle">{{ $products->total() }} matching</span>
                <a class="button" href="{{ route('dashboard.products.create', $tenant) }}">Create new product</a>
            </div>
        </div>

        <form class="toolbar toolbar--four" method="GET" action="{{ route('dashboard.products.index', $tenant) }}" data-category-scope>
            <label class="toolbar-field">
                Search products
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Name, SKU, slug, store, retailer ID">
            </label>
            <label class="toolbar-field">
                Store
                <select name="store_id" data-category-store>
                    <option value="">All stores</option>
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}" @selected($filters['store_id'] === (string) $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="toolbar-field">
                Category
                <select name="category_id" data-category-target>
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" data-store-id="{{ $category->store_id }}" @selected($filters['category_id'] === (string) $category->id)>{{ $category->store?->name }} | {{ $category->name }}</option>
                    @endforeach
                </select>
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
                <a class="button secondary" href="{{ route('dashboard.products.index', $tenant) }}">Reset</a>
            </div>
        </form>

        <div class="table entity-table">
            @forelse ($products as $product)
                <div class="entity-row">
                    <div class="entity-main">
                        <div class="entity-title">
                            <strong>{{ $product->name }}</strong>
                            <span class="badge {{ $product->is_active ? 'success' : 'warning' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                            <span class="badge {{ $product->inventory_quantity <= 10 ? 'warning' : 'success' }}">Qty {{ $product->inventory_quantity }}</span>
                        </div>
                        <div class="entity-meta">
                            <span>{{ $product->store?->name }}</span>
                            <span>{{ $product->category?->name ?: 'Uncategorized' }}</span>
                        </div>
                        <p class="entity-copy">{{ $product->description ?: 'No product description yet.' }}</p>
                        <div class="chip-row">
                            <span class="badge subtle">{{ $product->sku }}</span>
                            <span class="badge subtle">{{ $product->store?->currency }} {{ number_format((float) $product->price, 2) }}</span>
                            <span class="badge subtle">Sale: {{ $product->sale_price ? number_format((float) $product->sale_price, 2) : 'Not set' }}</span>
                            <span class="badge subtle">Color: {{ $product->color ?: 'Not set' }}</span>
                            <span class="badge subtle">Size: {{ $product->size ?: 'Not set' }}</span>
                            <span class="badge subtle">Weight: {{ $product->shipping_weight ? number_format((float) $product->shipping_weight, 2).' kg' : 'Not set' }}</span>
                            <span class="badge subtle">Retailer ID: {{ $product->meta_retailer_id ?: 'Not linked' }}</span>
                        </div>
                        @if ($product->image_url)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="thumb">
                        @endif
                    </div>
                    <div class="entity-actions">
                        <a class="button secondary" href="{{ route('dashboard.products.edit', [$tenant, $product]) }}">Edit</a>
                        <form method="POST" action="{{ route('dashboard.products.destroy', [$tenant, $product]) }}" onsubmit="return confirm('Delete this product?');">
                            @csrf
                            @method('DELETE')
                            <button class="danger" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="muted">No products yet.</p>
            @endforelse
        </div>

        @include('layouts.panel.pagination', ['paginator' => $products])
    </section>
@endsection
