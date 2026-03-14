@extends('admin.layout', [
    'title' => 'Edit Store',
    'heading' => 'Edit store',
    'subheading' => 'Update tenant assignment, WhatsApp connection settings, and storefront presentation for '.$store->name.'.',
])

@section('content')
    <section class="grid columns-2">
        <article class="panel">
            <h2>{{ $store->name }}</h2>

            <div class="callout">
                <div class="actions">
                    <strong>Native WhatsApp catalog</strong>
                    <span class="badge {{ $catalogReadiness['ready'] ? 'success' : 'warning' }}">
                        {{ $catalogReadiness['ready'] ? 'Ready' : 'Needs setup' }}
                    </span>
                </div>
                <div class="checklist">
                    <span class="badge {{ $catalogReadiness['checks']['phone_number_id'] ? 'success' : 'warning' }}">Phone number ID</span>
                    <span class="badge {{ $catalogReadiness['checks']['access_token'] ? 'success' : 'warning' }}">Access token</span>
                    <span class="badge {{ $catalogReadiness['checks']['meta_catalog_id'] ? 'success' : 'warning' }}">Meta catalog ID</span>
                    <span class="badge {{ $catalogReadiness['checks']['active_products'] ? 'success' : 'warning' }}">
                        Active products {{ $catalogReadiness['active_products'] }}
                    </span>
                    <span class="badge {{ $catalogReadiness['checks']['mapped_products'] ? 'success' : 'warning' }}">
                        Retailer IDs {{ $catalogReadiness['catalog_products'] }}/{{ $catalogReadiness['active_products'] }}
                    </span>
                </div>
                @if ($catalogReadiness['issues'] !== [])
                    <p class="muted">{{ implode(' ', $catalogReadiness['issues']) }}</p>
                @else
                    <p class="muted">App-side checks pass. If WhatsApp still does not show the native catalog, confirm the same Meta catalog is linked to this WhatsApp business account inside Commerce Manager.</p>
                @endif
                <p class="muted">Product feed: <a href="{{ route('feeds.meta-products') }}" target="_blank">/feeds/meta-products</a></p>
            </div>

            <form class="stack" method="POST" action="{{ route('admin.stores.update', $store) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <label>
                    Tenant
                    <select name="tenant_id" required>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected(old('tenant_id', $store->tenant_id) == $tenant->id)>{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </label>

            <div class="two-up">
                <label>
                    Store name
                    <input type="text" name="name" value="{{ old('name', $store->name) }}" required>
                </label>
                <label>
                    Slug
                    <input type="text" name="slug" value="{{ old('slug', $store->slug) }}" required>
                </label>
            </div>

            <label>
                Description
                <textarea name="description">{{ old('description', $store->description) }}</textarea>
            </label>

            <div class="two-up">
                <label>
                    Support phone
                    <input type="text" name="support_phone" value="{{ old('support_phone', $store->support_phone) }}">
                </label>
                <label>
                    Currency
                    <input type="text" name="currency" value="{{ old('currency', $store->currency) }}" maxlength="3" required>
                </label>
            </div>

            <div class="two-up">
                <label>
                    Contact email
                    <input type="email" name="contact_email" value="{{ old('contact_email', $store->contact_email) }}">
                </label>
                <label>
                    Contact phone
                    <input type="text" name="contact_phone" value="{{ old('contact_phone', $store->contact_phone) }}">
                </label>
            </div>

            <div class="two-up">
                <label>
                    WhatsApp phone number ID
                    <input type="text" name="whatsapp_phone_number_id" value="{{ old('whatsapp_phone_number_id', $store->whatsapp_phone_number_id) }}">
                </label>
                <label>
                    Business account ID
                    <input type="text" name="whatsapp_business_account_id" value="{{ old('whatsapp_business_account_id', $store->whatsapp_business_account_id) }}">
                </label>
            </div>

            <label>
                Meta catalog ID
                <input type="text" name="meta_catalog_id" value="{{ old('meta_catalog_id', $store->meta_catalog_id) }}">
            </label>

            <label>
                Meta access token
                <textarea name="meta_access_token">{{ old('meta_access_token', $store->getRawOriginal('meta_access_token')) }}</textarea>
            </label>

            <label>
                WhatsApp brand name
                <input type="text" name="whatsapp_brand_name" value="{{ old('whatsapp_brand_name', $store->whatsapp_brand_name) }}">
            </label>

            <label>
                Welcome text
                <textarea name="whatsapp_welcome_text">{{ old('whatsapp_welcome_text', $store->whatsapp_welcome_text) }}</textarea>
            </label>

            <label>
                Store intro
                <textarea name="whatsapp_store_intro">{{ old('whatsapp_store_intro', $store->whatsapp_store_intro) }}</textarea>
            </label>

            <label>
                Contact note
                <textarea name="whatsapp_contact_text">{{ old('whatsapp_contact_text', $store->whatsapp_contact_text) }}</textarea>
            </label>

            @if ($store->whatsapp_store_image_url)
                <div>
                    <p class="muted">Current store image</p>
                    <img src="{{ $store->whatsapp_store_image_url }}" alt="{{ $store->name }}" class="thumb">
                </div>
            @endif

            <label>
                Replace WhatsApp store image
                <input type="file" name="whatsapp_store_image" accept="image/*">
            </label>

            <label class="checkbox">
                <input type="checkbox" name="remove_whatsapp_store_image" value="1">
                Remove current store image
            </label>

            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $store->is_active))>
                Store is active
            </label>

                <div class="actions">
                    <button type="submit">Save store</button>
                    <a class="button secondary" href="{{ route('admin.stores.index') }}">Back</a>
                </div>
            </form>
        </article>

        <article class="panel">
            <h2>Catalog checklist</h2>
            <div class="table">
                <div class="table-row">
                    <strong>WhatsApp phone number ID</strong>
                    <span class="muted">{{ $catalogReadiness['checks']['phone_number_id'] ? 'Configured' : 'Missing' }}</span>
                </div>
                <div class="table-row">
                    <strong>Access token</strong>
                    <span class="muted">{{ $catalogReadiness['checks']['access_token'] ? 'Configured' : 'Missing' }}</span>
                </div>
                <div class="table-row">
                    <strong>Meta catalog ID</strong>
                    <span class="muted">{{ $catalogReadiness['checks']['meta_catalog_id'] ? ($store->meta_catalog_id ?: 'Configured') : 'Missing' }}</span>
                </div>
                <div class="table-row">
                    <strong>Active products</strong>
                    <span class="muted">{{ $catalogReadiness['active_products'] }}</span>
                </div>
                <div class="table-row">
                    <strong>Products with local Meta retailer IDs</strong>
                    <span class="muted">{{ $catalogReadiness['catalog_products'] }} of {{ $catalogReadiness['active_products'] }}</span>
                </div>
            </div>
        </article>
    </section>
@endsection
