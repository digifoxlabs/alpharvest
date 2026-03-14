@extends('admin.layout', [
    'title' => 'Manage Stores',
    'heading' => 'Manage stores',
    'subheading' => 'Assign storefronts to tenants, configure the WhatsApp connection, and customize how the store appears in chat.',
])

@section('content')
    <section class="grid columns-2">
        <article class="panel">
            <h2>Create store</h2>
            <form class="stack" method="POST" action="{{ route('admin.stores.store') }}" enctype="multipart/form-data">
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
                        Contact email
                        <input type="email" name="contact_email" value="{{ old('contact_email') }}">
                    </label>
                    <label>
                        Contact phone
                        <input type="text" name="contact_phone" value="{{ old('contact_phone') }}">
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
                    Meta catalog ID
                    <input type="text" name="meta_catalog_id" value="{{ old('meta_catalog_id') }}" placeholder="Required for native WhatsApp catalog storefront">
                </label>

                <label>
                    Meta access token
                    <textarea name="meta_access_token" placeholder="Optional store-level token">{{ old('meta_access_token') }}</textarea>
                </label>

                <label>
                    WhatsApp brand name
                    <input type="text" name="whatsapp_brand_name" value="{{ old('whatsapp_brand_name') }}" placeholder="Shown in the WhatsApp menu header">
                </label>

                <label>
                    Welcome text
                    <textarea name="whatsapp_welcome_text" placeholder="Message shown when the customer says Hi">{{ old('whatsapp_welcome_text') }}</textarea>
                </label>

                <label>
                    Store intro
                    <textarea name="whatsapp_store_intro" placeholder="Message shown when customer taps Visit Store">{{ old('whatsapp_store_intro') }}</textarea>
                </label>

                <label>
                    Contact note
                    <textarea name="whatsapp_contact_text" placeholder="Optional note shown with email and phone">{{ old('whatsapp_contact_text') }}</textarea>
                </label>

                <label>
                    WhatsApp store image
                    <input type="file" name="whatsapp_store_image" accept="image/*">
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
                        @php($readiness = $store->catalog_readiness)
                        <div>
                            <div class="actions">
                                <strong>{{ $store->name }}</strong>
                                <span class="badge {{ $readiness['ready'] ? 'success' : 'warning' }}">
                                    {{ $readiness['ready'] ? 'Native catalog ready' : 'Native catalog needs setup' }}
                                </span>
                            </div>
                            <p class="muted">{{ $store->tenant?->name }} | {{ $store->slug }} | {{ $store->currency }}</p>
                            <p class="muted">{{ $store->categories_count }} categories | {{ $store->products_count }} products | {{ $store->orders_count }} orders</p>
                            <p class="muted">Catalog: {{ $store->meta_catalog_id ?: 'Not linked' }}</p>
                            <p class="muted">{{ $store->contact_email ?: 'No email' }} | {{ $store->contact_phone ?: $store->support_phone ?: 'No phone' }}</p>
                            <div class="chip-row">
                                <span class="badge {{ $readiness['checks']['phone_number_id'] ? 'success' : 'warning' }}">Phone ID</span>
                                <span class="badge {{ $readiness['checks']['access_token'] ? 'success' : 'warning' }}">Access token</span>
                                <span class="badge {{ $readiness['checks']['meta_catalog_id'] ? 'success' : 'warning' }}">Catalog ID</span>
                                <span class="badge {{ $readiness['checks']['active_products'] ? 'success' : 'warning' }}">
                                    Active products {{ $readiness['active_products'] }}
                                </span>
                                <span class="badge {{ $readiness['checks']['mapped_products'] ? 'success' : 'warning' }}">
                                    Retailer IDs {{ $readiness['catalog_products'] }}/{{ $readiness['active_products'] }}
                                </span>
                            </div>
                            @if ($readiness['issues'] !== [])
                                <p class="muted">{{ implode(' ', $readiness['issues']) }}</p>
                            @else
                                <p class="muted">This store meets the app-side checks for the native WhatsApp multi-product catalog.</p>
                            @endif
                            @if ($store->whatsapp_store_image_url)
                                <img src="{{ $store->whatsapp_store_image_url }}" alt="{{ $store->name }}" class="thumb">
                            @endif
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
