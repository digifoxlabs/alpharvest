@extends('admin.layout', [
    'title' => 'Edit Store',
    'heading' => 'Edit store',
    'subheading' => 'Update tenant assignment, catalog identity, and WhatsApp connection settings for '.$store->name.'.',
])

@section('content')
    <section class="panel">
        <h2>{{ $store->name }}</h2>

        <form class="stack" method="POST" action="{{ route('admin.stores.update', $store) }}">
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
                    WhatsApp phone number ID
                    <input type="text" name="whatsapp_phone_number_id" value="{{ old('whatsapp_phone_number_id', $store->whatsapp_phone_number_id) }}">
                </label>
                <label>
                    Business account ID
                    <input type="text" name="whatsapp_business_account_id" value="{{ old('whatsapp_business_account_id', $store->whatsapp_business_account_id) }}">
                </label>
            </div>

            <label>
                Meta access token
                <textarea name="meta_access_token">{{ old('meta_access_token', $store->getRawOriginal('meta_access_token')) }}</textarea>
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
    </section>
@endsection
