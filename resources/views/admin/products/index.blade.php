@extends('admin.layout', [
    'title' => 'Manage Products',
    'heading' => 'Manage products',
    'subheading' => 'Control inventory, pricing, visuals, and cart-ready WhatsApp product cards for each sellable product.',
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Inventory control</p>
            <h2>Product management</h2>
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

    <section class="management-layout">
        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Create</p>
                    <h2>Create product</h2>
                    <p class="muted">The product form stays close to the list so inventory managers can add, review, and edit without context switching.</p>
                </div>
            </div>

            <form class="stack" method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="two-up">
                    <label>
                        Store
                        <select name="store_id" required>
                            <option value="">Select store</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->tenant?->name }} | {{ $store->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Category
                        <select name="product_category_id">
                            <option value="">Uncategorized</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('product_category_id') == $category->id)>{{ $category->store?->name }} | {{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="two-up">
                    <label>
                        Product name
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        SKU
                        <input type="text" name="sku" value="{{ old('sku') }}" required>
                    </label>
                </div>

                <label>
                    Meta retailer ID
                    <input type="text" name="meta_retailer_id" value="{{ old('meta_retailer_id') }}" placeholder="Catalog item retailer ID for WhatsApp multi-product store">
                </label>

                <div class="two-up">
                    <label>
                        Slug
                        <input type="text" name="slug" value="{{ old('slug') }}" required>
                    </label>
                    <label>
                        Inventory quantity
                        <input type="number" name="inventory_quantity" value="{{ old('inventory_quantity', 0) }}" min="0" required>
                    </label>
                </div>

                <label>
                    Description
                    <textarea name="description">{{ old('description') }}</textarea>
                </label>

                <label>
                    Product image
                    <input type="file" name="image" accept="image/*">
                </label>

                <div class="two-up">
                    <label>
                        Price
                        <input type="number" step="0.01" name="price" value="{{ old('price') }}" min="0" required>
                    </label>
                    <label>
                        Compare-at price
                        <input type="number" step="0.01" name="compare_at_price" value="{{ old('compare_at_price') }}" min="0">
                    </label>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                    Product is active
                </label>

                <button type="submit">Create product</button>
            </form>
        </article>

        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Directory</p>
                    <h2>Existing products</h2>
                    <p class="muted">Filter the catalog by store, category, keyword, or active state and page through larger inventories.</p>
                </div>
                <span class="badge subtle">{{ $products->total() }} matching</span>
            </div>

            <form class="toolbar toolbar--four" method="GET" action="{{ route('admin.products.index') }}">
                <label class="toolbar-field">
                    Search products
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Name, SKU, slug, store, retailer ID">
                </label>
                <label class="toolbar-field">
                    Store
                    <select name="store_id">
                        <option value="">All stores</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($filters['store_id'] === (string) $store->id)>{{ $store->tenant?->name }} | {{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="toolbar-field">
                    Category
                    <select name="category_id">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category_id'] === (string) $category->id)>{{ $category->store?->name }} | {{ $category->name }}</option>
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
                    <a class="button secondary" href="{{ route('admin.products.index') }}">Reset</a>
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
                                <span>{{ $product->store?->tenant?->name }}</span>
                                <span>{{ $product->store?->name }}</span>
                                <span>{{ $product->category?->name ?: 'Uncategorized' }}</span>
                            </div>
                            <p class="entity-copy">{{ $product->description ?: 'No product description yet.' }}</p>
                            <div class="chip-row">
                                <span class="badge subtle">{{ $product->sku }}</span>
                                <span class="badge subtle">{{ $product->store?->currency }} {{ number_format((float) $product->price, 2) }}</span>
                                <span class="badge subtle">Retailer ID: {{ $product->meta_retailer_id ?: 'Not linked' }}</span>
                            </div>
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="thumb">
                            @endif
                        </div>
                        <div class="entity-actions">
                            <a class="button secondary" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete this product?');">
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
        </article>
    </section>
@endsection
