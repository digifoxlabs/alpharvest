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
    ) {}

    public function reply(
        Store $store,
        Customer $customer,
        Conversation $conversation,
        string $incomingText,
        ?string $incomingCommand = null
    ): array {
        $command = $this->normalizeCommand($incomingCommand ?: $incomingText);
        $text = Str::of($incomingText)->trim()->lower()->squish()->toString();

        if ($this->isAwaitingPincode($conversation) && ! $incomingCommand) {
            return $this->capturePincode($store, $customer, $conversation, $incomingText);
        }

        if ($this->isAwaitingAddress($conversation) && ! $incomingCommand) {
            return $this->captureAddress($store, $customer, $conversation, $incomingText);
        }

        if ($addressId = $this->savedAddressCommand($conversation, $command, $incomingCommand)) {
            return $this->handleSavedAddressSelection($store, $customer, $conversation, $addressId);
        }

        if ($this->isGreeting($command, $text)) {
            // return [$this->mainMenuMessage($store)];

            // If already has pincode → go directly to catalog
            // if ($this->storeEngine->hasCustomerPincode($customer)) {
            //     return $this->storefrontMessages($store, $customer, $conversation);
            // }

            return [$this->promptForPincode($store, $conversation)];
        }

        if (in_array($command, ['visit_store', 'menu', 'browse', 'products', 'storefront'], true)) {
            return $this->storefrontMessages($store, $customer, $conversation);
        }

        if (in_array($command, ['orders', 'my_orders', 'current_order', 'cart', 'view_cart'], true)) {
            if ($this->isCatalogSyncPending($conversation)) {
                return [$this->catalogSyncPendingMessage($store)];
            }

            return $this->orderMessages($store, $customer, $conversation);
        }

        if ($command === 'change_pincode') {
             return [$this->promptForPincode($store, $conversation)];
        }


        if ($command === 'contact') {
            return [[
                'kind' => 'text',
                // 'header_text' => $store->whatsapp_brand_name ?: $store->name,
                // 'body' => $this->storeEngine->contactText($store),
                'body' => '*' . ($store->whatsapp_brand_name ?: $store->name) . "*\n\n" .
                                $this->storeEngine->contactText($store),
                // 'buttons' => $this->mainMenuButtons(),
                // 'footer' => 'Need anything else?',
            ]];
        }

        if (in_array($command, ['save_address', 'new_address'], true)) {
            return [$this->promptForAddress($store, $conversation)];
        }

        if ($command === 'catalog_order_received') {


        $pincode = $customer->pincode;

        if (! $pincode || ! $this->storeEngine->isDeliverable($store, $pincode)) {

            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => "*Delivery not available*\n\nWe currently do not deliver to pincode {$pincode}.\n\nPlease update your location to continue.",
                'buttons' => [
                    ['id' => 'change_pincode', 'title' => 'Change Pincode'],
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
                'footer' => 'Location required to proceed.',
            ]];
        }

        
            $order = $this->storeEngine->latestOpenOrder($store, $customer)
                ?? $this->storeEngine->latestOrder($store, $customer);

            $responses = [[
                // 'kind' => 'text',
                'header_text' => $order?->order_number ?: ($store->whatsapp_brand_name ?: $store->name),
                'body' => trim(implode("\n", array_filter([
                    $order ? 'Your order has been placed successfully.' : 'Your order has been placed.',
                    $order ? 'Total: ' . MoneyFormatter::format($order->total, $order->currency) : null,
                    'Please confirm the delivery address for this order.',
                ]))),
                // 'buttons' => [
                //     ['id' => 'my_orders', 'title' => 'My Orders'],
                //     ['id' => 'visit_store', 'title' => 'Visit Store'],
                //     ['id' => 'contact', 'title' => 'Contact'],
                // ],
                'footer' => 'Order received in WhatsApp.',
            ]];

            if (! $order) {
                return $responses;
            }

            $addressBook = $this->storeEngine->customerAddressBook($customer);

            if ($addressBook === []) {
                $responses[] = $this->promptForAddress(
                    $store,
                    $conversation,
                    "Please share your delivery address for order {$order->order_number}.",
                    $order->id
                );

                return $responses;
            }

            $responses[] = $this->savedAddressSelectionMessage($store, $conversation, $order, $addressBook);

            return $responses;
        }

        if ($command === 'catalog_sync_failed') {
            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => "We could not match the catalog items shared from WhatsApp to this store yet.\nPlease open Visit Store again, add products from the catalog, and share the catalog cart once more.",
                'buttons' => [
                    ['id' => 'visit_store', 'title' => 'Visit Store'],
                    ['id' => 'my_orders', 'title' => 'My Orders'],
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
                return [$this->promptForAddress($store, $conversation, 'Save delivery details before checkout.', null, true)];
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
                'body' => "Your order is ready for payment.\nAmount due: " . MoneyFormatter::format($order->total, $order->currency) . "\nSecure payment link: {$payment->payment_url}",
                'buttons' => [
                    ['id' => 'my_orders', 'title' => 'My Orders'],
                    ['id' => 'visit_store', 'title' => 'Visit Store'],
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
                'footer' => 'Tap Pay Now to continue.',
            ]];
        }

        if (Str::startsWith($command, 'add_to_cart:')) {
            $this->setConversationContext($conversation, [
                'awaiting_pincode' => false,
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
            'footer' => 'Visit Store, My Orders, Contact',
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

    protected function normalizeWelcomeText(string $value): string
    {
        return str_replace(
            ['Visit Store, View Catalog, or Contact', 'Visit Store, View Catalog, Contact', 'Visit Store, Orders, or Contact', 'Visit Store, Orders, Contact'],
            ['Visit Store, My Orders, or Contact', 'Visit Store, My Orders, Contact', 'Visit Store, My Orders, or Contact', 'Visit Store, My Orders, Contact'],
            $value
        );
    }

    protected function mainMenuButtons(): array
    {
        return [
            ['id' => 'visit_store', 'title' => 'Visit Store'],
            ['id' => 'my_orders', 'title' => 'My Orders'],
            ['id' => 'contact', 'title' => 'Contact'],
        ];
    }

    protected function mainMenuMessage(Store $store): array
    {
        return [
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => $this->normalizeWelcomeText($this->storeEngine->welcomeText($store)),
            'buttons' => $this->mainMenuButtons(),
            'footer' => 'Choose an option to continue.',
        ];
    }


    protected function promptForPincode(Store $store, Conversation $conversation): array
    {
        $this->setConversationContext($conversation, [
            'awaiting_pincode' => true,
        ]);

        return [
            'kind' => 'text',
            //'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => '*' . ($store->whatsapp_brand_name ?: $store->name) . "*\n\n" .
                     "Welcome 👋\n\nPlease enter your delivery pincode to continue.",
            // 'buttons' => [
            //     ['id' => 'contact', 'title' => 'Contact'],
            // ],
            'footer' => '6-digit pincode required',
        ];
    }


    protected function storefrontMessages(Store $store, Customer $customer, Conversation $conversation): array
    {
       
     $pincode = $customer->pincode; // ✅ get stored pincode
         $catalogReadiness = $this->storeEngine->whatsappCatalogReadiness($store);

        // if ($catalogReadiness['ready']) {
        //     $this->setConversationContext($conversation, [
        //         'catalog_sync_pending' => true,
        //     ]);

        //     return [
                
        //     [
        //         'kind' => 'text',
        //         'body' => "*Delivering to:* {$pincode} 🚚",
        //     ],
            
        //     [
        //         'kind' => 'catalog_message',
        //         'body' => $this->storeEngine->storeIntroText($store, $pincode),
        //         'footer' => 'Open the full store inside WhatsApp.',
        //     ]];
        // }

        $this->setConversationContext($conversation, [
            'catalog_sync_pending' => false,
        ]);

        return $this->fallbackStorefrontMessages($store, $pincode);
    }

    public function fallbackStorefrontMessages(Store $store): array
    {
        $messages = [];

        // if ($store->whatsapp_store_image_url) {
        //     $messages[] = [
        //         'kind' => 'image_buttons',
        //         'image_url' => $store->whatsapp_store_image_url,
        //         'body' => trim(implode("\n", array_filter([
        //             $store->whatsapp_brand_name ?: $store->name,
        //             $this->storeEngine->storeIntroText($store),
        //         ]))),
        //         'buttons' => [
        //             ['id' => 'my_orders', 'title' => 'My Orders'],
        //             ['id' => 'contact', 'title' => 'Contact'],
        //             ['id' => 'checkout', 'title' => 'Checkout'],
        //         ],
        //         'footer' => 'Opening product list.',
        //     ];
        // }

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
        $body = "{$product->name}\n" . MoneyFormatter::format($product->price, $store->currency);

        if ($product->description) {
            $body .= "\n" . Str::limit($product->description, 90);
        }

        $message = [
            'kind' => 'buttons',
            'header_text' => $product->sku,
            'body' => $body,
            'buttons' => [
                ['id' => 'add_to_cart:' . $product->id, 'title' => 'Add to Cart'],
                ['id' => 'my_orders', 'title' => 'My Orders'],
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
            'body' => "Added to cart.\nQty for this item: {$item->quantity}\nCart items: {$cart->items->sum('quantity')}\nCart total: " . MoneyFormatter::format($cart->total, $store->currency),
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
                ['id' => 'my_orders', 'title' => 'My Orders'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ];
        } elseif ($cart->items->isNotEmpty()) {
            $buttons = [
                ['id' => 'checkout', 'title' => 'Checkout'],
                ['id' => 'visit_store', 'title' => 'Browse More'],
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
            'body' => "Order created successfully.\nTotal: " . MoneyFormatter::format($order->total, $order->currency) . "\nOur store team will message you for address and payment.",
            'buttons' => [
                ['id' => 'my_orders', 'title' => 'My Orders'],
                ['id' => 'visit_store', 'title' => 'Visit Store'],
                ['id' => 'contact', 'title' => 'Contact'],
            ],
            'footer' => 'Admin follow-up pending.',
        ]];
    }

    protected function promptForAddress(
        Store $store,
        Conversation $conversation,
        ?string $prefix = null,
        ?int $orderId = null,
        bool $createOrderAfterSave = false
    ): array {
        $context = [
            'awaiting_address' => true,
            'address_choice_map' => null,
            'awaiting_order_creation' => $createOrderAfterSave,
        ];

        if ($orderId) {
            $context['awaiting_order_id'] = $orderId;
        }

        $this->setConversationContext($conversation, $context);

        return [
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => trim(implode("\n\n", array_filter([
                $prefix,
                'Send your delivery details in this format:',
                "781001\nGuwahati\nHno 01, Panbazar\nNear Cotton University",
            ]))),
            // 'buttons' => [
            //     ['id' => 'view_cart', 'title' => 'View Cart'],
            //     ['id' => 'visit_store', 'title' => 'Visit Store'],
            //     ['id' => 'contact', 'title' => 'Contact'],
            // ],
            'footer' => 'Pincode line 1, city line 2.',
        ];
    }

    protected function captureAddress(Store $store, Customer $customer, Conversation $conversation, string $incomingText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($incomingText)) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines)));
        $pincode = $lines[0] ?? null;
        $city = $lines[1] ?? null;
        $address = trim(implode("\n", array_slice($lines, 2)));

        if (! $pincode || ! preg_match('/^\d{6}$/', $pincode) || ! $city || $address === '') {
            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => "That address format was not valid.\nSend a 6-digit pincode on the first line, city on the second line, then the full delivery address below it.",
                'buttons' => [
                    ['id' => 'new_address', 'title' => 'Try Again'],
                    ['id' => 'view_cart', 'title' => 'View Cart'],
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
                'footer' => 'Example: 700001, Kolkata, address',
            ]];
        }

        if (! $this->storeEngine->isDeliverable($store, $pincode, $city)) {
            return [$this->undeliverableAreaMessage($store, $pincode, $city)];
        }

        $customer = $this->storeEngine->saveDeliveryAddress($customer, $pincode, $city, $address);
        $requestedOrderId = (int) data_get($conversation->context, 'awaiting_order_id', 0);
        $order = null;
        $orderCreatedAfterSave = false;

        if ($requestedOrderId > 0) {
            $order = $this->storeEngine->syncOrderDeliveryById($store, $customer, $requestedOrderId)
                ?? $this->storeEngine->syncLatestOpenOrderDelivery($store, $customer);
        } elseif ((bool) data_get($conversation->context, 'awaiting_order_creation', false)) {
            $order = $this->storeEngine->checkout($store, $customer, $conversation);
            $orderCreatedAfterSave = (bool) $order;
        } else {
            $order = $this->storeEngine->syncLatestOpenOrderDelivery($store, $customer);
        }

        $this->setConversationContext($conversation, [
            'awaiting_address' => false,
            'awaiting_order_id' => null,
            'address_choice_map' => null,
            'awaiting_order_creation' => false,
        ]);

        return [[
            'kind' => 'buttons',
            'header_text' => $order?->order_number ?: ($store->whatsapp_brand_name ?: $store->name),
            'body' => trim(implode("\n", array_filter([
                'Delivery details saved.',
                $this->storeEngine->deliverySummary($customer),
                $orderCreatedAfterSave && $order ? "Order created: {$order->order_number}" : null,
                'Our store team will send your payment link shortly.',
            ]))),
            'buttons' => [
                ['id' => 'my_orders', 'title' => 'My Orders'],
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

    protected function savedAddressCommand(Conversation $conversation, string $command, ?string $incomingCommand): ?string
    {
        if (Str::startsWith($command, 'select_address:')) {
            return (string) Str::after($command, 'select_address:');
        }

        $choiceMap = data_get($conversation->context, 'address_choice_map', []);

        if ($incomingCommand === null && preg_match('/^\d+$/', $command) && isset($choiceMap[$command])) {
            return (string) $choiceMap[$command];
        }

        return null;
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
                ['id' => 'my_orders', 'title' => 'My Orders'],
            ],
            'footer' => 'Waiting for catalog cart sync.',
        ];
    }

    protected function savedAddressSelectionMessage(Store $store, Conversation $conversation, Order $order, array $addressBook): array
    {
        $rows = [];
        $choiceMap = [];

        foreach (collect($addressBook)->take(8)->values() as $index => $address) {
            $number = (string) ($index + 1);
            $choiceMap[$number] = $address['id'];
            $rows[] = [
                'id' => 'select_address:' . $address['id'],
                'title' => Str::limit($number . '. ' . $address['pincode'] . ' ' . $address['city'], 24, ''),
                'description' => Str::limit($address['address'], 72, ''),
            ];
        }

        $rows[] = [
            'id' => 'new_address',
            'title' => 'New Address',
            'description' => 'Use a different delivery address',
        ];

        $this->setConversationContext($conversation, [
            'awaiting_address' => false,
            'awaiting_order_id' => $order->id,
            'address_choice_map' => $choiceMap,
        ]);

        return [
            'kind' => 'list',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => "Choose a saved delivery address for order {$order->order_number}, or select New Address.",
            'button_text' => 'Choose Address',
            'sections' => [[
                'title' => 'Saved addresses',
                'rows' => $rows,
            ]],
            'footer' => 'Reply with 1, 2, 3 or tap an address.',
        ];
    }

    protected function handleSavedAddressSelection(Store $store, Customer $customer, Conversation $conversation, string $addressId): array
    {
        $savedAddress = $this->storeEngine->findSavedAddress($customer, $addressId);

        if (! $savedAddress) {
            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => "That saved address is no longer available.\nPlease choose another address or add a new one.",
                'buttons' => [
                    ['id' => 'new_address', 'title' => 'New Address'],
                    ['id' => 'my_orders', 'title' => 'My Orders'],
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
                'footer' => 'Address selection expired.',
            ]];
        }

        if (! $this->storeEngine->isDeliverable($store, $savedAddress['pincode'], $savedAddress['city'])) {
            return [$this->undeliverableAreaMessage($store, $savedAddress['pincode'], $savedAddress['city'])];
        }

        $customer = $this->storeEngine->useSavedAddress($customer, $addressId) ?? $customer;
        $requestedOrderId = (int) data_get($conversation->context, 'awaiting_order_id', 0);

        if ($requestedOrderId > 0) {
            $this->storeEngine->syncOrderDeliveryById($store, $customer, $requestedOrderId)
                ?? $this->storeEngine->syncLatestOpenOrderDelivery($store, $customer);
        } else {
            $this->storeEngine->syncLatestOpenOrderDelivery($store, $customer);
        }

        $this->setConversationContext($conversation, [
            'awaiting_address' => false,
            'awaiting_order_id' => null,
            'address_choice_map' => null,
        ]);

        return [[
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => "Address confirmed.\n" . $this->storeEngine->deliverySummary($customer) . "\nOur store team will send your payment link shortly.",
            'buttons' => [
                ['id' => 'my_orders', 'title' => 'My Orders'],
                ['id' => 'contact', 'title' => 'Contact'],
            ],
            'footer' => 'Saved address linked to this order.',
        ]];
    }

    protected function undeliverableAreaMessage(Store $store, string $pincode, string $city): array
    {
        return [
            'kind' => 'buttons',
            'header_text' => $store->whatsapp_brand_name ?: $store->name,
            'body' => $this->storeEngine->undeliverableMessage($store, $pincode, $city),
            'buttons' => [
                ['id' => 'new_address', 'title' => 'New Address'],
                ['id' => 'my_orders', 'title' => 'My Orders'],
                ['id' => 'contact', 'title' => 'Contact'],
            ],
            'footer' => 'Outside delivery area.',
        ];
    }


    protected function capturePincode(
        Store $store,
        Customer $customer,
        Conversation $conversation,
        string $incomingText
    ): array {

        $pincode = trim($incomingText);

        // ✅ Validate format
        if (! preg_match('/^\d{6}$/', $pincode)) {
            return [[
                'kind' => 'text',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => "Please enter a valid 6-digit pincode.",
                // 'buttons' => [
                //     ['id' => 'contact', 'title' => 'Contact'],
                // ],
            ]];
        }

        // ✅ Check delivery (PINCODE ONLY)
        if (! $this->storeEngine->isDeliverable($store, $pincode)) {

            // ✅ Clear state
            $this->setConversationContext($conversation, [
                'awaiting_pincode' => false,
            ]);

            return [[
                'kind' => 'buttons',
                'header_text' => $store->whatsapp_brand_name ?: $store->name,
                'body' => $this->storeEngine->undeliverableMessage($store, $pincode),
                'buttons' => [
                    ['id' => 'contact', 'title' => 'Contact'],
                ],
            ]];
        }

        // ✅ Save pincode
        $this->storeEngine->saveCustomerPincode($customer, $pincode);

        // ✅ Clear state
        $this->setConversationContext($conversation, [
            'awaiting_pincode' => false,
        ]);

        // 🚀 IMPORTANT: directly open catalog
        return $this->storefrontMessages($store, $customer, $conversation);
    }


    protected function isAwaitingPincode(Conversation $conversation): bool
    {
        return (bool) data_get($conversation->context, 'awaiting_pincode', false);
    }
}
