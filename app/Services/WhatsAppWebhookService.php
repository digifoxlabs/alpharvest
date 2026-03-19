<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Store;
use App\Models\WebhookEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppWebhookService
{
    public function __construct(
        protected ChatbotEngineService $chatbot,
        protected WhatsAppCloudApiService $cloudApi,
        protected StoreEngineService $storeEngine,
    ) {
    }

    public function ingest(array $payload): void
    {
        foreach (Arr::get($payload, 'entry', []) as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                $value = Arr::get($change, 'value', []);
                $phoneNumberId = Arr::get($value, 'metadata.phone_number_id');
                $store = Store::query()
                    ->where('whatsapp_phone_number_id', $phoneNumberId)
                    ->first();

                $event = WebhookEvent::create([
                    'store_id' => $store?->id,
                    'provider' => 'whatsapp_cloud',
                    'event_type' => Arr::get($change, 'field', 'message'),
                    'external_id' => Arr::get($entry, 'id'),
                    'status' => 'received',
                    'payload' => $change,
                ]);

                try {
                    if (! $store) {
                        throw new RuntimeException('No store is connected to this phone_number_id.');
                    }

                    foreach (Arr::get($value, 'messages', []) as $message) {
                        $this->processInboundMessage($store, $message, $value);
                    }

                    foreach (Arr::get($value, 'statuses', []) as $status) {
                        $this->processStatusUpdate($status);
                    }

                    $event->forceFill([
                        'status' => 'processed',
                        'processed_at' => now(),
                    ])->save();
                } catch (Throwable $exception) {
                    $event->forceFill([
                        'status' => 'failed',
                        'error_message' => $exception->getMessage(),
                    ])->save();

                    Log::warning('WhatsApp webhook processing failed', [
                        'exception' => $exception->getMessage(),
                        'change' => $change,
                    ]);
                }
            }
        }
    }

    protected function processInboundMessage(Store $store, array $message, array $value): void
    {


        $messageId = Arr::get($message, 'id');

        if ($messageId) {
        $this->cloudApi->markAsRead($store, $messageId);
        }


        $from = Arr::get($message, 'from');

        if (! $from) {
            return;
        }




        $customer = Customer::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'phone' => $from,
            ],
            [
                'name' => Arr::get($value, 'contacts.0.profile.name'),
                'whatsapp_id' => $from,
                'last_message_at' => now(),
            ]
        );

        $conversation = Conversation::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'pending'])
            ->latest('last_message_at')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'status' => 'open',
                'source' => 'whatsapp',
                'last_message_at' => now(),
            ]);
        }

        $inbound = $this->extractInboundPayload($message);

        if (Arr::get($message, 'type') === 'order') {
            $catalogSync = $this->storeEngine->syncCartFromCatalogOrder(
                $store,
                $customer,
                $conversation,
                Arr::get($message, 'order', [])
            );

            $conversation->forceFill([
                'context' => array_merge($conversation->context ?? [], [
                    'catalog_sync_pending' => false,
                    'last_catalog_sync_requested' => $catalogSync['requested_count'],
                    'last_catalog_sync_matched' => $catalogSync['matched_count'],
                ]),
            ])->save();

            if ($catalogSync['matched_count'] > 0) {
                $order = $this->storeEngine->checkout($store, $customer, $conversation);

                if ($order) {
                    $this->storeEngine->prepareOrderForAdminFollowUp($order, Arr::get($message, 'order', []));
                }

                $inbound['command'] = 'catalog_order_received';
                $inbound['body'] = 'Catalog order placed';
            } else {
                $inbound['command'] = 'catalog_sync_failed';
                $inbound['body'] = 'Catalog cart could not be matched';
            }
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => Arr::get($message, 'type', 'text'),
            'whatsapp_message_id' => Arr::get($message, 'id'),
            'body' => $inbound['body'],
            'payload' => $message,
            'sent_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();

        $responses = $this->chatbot->reply(
            $store,
            $customer,
            $conversation,
            $inbound['body'],
            $inbound['command']
        );

        foreach ($responses as $response) {
            $dispatch = $this->cloudApi->sendStructuredMessage($store, $customer, $response);

            $this->storeOutboundMessage($conversation, $response, $dispatch);

            if (! ($dispatch['dispatched'] ?? false) && in_array($response['kind'] ?? null, ['catalog_message', 'product_list'], true)) {
                foreach ($this->chatbot->fallbackStorefrontMessages($store) as $fallbackResponse) {
                    $fallbackDispatch = $this->cloudApi->sendStructuredMessage($store, $customer, $fallbackResponse);
                    $this->storeOutboundMessage($conversation, $fallbackResponse, $fallbackDispatch);
                }
            }
        }
    }

    protected function extractInboundPayload(array $message): array
    {
        $command = Arr::get($message, 'interactive.button_reply.id')
            ?? Arr::get($message, 'interactive.list_reply.id')
            ?? Arr::get($message, 'button.payload');

        $body = Arr::get($message, 'text.body')
            ?? Arr::get($message, 'button.text')
            ?? Arr::get($message, 'interactive.button_reply.title')
            ?? Arr::get($message, 'interactive.list_reply.title')
            ?? $this->orderBody($message)
            ?? '[unsupported message type]';

        return [
            'command' => $command,
            'body' => $body,
        ];
    }

    protected function orderBody(array $message): ?string
    {
        if (Arr::get($message, 'type') !== 'order') {
            return null;
        }

        $items = collect(Arr::get($message, 'order.product_items', []))
            ->map(function (array $item) {
                $retailerId = Arr::get($item, 'product_retailer_id', 'unknown');
                $quantity = Arr::get($item, 'quantity', 1);

                return $quantity.' x '.$retailerId;
            })
            ->all();

        if ($items === []) {
            return 'Catalog cart shared';
        }

        return "Catalog cart shared:\n".implode("\n", $items);
    }

    protected function bodyForResponse(array $response): string
    {
        return trim(implode("\n", array_filter([
            $response['header_text'] ?? null,
            $response['body'] ?? null,
            $response['footer'] ?? null,
        ])));
    }

    protected function messageTypeForResponse(array $response): string
    {
        return match ($response['kind'] ?? 'text') {
            'buttons', 'image_buttons', 'list', 'catalog_message', 'product_list' => 'interactive',
            default => 'text',
        };
    }

    protected function storeOutboundMessage(Conversation $conversation, array $response, array $dispatch): void
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => $this->messageTypeForResponse($response),
            'whatsapp_message_id' => $dispatch['message_id'] ?? null,
            'body' => $this->bodyForResponse($response),
            'payload' => $dispatch,
            'sent_at' => $dispatch['dispatched'] ? now() : null,
        ]);
    }

    protected function processStatusUpdate(array $status): void
    {
        $message = Message::query()
            ->where('whatsapp_message_id', Arr::get($status, 'id'))
            ->first();

        if (! $message) {
            return;
        }

        $timestamp = Arr::get($status, 'timestamp');
        $at = $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : now();
        $payload = array_merge($message->payload ?? [], [
            'status_update' => $status,
        ]);

        $attributes = [
            'payload' => $payload,
        ];

        switch (Arr::get($status, 'status')) {
            case 'sent':
                $attributes['sent_at'] = $message->sent_at ?: $at;
                break;

            case 'delivered':
                $attributes['sent_at'] = $message->sent_at ?: $at;
                $attributes['delivered_at'] = $at;
                break;

            case 'read':
                $attributes['sent_at'] = $message->sent_at ?: $at;
                $attributes['delivered_at'] = $message->delivered_at ?: $at;
                $attributes['read_at'] = $at;
                break;
        }

        $message->forceFill($attributes)->save();
    }
}
