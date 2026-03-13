<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Store;
use App\Models\WebhookEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppWebhookService
{
    public function __construct(
        protected ChatbotEngineService $chatbot,
        protected WhatsAppCloudApiService $cloudApi,
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

        $body = $this->extractBody($message);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => Arr::get($message, 'type', 'text'),
            'whatsapp_message_id' => Arr::get($message, 'id'),
            'body' => $body,
            'payload' => $message,
            'sent_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();

        $reply = $this->chatbot->reply($store, $customer, $conversation, $body);
        $dispatch = $this->cloudApi->sendTextMessage($store, $customer, $reply);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $reply,
            'payload' => $dispatch,
            'sent_at' => now(),
        ]);
    }

    protected function extractBody(array $message): string
    {
        return Arr::get($message, 'text.body')
            ?? Arr::get($message, 'button.text')
            ?? Arr::get($message, 'interactive.button_reply.title')
            ?? Arr::get($message, 'interactive.list_reply.title')
            ?? '[unsupported message type]';
    }
}
