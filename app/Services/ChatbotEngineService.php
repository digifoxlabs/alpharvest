<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
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

    public function reply(
        Store $store,
        Customer $customer,
        Conversation $conversation,
        string $incomingText,
        ?string $incomingCommand = null
    ): array {
        $command = $this->normalizeCommand($incomingCommand ?: $incomingText);
        $text = Str::of($incomingText)->trim()->lower()->squish()->toString();

        if ($this->isGreeting($command, $text)) {
            return [$this->mainMenuMessage($store)];
        }

        if (in_array($command, ['visit_store', 'menu', 'browse', 'products', 'storefront'], true)) {
            return $this->storefrontMessages($store);
        }

        if (in_array($command, ['orders', 'current_order', 'cart'], true)) {
            return $this->orderMessages($store, $customer, $conversation);
        }

        if ($command === 'contact') {
            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => $this->storeEngine->contactText($store),
                'buttons' => $this->mainMenuButtons(),
                'footer' => 'Need anything else?',
            ]];
        }

        if ($command === 'checkout') {
            $order = $this->storeEngine->checkout($store, $customer, $conversation);

            if (! $order) {
                return [[
                    'kind' => 'buttons',
                    'body' => "Your cart is empty.\nTap Visit Store to add products first.",
                    'buttons' => $this->mainMenuButtons(),
                ]];
            }

            return $this->checkoutMessages($store, $order);
        }

        if (in_array($command, ['pay', 'pay_now'], true)) {
            $order = $this->storeEngine->latestOpenOrder($store, $customer)
                ?? $this->storeEngine->checkout($store, $customer, $conversation);

            if (! $order) {
                return [[
                    'kind' => 'buttons',
                    'body' => "There is no open order yet.\nTap Visit Store to start shopping.",
                    'buttons' => $this->mainMenuButtons(),
                ]];
            }

            $payment = $this->paymentLinks->createOrReuse($order);

            return [[
                'kind' => 'buttons',
                'header_text' => $order->order_number,
                'body' => "Your order is ready for payment.\nAmount due: ".MoneyFormatter::format($order->total, $order->currency)."\nSecure payment link: {$payment->payment_url}",
                'buttons' => [
                    ['id' => 'orders', 'title' => 'Orders'],
                    ['id' => 'visit_store', 'title' => 'Visit Store'],
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
                'footer' => 'Full in-chat payments require a Meta-supported payments setup. This build currently sends a secure pay link.',
            ]];
        }

        if (Str::startsWith($command, 'add_to_cart:')) {
            $productId = (int) Str::after($command, 'add_to_cart:');
            $product = $this->storeEngine->findProductById($store, $productId);

            return $this->handleProductAdd($store, $customer, $conversation, $product);
        }

        if (Str::startsWith($text, 'add ')) {
            preg_match('/^add\s+([a-z0-9\-_]+)(?:\s+(\d+))?$/i', $text, $matches);

            $lookup = $matches[1] ?? null;
            $quantity = (int) ($matches[2] ?? 1);
            $product = $lookup ? $this->storeEngine->findProduct($store, $lookup) : null;

            return $this->handleProductAdd($store, $customer, $conversation, $product, $quantity);
        }

        $suggestedProduct = $this->storeEngine->findProduct($store, $text);

        if ($suggestedProduct) {
            return [$this->productCardMessage($store, $suggestedProduct)];
        }

        return [[
            'kind' => 'buttons',
            'body' => "I can help you shop from this WhatsApp store.\nChoose one of the options below.",
            'buttons' => $this->mainMenuButtons(),
            'footer' => 'Visit Store, Orders, Contact',
        ]];
    }

    protected function isGreeting(string $command, string $text): bool
    {
        return $command === ''
            || in_array($command, ['hi', 'hello', 'hey', 'start'], true)
            || in_array($text, ['hi', 'hello', 'hey', 'start'], true);
    }

    protected function normalizeCommand(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->replace(' ', '_')
            ->replace('-', '_')
            ->squish()
            ->replace(' ', '_')
            ->toString();
    }

    protected function mainMenuButtons(): array
    {
        return [
            ['id' => 'visit_store', 'title' => 'Visit Store'],
            ['id' => 'orders', 'title' => 'Orders'],
            ['id' => 'contact', 'title' => 'Contact'],
        ];
    }

    protected function mainMenuMessage(Store $store): array
    {
        return [
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => $this->storeEngine->welcomeText($store),
            'buttons' => $this->mainMenuButtons(),
            'footer' => 'Choose an option to continue.',
        ];
    }

    protected function storefrontMessages(Store $store): array
    {
        if ($this->storeEngine->canSendWhatsAppCatalog($store)) {
            return [[
                'kind' => 'product_list',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => $this->storeEngine->storeIntroText($store),
                'sections' => $this->storeEngine->whatsappCatalogSections($store),
                'footer' => 'Browse the full store inside WhatsApp and add items natively to cart.',
            ]];
        }

        $messages = [];

        if ($store->whatsapp_store_image_url) {
            $messages[] = [
                'kind' => 'image_buttons',
                'image_url' => $store->whatsapp_store_image_url,
                'body' => trim(implode("\n", array_filter([
                    $store->whatsapp_brand_name ?: $store->name,
                    $this->storeEngine->storeIntroText($store),
                ]))),
                'buttons' => [
                    ['id' => 'orders', 'title' => 'Orders'],
                    ['id' => 'contact', 'title' => 'Contact'],
                    ['id' => 'checkout', 'title' => 'Checkout'],
                ],
                'footer' => 'Opening product list.',
            ];
        }

        $messages[] = [
            'kind' => 'list',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => $this->storeEngine->storeIntroText($store),
            'button_text' => 'Browse Products',
            'sections' => $this->storeEngine->whatsappStoreListSections($store),
            'footer' => 'Choose a product to add it to cart.',
        ];

        return $messages;
    }

    protected function productCardMessage(Store $store, Product $product): array
    {
        $body = "{$product->name}\n".MoneyFormatter::format($product->price, $store->currency);

        if ($product->description) {
            $body .= "\n".Str::limit($product->description, 90);
        }

        $message = [
            'kind' => 'buttons',
            'header_text' => $product->sku,
            'body' => $body,
            'buttons' => [
                ['id' => 'add_to_cart:'.$product->id, 'title' => 'Add to Cart'],
                ['id' => 'orders', 'title' => 'Orders'],
                ['id' => 'contact', 'title' => 'Contact'],
            ],
            'footer' => 'Select Add to Cart to continue.',
        ];

        if ($product->image_url) {
            $message['kind'] = 'image_buttons';
            $message['image_url'] = $product->image_url;
        }

        return $message;
    }

    protected function handleProductAdd(
        Store $store,
        Customer $customer,
        Conversation $conversation,
        ?Product $product,
        int $quantity = 1
    ): array {
        if (! $product) {
            return [[
                'kind' => 'buttons',
                'body' => "I couldn't find that product.\nTap Visit Store to browse the available catalog.",
                'buttons' => $this->mainMenuButtons(),
            ]];
        }

        $cart = $this->storeEngine->addToCart($store, $customer, $conversation, $product, $quantity);
        $item = $cart->items->firstWhere('product_id', $product->id);

        return [[
            'kind' => 'buttons',
            'header_text' => $product->name,
            'body' => "Added to cart.\nQty: {$item->quantity}\nCart total: ".MoneyFormatter::format($cart->total, $store->currency),
            'buttons' => [
                ['id' => 'checkout', 'title' => 'Checkout'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'orders', 'title' => 'Orders'],
            ],
            'footer' => 'Continue shopping or complete your order.',
        ]];
    }

    protected function orderMessages(Store $store, Customer $customer, Conversation $conversation): array
    {
        $body = $this->storeEngine->currentOrderText($store, $customer, $conversation);
        $cart = $this->storeEngine->activeCart($store, $customer, $conversation);
        $openOrder = $this->storeEngine->latestOpenOrder($store, $customer);

        $buttons = $this->mainMenuButtons();

        if ($openOrder) {
            $buttons = [
                ['id' => 'pay_now', 'title' => 'Pay Now'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ];
        } elseif ($cart->items->isNotEmpty()) {
            $buttons = [
                ['id' => 'checkout', 'title' => 'Checkout'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ];
        }

        return [[
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => $body,
            'buttons' => $buttons,
            'footer' => 'Order details inside WhatsApp.',
        ]];
    }

    protected function checkoutMessages(Store $store, Order $order): array
    {
        $payment = $this->paymentLinks->createOrReuse($order);

        return [[
            'kind' => 'buttons',
            'header_text' => $order->order_number,
            'body' => "Order created successfully.\nTotal: ".MoneyFormatter::format($order->total, $order->currency)."\nUse Pay Now for the secure payment step.",
            'buttons' => [
                ['id' => 'pay_now', 'title' => 'Pay Now'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ],
            'footer' => "Payment link ready: {$payment->payment_url}",
        ]];
    }
}
