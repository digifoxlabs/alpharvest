<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Store;
use App\Support\MoneyFormatter;
use Illuminate\Support\Str;

class ChatbotEngineService
{
    public function __construct(
        protected StoreEngineService $storeEngine,
        protected PaymentLinkService $paymentLinks,
    ) {
    }

    public function reply(Store $store, Customer $customer, Conversation $conversation, string $incomingText): string
    {
        $normalized = Str::of($incomingText)->trim()->lower()->squish()->toString();

        if ($normalized === '' || in_array($normalized, ['hi', 'hello', 'hey', 'menu', 'catalog', 'browse', 'products'], true)) {
            return $this->storeEngine->catalogText($store);
        }

        if ($normalized === 'help') {
            return $this->storeEngine->catalogText($store);
        }

        if ($normalized === 'cart') {
            return $this->storeEngine->cartText($store, $customer, $conversation);
        }

        if ($normalized === 'checkout') {
            $order = $this->storeEngine->checkout($store, $customer, $conversation);

            if (! $order) {
                return "Your cart is empty.\nReply MENU to see products before checkout.";
            }

            return "Order {$order->order_number} is ready.\nAmount due: ".MoneyFormatter::format($order->total, $order->currency)."\nReply PAY to receive your payment link.";
        }

        if ($normalized === 'pay') {
            $order = $this->storeEngine->latestOpenOrder($store, $customer)
                ?? $this->storeEngine->checkout($store, $customer, $conversation);

            if (! $order) {
                return "There is no open order yet.\nReply ADD <sku> <qty> to start shopping.";
            }

            $payment = $this->paymentLinks->createOrReuse($order);

            return "Secure payment link for {$order->order_number}:\n{$payment->payment_url}";
        }

        if (Str::startsWith($normalized, 'add ')) {
            preg_match('/^add\s+([a-z0-9\-_]+)(?:\s+(\d+))?$/i', $normalized, $matches);

            $lookup = $matches[1] ?? null;
            $quantity = (int) ($matches[2] ?? 1);

            if (! $lookup) {
                return 'Use ADD <sku> <qty>. Example: ADD COF-250 2';
            }

            $product = $this->storeEngine->findProduct($store, $lookup);

            if (! $product) {
                return "I couldn't find that product.\nReply MENU to see active SKUs.";
            }

            $cart = $this->storeEngine->addToCart($store, $customer, $conversation, $product, $quantity);
            $item = $cart->items->firstWhere('product_id', $product->id);

            return "{$product->name} added to your cart.\nQty: {$item->quantity}\nCart total: ".MoneyFormatter::format($cart->total, $store->currency)."\nReply CART or CHECKOUT.";
        }

        $suggestedProduct = $this->storeEngine->findProduct($store, $normalized);

        if ($suggestedProduct) {
            return "{$suggestedProduct->name} is available for ".MoneyFormatter::format($suggestedProduct->price, $store->currency).".\nReply ADD {$suggestedProduct->sku} 1 to add it.";
        }

        return "I can help you shop from this chat.\nReply MENU, ADD <sku> <qty>, CART, CHECKOUT, or PAY.";
    }
}
