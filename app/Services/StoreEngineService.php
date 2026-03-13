<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Support\MoneyFormatter;
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
            ],
            'categories' => $categories->map(function ($category) use ($store) {
                return [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'products' => $category->products->map(fn (Product $product) => [
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'sku' => $product->sku,
                        'description' => $product->description,
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
            "Welcome to {$store->name}.",
            $store->description ?: 'Browse our catalog right from WhatsApp.',
            '',
            'Reply with:',
            'MENU to browse products',
            'ADD <sku> <qty> to add an item',
            'CART to review your cart',
            'CHECKOUT to create your order',
            'PAY to get your payment link',
            '',
            'Featured products:',
        ];

        $products = $store->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(6)
            ->get();

        foreach ($products as $product) {
            $lines[] = "{$product->sku} | {$product->name} | ".MoneyFormatter::format($product->price, $store->currency);
        }

        return implode("\n", $lines);
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

    public function activeCart(Store $store, Customer $customer, ?Conversation $conversation = null): Cart
    {
        $query = Cart::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->where('status', 'active');

        if ($conversation) {
            $query->where('conversation_id', $conversation->id);
        }

        $cart = $query->latest('id')->first();

        if ($cart) {
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
            return "Your cart is empty.\nReply MENU to browse products.";
        }

        $lines = ['Your cart:'];

        foreach ($cart->items as $item) {
            $lines[] = "{$item->quantity} x {$item->product->name} ({$item->product->sku}) = ".MoneyFormatter::format($item->total_price, $store->currency);
        }

        $lines[] = '';
        $lines[] = 'Total: '.MoneyFormatter::format($cart->total, $store->currency);
        $lines[] = 'Reply CHECKOUT when you are ready.';

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

    public function latestOpenOrder(Store $store, Customer $customer): ?Order
    {
        return Order::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->whereIn('payment_status', ['unpaid', 'pending'])
            ->latest('id')
            ->first();
    }

    protected function nextOrderNumber(Store $store): string
    {
        $count = Order::query()
            ->where('store_id', $store->id)
            ->count() + 1;

        return strtoupper($store->slug).'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
