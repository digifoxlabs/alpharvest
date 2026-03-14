<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;

class TenantOrderActionService
{
    public function __construct(
        protected PaymentLinkService $paymentLinks,
        protected WhatsAppCloudApiService $cloudApi,
    ) {
    }

    public function requestAddress(Order $order): void
    {
        $conversation = $this->conversationForOrder($order);
        $store = $order->store;
        $customer = $order->customer;

        $conversation->forceFill([
            'status' => 'open',
            'last_message_at' => now(),
            'context' => array_merge($conversation->context ?? [], [
                'awaiting_address' => true,
                'awaiting_order_id' => $order->id,
                'catalog_sync_pending' => false,
            ]),
        ])->save();

        $body = "Please reply with your delivery address for order {$order->order_number}.\nSend your 6-digit pincode on line 1, city on line 2, and the full address below it.";

        $dispatch = $this->cloudApi->sendTextMessage($store, $customer, $body);

        $this->storeOutboundMessage($conversation, $body, $dispatch);

        $metadata = $order->metadata ?? [];
        data_set($metadata, 'admin_follow_up.address_requested_at', now()->toIso8601String());

        $order->forceFill([
            'status' => 'awaiting_address',
            'metadata' => $metadata,
        ])->save();
    }

    public function sendPaymentLink(Order $order): void
    {
        $conversation = $this->conversationForOrder($order);
        $store = $order->store;
        $customer = $order->customer;
        $payment = $this->paymentLinks->createOrReuse($order);

        $body = "Payment for order {$order->order_number}\nAmount due: ".number_format((float) $order->total, 2).' '.$order->currency."\nSecure payment link: {$payment->payment_url}";

        $dispatch = $this->cloudApi->sendTextMessage($store, $customer, $body);

        $this->storeOutboundMessage($conversation, $body, $dispatch);

        $metadata = $order->metadata ?? [];
        data_set($metadata, 'admin_follow_up.payment_link_sent_at', now()->toIso8601String());

        $order->forceFill([
            'status' => 'payment_requested',
            'payment_status' => $order->payment_status === 'paid' ? 'paid' : 'pending',
            'metadata' => $metadata,
        ])->save();
    }

    protected function conversationForOrder(Order $order): Conversation
    {
        if ($order->conversation) {
            return $order->conversation;
        }

        return Conversation::query()
            ->firstOrCreate(
                [
                    'store_id' => $order->store_id,
                    'customer_id' => $order->customer_id,
                    'status' => 'open',
                ],
                [
                    'source' => 'whatsapp',
                    'last_message_at' => now(),
                ]
            );
    }

    protected function storeOutboundMessage(Conversation $conversation, string $body, array $dispatch): void
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'whatsapp_message_id' => $dispatch['message_id'] ?? null,
            'body' => $body,
            'payload' => $dispatch,
            'sent_at' => ($dispatch['dispatched'] ?? false) ? now() : null,
        ]);
    }
}
