@extends('admin.layout', [
    'title' => 'Manage Stores',
    'heading' => 'Manage stores',
    'subheading' => 'Assign storefronts to tenants and configure their WhatsApp connection details.',
])

@section('content')
    <section class="grid columns-2">
        <article class="panel">
            <h2>Create store</h2>
            <form class="stack" method="POST" action="{{ route('admin.stores.store') }}">
                @csrf

                <label>
                    Tenant
                    <select name="tenant_id" required>
                        <option value="">Select tenant</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected(old('tenant_id') == $tenant->id)>{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="two-up">
                    <label>
                        Store name
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Slug
                        <input type="text" name="slug" value="{{ old('slug') }}" required>
                    </label>
                </div>

                <label>
                    Description
                    <textarea name="description" placeholder="What this store sells">{{ old('description') }}</textarea>
                </label>

                <div class="two-up">
                    <label>
                        Support phone
                        <input type="text" name="support_phone" value="{{ old('support_phone') }}">
                    </label>
                    <label>
                        Currency
                        <input type="text" name="currency" value="{{ old('currency', 'USD') }}" maxlength="3" required>
                    </label>
                </div>

                <div class="two-up">
                    <label>
                        WhatsApp phone number ID
                        <input type="text" name="whatsapp_phone_number_id" value="{{ old('whatsapp_phone_number_id') }}">
                    </label>
                    <label>
                        Business account ID
                        <input type="text" name="whatsapp_business_account_id" value="{{ old('whatsapp_business_account_id') }}">
                    </label>
                </div>

                <label>
                    Meta access token
                    <textarea name="meta_access_token" placeholder="Optional store-level token">{{ old('meta_access_token') }}</textarea>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                    Store is active
                </label>

                <button type="submit">Create store</button>
            </form>
        </article>

        <article class="panel">
            <h2>Existing stores</h2>
            <div class="table">
                @forelse ($stores as $store)
                    <div class="table-row">
                        <div>
                            <strong>{{ $store->name }}</strong>
                            <p class="muted">{{ $store->tenant?->name }} | {{ $store->slug }} | {{ $store->currency }}</p>
                            <p class="muted">{{ $store->categories_count }} categories | {{ $store->products_count }} products | {{ $store->orders_count }} orders</p>
                        </div>
                        <div class="actions">
                            <a class="button secondary" href="{{ route('admin.stores.edit', $store) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.stores.destroy', $store) }}" onsubmit="return confirm('Delete this store and all dependent data?');">
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
        </article>
    </section>
@endsection
