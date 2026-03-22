@php($isEditing = isset($store))

<form class="stack" method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <div class="two-up">
        <label>
            Store name
            <input type="text" name="name" value="{{ old('name', $store->name ?? '') }}" required>
        </label>
        <label>
            Slug
            <input type="text" name="slug" value="{{ old('slug', $store->slug ?? '') }}" required>
        </label>
    </div>

    <label>
        Description
        <textarea name="description" placeholder="What this store sells">{{ old('description', $store->description ?? '') }}</textarea>
    </label>

    <div class="two-up">
        <label>
            Support phone
            <input type="text" name="support_phone" value="{{ old('support_phone', $store->support_phone ?? '') }}">
        </label>
        <label>
            Currency
            <input type="text" name="currency" value="{{ old('currency', $store->currency ?? $tenant->currency) }}" maxlength="3" required>
        </label>
    </div>

    <div class="two-up">
        <label>
            Contact email
            <input type="email" name="contact_email" value="{{ old('contact_email', $store->contact_email ?? '') }}">
        </label>
        <label>
            Contact phone
            <input type="text" name="contact_phone" value="{{ old('contact_phone', $store->contact_phone ?? '') }}">
        </label>
    </div>

    <div class="two-up">
        <label>
            WhatsApp phone number ID
            <input type="text" name="whatsapp_phone_number_id" value="{{ old('whatsapp_phone_number_id', $store->whatsapp_phone_number_id ?? '') }}">
        </label>
        <label>
            Business account ID
            <input type="text" name="whatsapp_business_account_id" value="{{ old('whatsapp_business_account_id', $store->whatsapp_business_account_id ?? '') }}">
        </label>
    </div>

    <label>
        Meta catalog ID
        <input type="text" name="meta_catalog_id" value="{{ old('meta_catalog_id', $store->meta_catalog_id ?? '') }}" placeholder="Required for native WhatsApp catalog storefront">
    </label>

    <label>
        Meta access token
        <textarea name="meta_access_token" placeholder="Optional store-level token">{{ old('meta_access_token', $isEditing ? $store->getRawOriginal('meta_access_token') : '') }}</textarea>
    </label>

    <label>
        WhatsApp brand name
        <input type="text" name="whatsapp_brand_name" value="{{ old('whatsapp_brand_name', $store->whatsapp_brand_name ?? '') }}" placeholder="Shown in the WhatsApp menu header">
    </label>

    <label>
        Welcome text
        <textarea name="whatsapp_welcome_text" placeholder="Message shown when the customer says Hi">{{ old('whatsapp_welcome_text', $store->whatsapp_welcome_text ?? '') }}</textarea>
    </label>

    <label>
        Store intro
        <textarea name="whatsapp_store_intro" placeholder="Message shown when customer taps Visit Store">{{ old('whatsapp_store_intro', $store->whatsapp_store_intro ?? '') }}</textarea>
    </label>

    <label>
        Contact note
        <textarea name="whatsapp_contact_text" placeholder="Optional note shown with email and phone">{{ old('whatsapp_contact_text', $store->whatsapp_contact_text ?? '') }}</textarea>
    </label>

    <label>
        Deliverable areas
        <textarea name="delivery_zones_text" placeholder="700001 | Kolkata&#10;700002 | Howrah">{{ old('delivery_zones_text', $deliveryZonesText ?? '') }}</textarea>
        <span class="muted">One area per line. Use `pincode | city`.</span>
    </label>

    <label>
        Outside-delivery message
        <textarea name="undeliverable_message" placeholder="Sorry, this area is outside our delivery zone.">{{ old('undeliverable_message', $undeliverableMessage ?? '') }}</textarea>
    </label>

    @if ($isEditing && $store->whatsapp_store_image_url)
        <div>
            <p class="muted">Current store image</p>
            <img src="{{ $store->whatsapp_store_image_url }}" alt="{{ $store->name }}" class="thumb">
        </div>
    @endif

    <label>
        {{ $isEditing ? 'Replace WhatsApp store image' : 'WhatsApp store image' }}
        <input type="file" name="whatsapp_store_image" accept="image/*">
    </label>

    @if ($isEditing)
        <label class="checkbox">
            <input type="checkbox" name="remove_whatsapp_store_image" value="1">
            Remove current store image
        </label>
    @endif

    <label class="checkbox">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $store->is_active ?? true))>
        Store is active
    </label>

    <div class="actions">
        <button type="submit">{{ $submitLabel }}</button>
        <a class="button secondary" href="{{ $backUrl }}">Back</a>
    </div>
</form>
