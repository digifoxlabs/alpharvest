@extends('admin.layout', [
    'title' => 'Manage Products',
    'heading' => 'Manage products',
    'subheading' => 'Control inventory, pricing, and store/category assignment for each sellable product.',
])

@section('content')
    <section class="grid columns-2">
        <article class="panel">
            <h2>Create product</h2>
            <form class="stack" method="POST" action="{{ route('admin.products.store') }}">
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
            <h2>Existing products</h2>
            <div class="table">
                @forelse ($products as $product)
                    <div class="table-row">
                        <div>
                            <strong>{{ $product->name }}</strong>
                            <p class="muted">{{ $product->store?->tenant?->name }} | {{ $product->store?->name }} | {{ $product->category?->name ?: 'Uncategorized' }}</p>
                            <p class="muted">{{ $product->sku }} | {{ $product->store?->currency }} {{ number_format((float) $product->price, 2) }} | Qty {{ $product->inventory_quantity }} | {{ $product->is_active ? 'Active' : 'Inactive' }}</p>
                        </div>
                        <div class="actions">
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
        </article>
    </section>
@endsection
