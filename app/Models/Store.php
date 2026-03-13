<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'support_phone',
        'contact_email',
        'contact_phone',
        'description',
        'currency',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'meta_catalog_id',
        'meta_access_token',
        'whatsapp_brand_name',
        'whatsapp_welcome_text',
        'whatsapp_store_intro',
        'whatsapp_contact_text',
        'whatsapp_store_image_path',
        'settings',
        'is_active',
    ];

    protected $hidden = [
        'meta_access_token',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'whatsapp_store_image_url',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Order::class);
    }

    public function getWhatsappStoreImageUrlAttribute(): ?string
    {
        if (! $this->whatsapp_store_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->whatsapp_store_image_path);
    }
}
