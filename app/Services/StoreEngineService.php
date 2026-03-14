<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Support\MoneyFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreEngineService
{
    public function catalogPayload(Store $store): array
    {
        $categories = $store->categories()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'products' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('name'),
            ])
            ->get();

        return [
            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description,
                'currency' => $store->currency,
                'support_phone' => $store->support_phone,
                'contact_email' => $store->contact_email,
                'contact_phone' => $store->contact_phone,
                'whatsapp_brand_name' => $store->whatsapp_brand_name,
                'whatsapp_welcome_text' => $store->whatsapp_welcome_text,
                'whatsapp_store_intro' => $store->whatsapp_store_intro,
                'whatsapp_store_image_url' => $store->whatsapp_store_image_url,
                'meta_catalog_id' => $store->meta_catalog_id,
            ],
            'categories' => $categories->map(function ($category) use ($store) {
                return [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'products' => $category->products->map(fn (Product $product) => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'sku' => $product->sku,
                        'meta_retailer_id' => $product->meta_retailer_id,
                        'description' => $product->description,
                        'image_url' => $product->image_url,
                        'price' => $product->price,
                        'formatted_price' => MoneyFormatter::format($product->price, $store->currency),
                        'inventory_quantity' => $product->inventory_quantity,
                    ]),
                ];
            }),
        ];
    }

    public function catalogText(Store $store): string
    {
        $lines = [
            $this->welcomeText($store),
            '',
            $this->storeIntroText($store),
            '',
            'Reply with:',
            'Visit Store',
            'Orders',
            'Contact',
            '',
            'Featured products:',
        ];

        foreach ($this->featuredProducts($store, 6) as $product) {
            $lines[] = "{$product->sku} | {$product->name} | ".MoneyFormatter::format($product->price, $store->currency);
        }

        return implode("\n", $lines);
    }

    public function welcomeText(Store $store): string
    {
        if ($store->whatsapp_welcome_text) {
            return $store->whatsapp_welcome_text;
        }

        $brand = $store->whatsapp_brand_name ?: $store->name;

        return "Hi! Welcome to {$brand}.";
    }

    public function storeIntroText(Store $store): string
    {
        if ($store->whatsapp_store_intro) {
            return $store->whatsapp_store_intro;
        }

        return $store->description ?: 'Browse our products, add items to cart, and complete your order from this WhatsApp conversation.';
    }

    public function contactText(Store $store): string
    {
        $lines = [
            'Store contact details:',
            'Email: '.($store->contact_email ?: 'Not configured'),
            'Phone: '.($store->contact_phone ?: $store->support_phone ?: 'Not configured'),
        ];

        if ($store->whatsapp_contact_text) {
            $lines[] = '';
            $lines[] = $store->whatsapp_contact_text;
        }

        return implode("\n", $lines);
    }

    public function deliveryDetails(Customer $customer): array
    {
        $delivery = data_get($customer->metadata, 'delivery', []);

        $pincode = trim((string) ($delivery['pincode'] ?? ''));
        $city = trim((string) ($delivery['city'] ?? ''));
        $address = trim((string) ($delivery['address'] ?? ''));

        return [
            'pincode' => $pincode ?: null,
            'city' => $city ?: null,
            'address' => $address ?: null,
            'is_saved' => $pincode !== '' && $city !== '' && $address !== '',
        ];
    }

    public function deliverySummary(Customer $customer): string
    {
        $delivery = $this->deliveryDetails($customer);

        if (! $delivery['is_saved']) {
            return "Deliver to pincode: not saved yet.\nTap Save Address and send pincode on line 1, city on line 2, and the full address below.";
        }

        return "Deliver to pincode: {$delivery['pincode']}\nCity: {$delivery['city']}\nAddress: {$delivery['address']}";
    }

    public function customerAddressBook(Customer $customer): array
    {
        return collect(data_get($customer->metadata, 'delivery.address_book', []))
            ->map(function (array $entry) {
                $pincode = trim((string) ($entry['pincode'] ?? ''));
                $city = trim((string) ($entry['city'] ?? ''));
                $address = trim((string) ($entry['address'] ?? ''));

                if ($pincode === '' || $city === '' || $address === '') {
                    return null;
                }

                return [
                    'id' => (string) ($entry['id'] ?? Str::lower(Str::random(10))),
                    'pincode' => $pincode,
                    'city' => $city,
                    'address' => $address,
                    'saved_at' => $entry['saved_at'] ?? null,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $entry) => $entry['saved_at'] ?? '')
            ->values()
            ->all();
    }

    public function findSavedAddress(Customer $customer, string $addressId): ?array
    {
        return collect($this->customerAddressBook($customer))
            ->first(fn (array $entry) => $entry['id'] === $addressId);
    }

    public function saveDeliveryAddress(Customer $customer, string $pincode, string $city, string $address): Customer
    {
        $pincode = trim($pincode);
        $city = trim($city);
        $address = trim($address);

        $metadata = $customer->metadata ?? [];
        $savedAt = now()->toIso8601String();
        $existing = collect($this->customerAddressBook($customer));

        $matched = $existing->first(function (array $entry) use ($pincode, $city, $address) {
            return $entry['pincode'] === $pincode
                && Str::lower($entry['city']) === Str::lower($city)
                && Str::lower($entry['address']) === Str::lower($address);
        });

        $current = [
            'id' => $matched['id'] ?? Str::lower(Str::random(10)),
            'pincode' => $pincode,
            'city' => $city,
            'address' => $address,
            'saved_at' => $savedAt,
        ];

        $addressBook = $existing
            ->reject(fn (array $entry) => $entry['id'] === $current['id'])
            ->prepend($current)
            ->take(9)
            ->values()
            ->all();

        $metadata['delivery'] = [
            'id' => $current['id'],
            'pincode' => $pincode,
            'city' => $city,
            'address' => $address,
            'saved_at' => $savedAt,
            'address_book' => $addressBook,
        ];

        $customer->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $customer->fresh();
    }

    public function useSavedAddress(Customer $customer, string $addressId): ?Customer
    {
        $address = $this->findSavedAddress($customer, $addressId);

        if (! $address) {
            return null;
        }

        return $this->saveDeliveryAddress(
            $customer,
            $address['pincode'],
            $address['city'],
            $address['address']
        );
    }

    public function deliveryZones(Store $store): array
    {
        return collect(data_get($store->settings, 'delivery_zones', []))
            ->map(function (array $zone) {
                $pincode = trim((string) ($zone['pincode'] ?? ''));
                $city = trim((string) ($zone['city'] ?? ''));

                if ($pincode === '' || $city === '') {
                    return null;
                }

                return [
                    'pincode' => $pincode,
                    'city' => $city,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function isDeliverable(Store $store, string $pincode, string $city): bool
    {
        $zones = $this->deliveryZones($store);

        if ($zones === []) {
            return true;
        }

        return collect($zones)->contains(function (array $zone) use ($pincode, $city) {
            return $zone['pincode'] === trim($pincode)
                && Str::lower($zone['city']) === Str::lower(trim($city));
        });
    }

    public function undeliverableMessage(Store $store, string $pincode, string $city): string
    {
        $configured = trim((string) data_get($store->settings, 'undeliverable_message', ''));

        if ($configured !== '') {
            return $configured;
        }

        return "We do not currently deliver to {$city} {$pincode}.\nPlease choose another address or contact the store team.";
    }

    public function featuredProducts(Store $store, int $limit = 4): Collection
    {
        return $store->products()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function whatsappCatalogReadiness(Store $store): array
    {
        $activeProducts = $store->products()
            ->where('is_active', true)
            ->count();

        $catalogProducts = $store->products()
            ->where('is_active', true)
            ->whereNotNull('meta_retailer_id')
            ->count();

        $checks = [
            'store_active' => (bool) $store->is_active,
            'phone_number_id' => (bool) ($store->whatsapp_phone_number_id ?: config('services.whatsapp.phone_number_id')),
            'access_token' => (bool) ($store->getRawOriginal('meta_access_token') ?: config('services.whatsapp.token')),
            'meta_catalog_id' => (bool) $store->meta_catalog_id,
            'active_products' => $activeProducts > 0,
            'mapped_products' => $catalogProducts > 0,
        ];

        $issues = [];

        if (! $checks['store_active']) {
            $issues[] = 'Store is inactive.';
        }

        if (! $checks['phone_number_id']) {
            $issues[] = 'WhatsApp phone number ID is missing.';
        }

        if (! $checks['access_token']) {
            $issues[] = 'Meta access token is missing.';
        }

        if (! $checks['meta_catalog_id']) {
            $issues[] = 'Meta catalog ID is missing.';
        }

        if (! $checks['active_products']) {
            $issues[] = 'No active products are available.';
        } elseif (! $checks['mapped_products']) {
            $issues[] = 'No Meta retailer IDs are assigned locally. Full catalog open can still work, but product-specific native shares may be limited until IDs are mapped.';
        }

        return [
            'ready' => $checks['store_active']
                && $checks['phone_number_id']
                && $checks['access_token']
                && $checks['meta_catalog_id']
                && $checks['active_products'],
            'checks' => $checks,
            'active_products' => $activeProducts,
            'catalog_products' => $catalogProducts,
            'issues' => $issues,
        ];
    }

    public function canSendWhatsAppCatalog(Store $store): bool
    {
        return $this->whatsappCatalogReadiness($store)['ready'];
    }

    public function whatsappCatalogSections(Store $store, int $maxSections = 10, int $maxItemsPerSection = 30): array
    {
        $categories = $store->categories()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'products' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotNull('meta_retailer_id')
                    ->orderBy('name'),
            ])
            ->get()
            ->filter(fn (ProductCategory $category) => $category->products->isNotEmpty())
            ->values();

        $categorySections = $categories->take(max(min($maxSections - 1, 3), 0))->map(function (ProductCategory $category) use ($maxItemsPerSection) {
            return [
                'title' => Str::limit($category->name, 24, ''),
                'product_items' => $category->products
                    ->take($maxItemsPerSection)
                    ->map(fn (Product $product) => [
                        'product_retailer_id' => $product->meta_retailer_id,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values();

        $allProducts = $store->products()
            ->where('is_active', true)
            ->whereNotNull('meta_retailer_id')
            ->orderBy('name')
            ->limit($maxItemsPerSection)
            ->get();

        $sections = $categorySections;

        if ($allProducts->isNotEmpty() && $sections->count() < $maxSections) {
            $sections->push([
                'title' => 'See All',
                'product_items' => $allProducts->map(fn (Product $product) => [
                    'product_retailer_id' => $product->meta_retailer_id,
                ])->values()->all(),
            ]);
        }

        $sections = $sections->all();

        if ($sections !== []) {
            return $sections;
        }

        if ($allProducts->isEmpty()) {
            return [];
        }

        return [[
            'title' => Str::limit($store->name, 24, ''),
            'product_items' => $allProducts->map(fn (Product $product) => [
                'product_retailer_id' => $product->meta_retailer_id,
            ])->values()->all(),
        ]];
    }

    public function whatsappStoreListSections(Store $store, int $maxSections = 10, int $maxRowsPerSection = 10): array
    {
        $categories = $store->categories()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'products' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('name'),
            ])
            ->get()
            ->filter(fn (ProductCategory $category) => $category->products->isNotEmpty())
            ->take($maxSections);

        $sections = $categories->map(function (ProductCategory $category) use ($store, $maxRowsPerSection) {
            return [
                'title' => Str::limit($category->name, 24, ''),
                'rows' => $category->products
                    ->take($maxRowsPerSection)
                    ->map(fn (Product $product) => [
                        'id' => 'add_to_cart:'.$product->id,
                        'title' => Str::limit($product->name, 24, ''),
                        'description' => Str::limit($product->sku.' | '.MoneyFormatter::format($product->price, $store->currency), 72, ''),
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        if ($sections !== []) {
            return $sections;
        }

        $products = $store->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit($maxRowsPerSection)
            ->get();

        if ($products->isEmpty()) {
            return [];
        }

        return [[
            'title' => Str::limit($store->name, 24, ''),
            'rows' => $products->map(fn (Product $product) => [
                'id' => 'add_to_cart:'.$product->id,
                'title' => Str::limit($product->name, 24, ''),
                'description' => Str::limit($product->sku.' | '.MoneyFormatter::format($product->price, $store->currency), 72, ''),
            ])->values()->all(),
        ]];
    }

    public function findProduct(Store $store, string $lookup): ?Product
    {
        $normalized = Str::lower(trim($lookup));

        return $store->products()
            ->where('is_active', true)
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(sku) = ?', [$normalized])
                    ->orWhereRaw('LOWER(slug) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) like ?', ['%'.$normalized.'%']);
            })
            ->orderBy('name')
            ->first();
    }

    public function findProductById(Store $store, int $productId): ?Product
    {
        return $store->products()
            ->where('is_active', true)
            ->whereKey($productId)
            ->first();
    }

    public function findProductByCatalogRetailerId(Store $store, string $retailerId): ?Product
    {
        $normalized = Str::lower(trim($retailerId));

        return $store->products()
            ->where('is_active', true)
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(meta_retailer_id) = ?', [$normalized])
                    ->orWhereRaw('LOWER(sku) = ?', [$normalized]);
            })
            ->orderBy('name')
            ->first();
    }

    public function activeCart(Store $store, Customer $customer, ?Conversation $conversation = null): Cart
    {
        $cart = Cart::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($cart) {
            if ($conversation && $cart->conversation_id !== $conversation->id) {
                $cart->forceFill([
                    'conversation_id' => $conversation->id,
                ])->save();
            }

            return $cart->load('items.product');
        }

        return Cart::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'conversation_id' => $conversation?->id,
            'status' => 'active',
            'currency' => $store->currency,
        ])->load('items.product');
    }

    public function clearCart(Store $store, Customer $customer, ?Conversation $conversation = null): ?Cart
    {
        $cart = Cart::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $cart) {
            return null;
        }

        return DB::transaction(function () use ($cart, $conversation) {
            CartItem::query()
                ->where('cart_id', $cart->id)
                ->delete();

            $cart->forceFill([
                'conversation_id' => $conversation?->id ?: $cart->conversation_id,
                'subtotal' => 0,
                'total' => 0,
            ])->save();

            return $cart->fresh(['items.product']);
        });
    }

    public function addToCart(Store $store, Customer $customer, Conversation $conversation, Product $product, int $quantity = 1): Cart
    {
        return DB::transaction(function () use ($store, $customer, $conversation, $product, $quantity) {
            $cart = $this->activeCart($store, $customer, $conversation);

            $available = max($product->inventory_quantity, 1);
            $quantity = max(1, min($quantity, $available));

            $item = CartItem::query()->firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
            ]);

            $newQuantity = $item->exists ? $item->quantity + $quantity : $quantity;
            $newQuantity = min($newQuantity, $available);

            $item->fill([
                'quantity' => $newQuantity,
                'unit_price' => $product->price,
                'total_price' => $product->price * $newQuantity,
            ])->save();

            return $this->refreshCartTotals($cart);
        });
    }

    public function syncCartFromCatalogOrder(Store $store, Customer $customer, Conversation $conversation, array $catalogOrder): array
    {
        $requestedItems = collect(Arr::get($catalogOrder, 'product_items', []));

        $items = $requestedItems
            ->map(function (array $item) use ($store) {
                $retailerId = (string) Arr::get($item, 'product_retailer_id', '');
                $product = $retailerId !== '' ? $this->findProductByCatalogRetailerId($store, $retailerId) : null;

                if (! $product) {
                    return null;
                }

                $quantity = max((int) Arr::get($item, 'quantity', 1), 1);

                return [
                    'product' => $product,
                    'quantity' => min($quantity, max($product->inventory_quantity, 1)),
                ];
            })
            ->filter()
            ->values();

        $cart = DB::transaction(function () use ($store, $customer, $conversation, $items) {
            $cart = $this->activeCart($store, $customer, $conversation);

            CartItem::query()
                ->where('cart_id', $cart->id)
                ->delete();

            foreach ($items as $item) {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['product']->price,
                    'total_price' => $item['product']->price * $item['quantity'],
                ]);
            }

            return $this->refreshCartTotals($cart);
        });

        return [
            'cart' => $items->isNotEmpty() ? $cart : null,
            'requested_count' => $requestedItems->count(),
            'matched_count' => $items->count(),
        ];
    }

    public function refreshCartTotals(Cart $cart): Cart
    {
        $cart->load('items.product');

        $subtotal = $cart->items->sum('total_price');

        $cart->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ])->save();

        return $cart->fresh(['items.product']);
    }

    public function cartText(Store $store, Customer $customer, ?Conversation $conversation = null): string
    {
        $cart = $this->activeCart($store, $customer, $conversation);

        if ($cart->items->isEmpty()) {
            $lines = ["Your cart is empty.\nTap Visit Store to browse products."];

            if ($store->meta_catalog_id) {
                $lines[] = 'If you selected items in the WhatsApp catalog, send the cart from the catalog first, then tap View Cart.';
            }

            return implode("\n", $lines);
        }

        $lines = ['Your cart:'];

        foreach ($cart->items as $item) {
            $lines[] = "{$item->quantity} x {$item->product->name} ({$item->product->sku}) = ".MoneyFormatter::format($item->total_price, $store->currency);
        }

        $lines[] = '';
        $lines[] = 'Total: '.MoneyFormatter::format($cart->total, $store->currency);
        $lines[] = $this->deliverySummary($customer);
        $lines[] = 'Choose Checkout when you are ready.';

        return implode("\n", $lines);
    }

    public function checkout(Store $store, Customer $customer, ?Conversation $conversation = null): ?Order
    {
        $cart = $this->activeCart($store, $customer, $conversation);
        $cart->load('items.product');

        if ($cart->items->isEmpty()) {
            return null;
        }

        $existingOrder = Order::query()
            ->where('cart_id', $cart->id)
            ->latest('id')
            ->first();

        if ($existingOrder) {
            return $existingOrder->load('items', 'payments');
        }

        return DB::transaction(function () use ($store, $customer, $conversation, $cart) {
            $order = Order::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'conversation_id' => $conversation?->id,
                'cart_id' => $cart->id,
                'order_number' => $this->nextOrderNumber($store),
                'status' => 'pending_payment',
                'payment_status' => 'unpaid',
                'currency' => $store->currency,
                'subtotal' => $cart->subtotal,
                'total' => $cart->total,
                'metadata' => [
                    'delivery' => $this->deliveryDetails($customer),
                ],
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                ]);
            }

            $cart->forceFill([
                'status' => 'converted',
                'checked_out_at' => now(),
            ])->save();

            return $order->load('items', 'payments');
        });
    }

    public function prepareOrderForAdminFollowUp(Order $order, array $catalogOrder = []): Order
    {
        $metadata = array_merge($order->metadata ?? [], [
            'source' => data_get($order->metadata, 'source', 'whatsapp_catalog'),
            'catalog' => array_filter([
                'catalog_id' => Arr::get($catalogOrder, 'catalog_id'),
                'item_count' => count(Arr::get($catalogOrder, 'product_items', [])),
            ], fn ($value) => $value !== null && $value !== ''),
            'admin_follow_up' => array_merge(data_get($order->metadata, 'admin_follow_up', []), [
                'address_requested_at' => data_get($order->metadata, 'admin_follow_up.address_requested_at'),
                'payment_link_sent_at' => data_get($order->metadata, 'admin_follow_up.payment_link_sent_at'),
            ]),
        ]);

        $status = data_get($metadata, 'delivery.pincode') ? 'pending_payment' : 'awaiting_address';

        $order->forceFill([
            'status' => $status,
            'payment_status' => $order->payment_status ?: 'unpaid',
            'metadata' => $metadata,
        ])->save();

        return $order->fresh(['items', 'payments', 'customer', 'store']);
    }

    public function latestOpenOrder(Store $store, Customer $customer): ?Order
    {
        return Order::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->whereIn('payment_status', ['unpaid', 'pending'])
            ->latest('id')
            ->first();
    }

    public function latestOrder(Store $store, Customer $customer): ?Order
    {
        return Order::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->first();
    }

    public function syncLatestOpenOrderDelivery(Store $store, Customer $customer): ?Order
    {
        $order = $this->latestOpenOrder($store, $customer);

        if (! $order) {
            return null;
        }

        return $this->syncOrderDelivery($order, $customer);
    }

    public function syncOrderDeliveryById(Store $store, Customer $customer, int $orderId): ?Order
    {
        $order = Order::query()
            ->where('id', $orderId)
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $order) {
            return null;
        }

        return $this->syncOrderDelivery($order, $customer);
    }

    protected function syncOrderDelivery(Order $order, Customer $customer): Order
    {
        $metadata = $order->metadata ?? [];
        $metadata['delivery'] = $this->deliveryDetails($customer);
        data_set($metadata, 'admin_follow_up.address_received_at', now()->toIso8601String());

        $order->forceFill([
            'status' => 'pending_payment',
            'metadata' => $metadata,
        ])->save();

        return $order->fresh(['items', 'payments']);
    }

    public function currentOrderText(Store $store, Customer $customer, ?Conversation $conversation = null): string
    {
        $order = $this->latestOpenOrder($store, $customer)
            ?? $this->latestOrder($store, $customer);

        if ($order) {
            $order->loadMissing('items');

            $lines = [
                "Current order: {$order->order_number}",
                'Status: '.ucfirst(str_replace('_', ' ', $order->status)),
                'Payment: '.ucfirst(str_replace('_', ' ', $order->payment_status)),
                'Amount: '.MoneyFormatter::format($order->total, $order->currency),
            ];

            if ($order->items->isNotEmpty()) {
                $lines[] = '';

                foreach ($order->items as $item) {
                    $lines[] = "{$item->quantity} x {$item->product_name} = ".MoneyFormatter::format($item->total_price, $order->currency);
                }
            }

            $delivery = data_get($order->metadata, 'delivery');

            if (data_get($delivery, 'pincode') || data_get($delivery, 'address')) {
                $lines[] = '';
                $lines[] = 'Deliver to pincode: '.(data_get($delivery, 'pincode') ?: 'Not saved');
                $lines[] = 'City: '.(data_get($delivery, 'city') ?: 'Not saved');
                $lines[] = 'Address: '.(data_get($delivery, 'address') ?: 'Not saved');
            }

            return implode("\n", $lines);
        }

        return $this->cartText($store, $customer, $conversation);
    }

    protected function nextOrderNumber(Store $store): string
    {
        $count = Order::query()
            ->where('store_id', $store->id)
            ->count() + 1;

        return strtoupper($store->slug).'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
