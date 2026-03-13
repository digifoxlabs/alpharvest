@extends('admin.layout', [
    'title' => 'Edit Category',
    'heading' => 'Edit category',
    'subheading' => 'Update catalog grouping for '.$category->name.'.',
])

@section('content')
    <section class="panel">
        <h2>{{ $category->name }}</h2>

        <form class="stack" method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf
            @method('PUT')

            <label>
                Store
                <select name="store_id" required>
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}" @selected(old('store_id', $category->store_id) == $store->id)>{{ $store->tenant?->name }} | {{ $store->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="two-up">
                <label>
                    Category name
                    <input type="text" name="name" value="{{ old('name', $category->name) }}" required>
                </label>
                <label>
                    Slug
                    <input type="text" name="slug" value="{{ old('slug', $category->slug) }}" required>
                </label>
            </div>

            <label>
                Description
                <textarea name="description">{{ old('description', $category->description) }}</textarea>
            </label>

            <div class="two-up">
                <label>
                    Sort order
                    <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" required>
                </label>
                <label class="checkbox" style="margin-top: 30px;">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))>
                    Category is active
                </label>
            </div>

            <div class="actions">
                <button type="submit">Save category</button>
                <a class="button secondary" href="{{ route('admin.categories.index') }}">Back</a>
            </div>
        </form>
    </section>
@endsection
