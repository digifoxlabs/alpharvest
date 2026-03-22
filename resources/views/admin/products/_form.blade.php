@php($isEditing = isset($product))

<form class="stack" method="POST" action="{{ $action }}" enctype="multipart/form-data" data-category-scope>
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <div class="two-up">
        <label>
            Store
            <select name="store_id" required data-category-store>
                <option value="">Select store</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected(old('store_id', $product->store_id ?? null) == $store->id)>{{ $store->tenant?->name }} | {{ $store->name }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Category
            <select name="product_category_id" data-category-target>
                <option value="">Uncategorized</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" data-store-id="{{ $category->store_id }}" @selected(old('product_category_id', $product->product_category_id ?? null) == $category->id)>{{ $category->store?->name }} | {{ $category->name }}</option>
                @endforeach
            </select>
            <span class="muted">Categories update automatically after you choose a store.</span>
        </label>
    </div>

    <div class="two-up">
        <label>
            Product name
            <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required>
        </label>
        <label>
            SKU
            <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" required>
        </label>
    </div>

    <label>
        Meta retailer ID
        <input type="text" name="meta_retailer_id" value="{{ old('meta_retailer_id', $product->meta_retailer_id ?? '') }}" placeholder="Catalog item retailer ID for WhatsApp multi-product store">
    </label>

    <div class="two-up">
        <label>
            Slug
            <input type="text" name="slug" value="{{ old('slug', $product->slug ?? '') }}" required>
        </label>
        <label>
            Inventory quantity
            <input type="number" name="inventory_quantity" value="{{ old('inventory_quantity', $product->inventory_quantity ?? 0) }}" min="0" required>
        </label>
    </div>

    <label>
        Description
        <textarea name="description">{{ old('description', $product->description ?? '') }}</textarea>
    </label>

    <div class="two-up">
        <label>
            Price
            <input type="number" step="0.01" name="price" value="{{ old('price', $product->price ?? '') }}" min="0" required>
        </label>
        <label>
            Sale price
            <input type="number" step="0.01" name="sale_price" value="{{ old('sale_price', $product->sale_price ?? '') }}" min="0">
        </label>
    </div>

    <div class="two-up">
        <label>
            Color
            <input type="text" name="color" value="{{ old('color', $product->color ?? '') }}" placeholder="Optional color">
        </label>
        <label>
            Size
            <input type="text" name="size" value="{{ old('size', $product->size ?? '') }}" placeholder="Optional size">
        </label>
    </div>

    <label>
        Shipping weight
        <input type="number" step="0.01" name="shipping_weight" value="{{ old('shipping_weight', $product->shipping_weight ?? '') }}" min="0" placeholder="Optional weight in kg">
    </label>

    @if ($isEditing && $product->image_url)
        <div>
            <p class="muted">Current product image</p>
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="thumb">
        </div>
    @endif

    <label>
        {{ $isEditing ? 'Replace product image' : 'Product image' }}
        <input type="file" name="image" accept="image/*">
    </label>

    @if ($isEditing)
        <label class="checkbox">
            <input type="checkbox" name="remove_image" value="1">
            Remove current product image
        </label>
    @endif

    <label class="checkbox">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
        Product is active
    </label>

    <div class="actions">
        <button type="submit">{{ $submitLabel }}</button>
        <a class="button secondary" href="{{ $backUrl }}">Back</a>
    </div>
</form>
