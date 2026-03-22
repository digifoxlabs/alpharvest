@extends('tenant.layout', [
    'title' => $tenant->name.' Categories',
    'heading' => 'Manage categories',
    'subheading' => 'Organize each tenant store catalog into browsing groups without jumping into the admin workspace.',
    'headerBadges' => [
        $categories->total().' categories',
        strtoupper($tenant->plan).' plan',
        'Catalog structure',
    ],
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Catalog structure</p>
            <h2>{{ $tenant->name }} categories</h2>
            <p class="muted">Create browseable catalog groups, sort them per store, and keep each storefront taxonomy clean as the product count grows.</p>
        </div>
        <div class="summary-grid">
            <article class="summary-card">
                <span class="eyebrow">Categories</span>
                <strong>{{ $stats['total'] }}</strong>
                <span class="muted">total categories</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Active</span>
                <strong>{{ $stats['active'] }}</strong>
                <span class="muted">visible groups</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Stores</span>
                <strong>{{ $stats['stores'] }}</strong>
                <span class="muted">stores using categories</span>
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
                    <h2>Create category</h2>
                    <p class="muted">Set the storefront, slug, and sort order in one compact tenant card.</p>
                </div>
            </div>

            <form class="stack" method="POST" action="{{ route('dashboard.categories.store', $tenant) }}">
                @csrf

                <label>
                    Store
                    <select name="store_id" required>
                        <option value="">Select store</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="two-up">
                    <label>
                        Category name
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Slug
                        <input type="text" name="slug" value="{{ old('slug') }}" required>
                    </label>
                </div>

                <label>
                    Description
                    <textarea name="description">{{ old('description') }}</textarea>
                </label>

                <div class="two-up">
                    <label>
                        Sort order
                        <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" required>
                    </label>
                    <label class="checkbox checkbox--padded">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        Category is active
                    </label>
                </div>

                <button type="submit">Create category</button>
            </form>
        </article>

        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Directory</p>
                    <h2>Existing categories</h2>
                    <p class="muted">Filter the tenant taxonomy by store, status, or keyword before editing.</p>
                </div>
                <span class="badge subtle">{{ $categories->total() }} matching</span>
            </div>

            <form class="toolbar toolbar--three" method="GET" action="{{ route('dashboard.categories.index', $tenant) }}">
                <label class="toolbar-field">
                    Search categories
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Name, slug, store">
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
                        <option value="">All statuses</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </label>
                <div class="toolbar-actions">
                    <button type="submit">Apply filters</button>
                    <a class="button secondary" href="{{ route('dashboard.categories.index', $tenant) }}">Reset</a>
                </div>
            </form>

            <div class="table entity-table">
                @forelse ($categories as $category)
                    <div class="entity-row">
                        <div class="entity-main">
                            <div class="entity-title">
                                <strong>{{ $category->name }}</strong>
                                <span class="badge {{ $category->is_active ? 'success' : 'warning' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                                <span class="badge subtle">Sort {{ $category->sort_order }}</span>
                            </div>
                            <div class="entity-meta">
                                <span>{{ $category->store?->name }}</span>
                                <span>{{ $category->slug }}</span>
                            </div>
                            <p class="entity-copy">{{ $category->description ?: 'No category description yet.' }}</p>
                            <div class="chip-row">
                                <span class="badge subtle">{{ $category->products_count }} linked products</span>
                            </div>
                        </div>
                        <div class="entity-actions">
                            <a class="button secondary" href="{{ route('dashboard.categories.edit', [$tenant, $category]) }}">Edit</a>
                            <form method="POST" action="{{ route('dashboard.categories.destroy', [$tenant, $category]) }}" onsubmit="return confirm('Delete this category? Products will remain but lose the category link.');">
                                @csrf
                                @method('DELETE')
                                <button class="danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="muted">No categories yet.</p>
                @endforelse
            </div>

            @include('layouts.panel.pagination', ['paginator' => $categories])
        </article>
    </section>
@endsection
