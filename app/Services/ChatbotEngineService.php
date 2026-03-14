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

        if ($this->isAwaitingAddress($conversation) && ! $incomingCommand) {
            return $this->captureAddress($store, $customer, $conversation, $incomingText);
        }

        if ($this->isGreeting($command, $text)) {
            return [$this->mainMenuMessage($store)];
        }

        if (in_array($command, ['visit_store', 'menu', 'browse', 'products', 'storefront'], true)) {
            return $this->storefrontMessages($store, $customer, $conversation);
        }

        if (in_array($command, ['orders', 'current_order', 'cart', 'view_cart'], true)) {
            if ($this->isCatalogSyncPending($conversation)) {
                return [$this->catalogSyncPendingMessage($store)];
            }

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

        if ($command === 'save_address') {
            return [$this->promptForAddress($store, $conversation)];
        }

        if ($command === 'catalog_order_received') {
            $order = $this->storeEngine->latestOpenOrder($store, $customer)
                ?? $this->storeEngine->latestOrder($store, $customer);

            return [[
                'kind' => 'buttons',
                'header_text' => $order?->order_number ?: ($store->whatsapp_brand_name ?: $store->name),
                'body' => trim(implode("\n", array_filter([
                    $order ? 'Your order has been placed successfully.' : 'Your order has been placed.',
                    $order ? 'Total: '.MoneyFormatter::format($order->total, $order->currency) : null,
                    'Our store team will message you for address and payment.',
                ]))),
                'buttons' => [
                    ['id' => 'orders', 'title' => 'Orders'],
                    ['id' => 'visit_store', 'title' => 'Visit Store'],
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
                'footer' => 'Order received in WhatsApp.',
            ]];
        }

        if ($command === 'catalog_sync_failed') {
            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => "We could not match the catalog items shared from WhatsApp to this store yet.\nPlease open Visit Store again, add products from the catalog, and share the catalog cart once more.",
                'buttons' => [
                    ['id' => 'visit_store', 'title' => 'Visit Store'],
                    ['id' => 'orders', 'title' => 'Orders'],
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
                'footer' => 'Catalog sync needs another try.',
            ]];
        }

        if (in_array($command, ['clear_cart', 'empty_cart'], true)) {
            $cart = $this->storeEngine->clearCart($store, $customer, $conversation);

            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => $cart
                    ? "Your cart has been cleared.\nTap Visit Store to start a new basket."
                    : "There is no active cart to clear.\nTap Visit Store to browse products.",
                'buttons' => $this->mainMenuButtons(),
                'footer' => 'Cart updated.',
            ]];
        }

        if ($command === 'checkout') {
            if ($this->isCatalogSyncPending($conversation)) {
                return [$this->catalogSyncPendingMessage($store)];
            }

            if (! $this->storeEngine->deliveryDetails($customer)['is_saved']) {
                return [$this->promptForAddress($store, $conversation, 'Save delivery details before checkout.')];
            }

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
            if ($this->isCatalogSyncPending($conversation)) {
                return [$this->catalogSyncPendingMessage($store)];
            }

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
                'footer' => 'Tap Pay Now to continue.',
            ]];
        }

        if (Str::startsWith($command, 'add_to_cart:')) {
            $this->setConversationContext($conversation, [
                'catalog_sync_pending' => false,
            ]);

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

    protected function storefrontMessages(Store $store, Customer $customer, Conversation $conversation): array
    {
        $catalogReadiness = $this->storeEngine->whatsappCatalogReadiness($store);

        if ($catalogReadiness['ready'] && $catalogReadiness['checks']['mapped_products']) {
            $this->setConversationContext($conversation, [
                'catalog_sync_pending' => true,
            ]);

            return [[
                'kind' => 'product_list',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => $this->storeEngine->storeIntroText($store),
                'sections' => $this->storeEngine->whatsappCatalogSections($store),
                'footer' => 'Browse products and add them from WhatsApp.',
            ]];
        }

        if ($catalogReadiness['ready']) {
            $this->setConversationContext($conversation, [
                'catalog_sync_pending' => true,
            ]);

            return [[
                'kind' => 'catalog_message',
                'body' => $this->storeEngine->storeIntroText($store),
                'footer' => 'Open the full store inside WhatsApp.',
            ]];
        }

        $this->setConversationContext($conversation, [
            'catalog_sync_pending' => false,
        ]);

        return $this->fallbackStorefrontMessages($store);
    }

    public function fallbackStorefrontMessages(Store $store): array
    {
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
            'body' => "Added to cart.\nQty for this item: {$item->quantity}\nCart items: {$cart->items->sum('quantity')}\nCart total: ".MoneyFormatter::format($cart->total, $store->currency),
            'buttons' => [
                ['id' => 'visit_store', 'title' => 'Browse More'],
                ['id' => 'view_cart', 'title' => 'View Cart'],
                ['id' => 'checkout', 'title' => 'Checkout'],
            ],
            'footer' => 'Keep adding products or checkout.',
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
                ['id' => 'orders', 'title' => 'Orders'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ];
        } elseif ($cart->items->isNotEmpty()) {
            $buttons = [
                ['id' => 'view_cart', 'title' => 'View Cart'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'clear_cart', 'title' => 'Clear Cart'],
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
        return [[
            'kind' => 'buttons',
            'header_text' => $order->order_number,
            'body' => "Order created successfully.\nTotal: ".MoneyFormatter::format($order->total, $order->currency)."\nOur store team will message you for address and payment.",
            'buttons' => [
                ['id' => 'orders', 'title' => 'Orders'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ],
            'footer' => 'Admin follow-up pending.',
        ]];
    }

    protected function promptForAddress(Store $store, Conversation $conversation, ?string $prefix = null): array
    {
        $this->setConversationContext($conversation, [
            'awaiting_address' => true,
        ]);

        return [
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => trim(implode("\n\n", array_filter([
                $prefix,
                'Send your delivery details in this format:',
                "700001\n221B Market Road\nKolkata, West Bengal",
            ]))),
            'buttons' => [
                ['id' => 'view_cart', 'title' => 'View Cart'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ],
            'footer' => 'Pincode on line 1, address below.',
        ];
    }

    protected function captureAddress(Store $store, Customer $customer, Conversation $conversation, string $incomingText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($incomingText)) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines)));
        $pincode = $lines[0] ?? null;
        $address = trim(implode("\n", array_slice($lines, 1)));

        if (! $pincode || ! preg_match('/^\d{6}$/', $pincode) || $address === '') {
            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => "That address format was not valid.\nSend a 6-digit pincode on the first line, then the full delivery address below it.",
                'buttons' => [
                    ['id' => 'save_address', 'title' => 'Try Again'],
                    ['id' => 'view_cart', 'title' => 'View Cart'],
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
                'footer' => 'Example: 700001 + address lines',
            ]];
        }

        $customer = $this->storeEngine->saveDeliveryAddress($customer, $pincode, $address);
        $this->storeEngine->syncLatestOpenOrderDelivery($store, $customer);
        $this->setConversationContext($conversation, [
            'awaiting_address' => false,
        ]);

        return [[
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => "Delivery details saved.\n".$this->storeEngine->deliverySummary($customer)."\nOur store team will send your payment link shortly.",
            'buttons' => [
                ['id' => 'orders', 'title' => 'Orders'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ],
            'footer' => 'Address saved for this order.',
        ]];
    }

    protected function isAwaitingAddress(Conversation $conversation): bool
    {
        return (bool) data_get($conversation->context, 'awaiting_address', false);
    }

    protected function isCatalogSyncPending(Conversation $conversation): bool
    {
        return (bool) data_get($conversation->context, 'catalog_sync_pending', false);
    }

    protected function setConversationContext(Conversation $conversation, array $context): void
    {
        $current = $conversation->context ?? [];

        $conversation->forceFill([
            'context' => array_merge($current, $context),
        ])->save();
    }

    protected function catalogSyncPendingMessage(Store $store): array
    {
        return [
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => "Your catalog selections have not been synced into the bot cart yet.\nAfter choosing products in the WhatsApp catalog, use the catalog cart share/send action, then tap View Cart again.",
            'buttons' => [
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
                ['id' => 'orders', 'title' => 'Orders'],
            ],
            'footer' => 'Waiting for catalog cart sync.',
        ];
    }
}
