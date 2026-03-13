@extends('admin.layout', [
    'title' => 'Edit Product',
    'heading' => 'Edit product',
    'subheading' => 'Update product pricing, inventory, media, and merchandising for '.$product->name.'.',
])

@section('content')
    <section class="panel">
        <h2>{{ $product->name }}</h2>

        <form class="stack" method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="two-up">
                <label>
                    Store
                    <select name="store_id" required>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('store_id', $product->store_id) == $store->id)>{{ $store->tenant?->name }} | {{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Category
                    <select name="product_category_id">
                        <option value="">Uncategorized</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('product_category_id', $product->product_category_id) == $category->id)>{{ $category->store?->name }} | {{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="two-up">
                <label>
                    Product name
                    <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
                </label>
                <label>
                    SKU
                    <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" required>
                </label>
            </div>

            <label>
                Meta retailer ID
                <input type="text" name="meta_retailer_id" value="{{ old('meta_retailer_id', $product->meta_retailer_id) }}">
            </label>

            <div class="two-up">
                <label>
                    Slug
                    <input type="text" name="slug" value="{{ old('slug', $product->slug) }}" required>
                </label>
                <label>
                    Inventory quantity
                    <input type="number" name="inventory_quantity" value="{{ old('inventory_quantity', $product->inventory_quantity) }}" min="0" required>
                </label>
            </div>

            <label>
                Description
                <textarea name="description">{{ old('description', $product->description) }}</textarea>
            </label>

            @if ($product->image_url)
                <div>
                    <p class="muted">Current product image</p>
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="thumb">
                </div>
            @endif

            <label>
                Replace product image
                <input type="file" name="image" accept="image/*">
            </label>

            <label class="checkbox">
                <input type="checkbox" name="remove_image" value="1">
                Remove current product image
            </label>

            <div class="two-up">
                <label>
                    Price
                    <input type="number" step="0.01" name="price" value="{{ old('price', $product->price) }}" min="0" required>
                </label>
                <label>
                    Compare-at price
                    <input type="number" step="0.01" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" min="0">
                </label>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                Product is active
            </label>

            <div class="actions">
                <button type="submit">Save product</button>
                <a class="button secondary" href="{{ route('admin.products.index') }}">Back</a>
            </div>
        </form>
    </section>
@endsection
