@extends('admin.layout', [
    'title' => 'Manage Stores',
    'heading' => 'Manage stores',
    'subheading' => 'Assign storefronts to tenants, configure the WhatsApp connection, and customize how the store appears in chat.',
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Store operations</p>
            <h2>Storefront management</h2>
            <p class="muted">Provision storefronts, wire up WhatsApp credentials, and keep delivery settings and catalog readiness visible in one workspace.</p>
        </div>
        <div class="summary-grid">
            <article class="summary-card">
                <span class="eyebrow">Stores</span>
                <strong>{{ $stats['total'] }}</strong>
                <span class="muted">connected storefronts</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Active</span>
                <strong>{{ $stats['active'] }}</strong>
                <span class="muted">currently selling</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Catalogs</span>
                <strong>{{ $stats['catalog_linked'] }}</strong>
                <span class="muted">linked Meta catalogs</span>
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
                    <h2>Create store</h2>
                    <p class="muted">All storefront setup fields stay here, while the list side handles search, review, and edits.</p>
                </div>
            </div>

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
                    Deliverable areas
                    <textarea name="delivery_zones_text" placeholder="700001 | Kolkata&#10;700002 | Howrah">{{ old('delivery_zones_text') }}</textarea>
                    <span class="muted">One area per line. Use `pincode | city`.</span>
                </label>

                <label>
                    Outside-delivery message
                    <textarea name="undeliverable_message" placeholder="Sorry, this area is outside our delivery zone.">{{ old('undeliverable_message') }}</textarea>
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
            <div class="table-header">
                <div>
                    <p class="eyebrow">Directory</p>
                    <h2>Existing stores</h2>
                    <p class="muted">Filter by tenant, keyword, or status before drilling into store-level edits.</p>
                </div>
                <span class="badge subtle">{{ $stores->total() }} matching</span>
            </div>

            <form class="toolbar toolbar--three" method="GET" action="{{ route('admin.stores.index') }}">
                <label class="toolbar-field">
                    Search stores
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Store, tenant, contact, brand name">
                </label>
                <label class="toolbar-field">
                    Tenant
                    <select name="tenant_id">
                        <option value="">All tenants</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected($filters['tenant_id'] === (string) $tenant->id)>{{ $tenant->name }}</option>
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
                    <a class="button secondary" href="{{ route('admin.stores.index') }}">Reset</a>
                </div>
            </form>

            <div class="table entity-table">
                @forelse ($stores as $store)
                    @php($readiness = $store->catalog_readiness)
                    <div class="entity-row">
                        <div class="entity-main">
                            <div class="entity-title">
                                <strong>{{ $store->name }}</strong>
                                <span class="badge {{ $readiness['ready'] ? 'success' : 'warning' }}">{{ $readiness['ready'] ? 'Native catalog ready' : 'Native catalog needs setup' }}</span>
                                <span class="badge {{ $store->is_active ? 'success' : 'warning' }}">{{ $store->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <div class="entity-meta">
                                <span>{{ $store->tenant?->name }}</span>
                                <span>{{ $store->slug }}</span>
                                <span>{{ $store->currency }}</span>
                            </div>
                            <p class="entity-copy">{{ $store->description ?: 'No store description added yet.' }}</p>
                            <div class="chip-row">
                                <span class="badge subtle">{{ $store->categories_count }} categories</span>
                                <span class="badge subtle">{{ $store->products_count }} products</span>
                                <span class="badge subtle">{{ $store->orders_count }} orders</span>
                                <span class="badge subtle">Catalog: {{ $store->meta_catalog_id ?: 'Not linked' }}</span>
                                <span class="badge subtle">Deliverable areas: {{ count(data_get($store->settings, 'delivery_zones', [])) }}</span>
                            </div>
                            <div class="chip-row">
                                <span class="badge {{ $readiness['checks']['phone_number_id'] ? 'success' : 'warning' }}">Phone ID</span>
                                <span class="badge {{ $readiness['checks']['access_token'] ? 'success' : 'warning' }}">Access token</span>
                                <span class="badge {{ $readiness['checks']['meta_catalog_id'] ? 'success' : 'warning' }}">Catalog ID</span>
                                <span class="badge {{ $readiness['checks']['active_products'] ? 'success' : 'warning' }}">Active products {{ $readiness['active_products'] }}</span>
                                <span class="badge {{ $readiness['checks']['mapped_products'] ? 'success' : 'warning' }}">Retailer IDs {{ $readiness['catalog_products'] }}/{{ $readiness['active_products'] }}</span>
                            </div>
                            <p class="muted">{{ $store->contact_email ?: 'No email' }} | {{ $store->contact_phone ?: $store->support_phone ?: 'No phone' }}</p>
                            <p class="muted">{{ $readiness['issues'] !== [] ? implode(' ', $readiness['issues']) : 'This store meets the app-side checks for the native WhatsApp multi-product catalog.' }}</p>
                            @if ($store->whatsapp_store_image_url)
                                <img src="{{ $store->whatsapp_store_image_url }}" alt="{{ $store->name }}" class="thumb">
                            @endif
                        </div>
                        <div class="entity-actions">
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

            @include('layouts.panel.pagination', ['paginator' => $stores])
        </article>
    </section>
@endsection
