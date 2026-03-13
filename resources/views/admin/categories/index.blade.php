@extends('admin.layout', [
    'title' => 'Manage Categories',
    'heading' => 'Manage categories',
    'subheading' => 'Organize each store catalog into reusable browsing groups.',
])

@section('content')
    <section class="grid columns-2">
        <article class="panel">
            <h2>Create category</h2>
            <form class="stack" method="POST" action="{{ route('admin.categories.store') }}">
                @csrf

                <label>
                    Store
                    <select name="store_id" required>
                        <option value="">Select store</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->tenant?->name }} | {{ $store->name }}</option>
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
                    <label class="checkbox" style="margin-top: 30px;">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        Category is active
                    </label>
                </div>

                <button type="submit">Create category</button>
            </form>
        </article>

        <article class="panel">
            <h2>Existing categories</h2>
            <div class="table">
                @forelse ($categories as $category)
                    <div class="table-row">
                        <div>
                            <strong>{{ $category->name }}</strong>
                            <p class="muted">{{ $category->store?->tenant?->name }} | {{ $category->store?->name }} | {{ $category->slug }}</p>
                            <p class="muted">{{ $category->products_count }} products | Sort {{ $category->sort_order }} | {{ $category->is_active ? 'Active' : 'Inactive' }}</p>
                        </div>
                        <div class="actions">
                            <a class="button secondary" href="{{ route('admin.categories.edit', $category) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Products will remain but lose the category link.');">
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
        </article>
    </section>
@endsection
