<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudApiService
{
    public function sendTextMessage(Store $store, Customer $customer, string $text): array
    {
        return $this->dispatch($store, $customer, [
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ],
        ]);
    }

    public function sendButtonMessage(
        Store $store,
        Customer $customer,
        string $body,
        array $buttons,
        ?string $footer = null,
        ?string $headerText = null
    ): array {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $body],
            'action' => [
                'buttons' => collect($buttons)->take(3)->map(function (array $button) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $button['title'],
                        ],
                    ];
                })->values()->all(),
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $headerText,
            ];
        }

        return $this->dispatch($store, $customer, [
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    public function sendImageButtonMessage(
        Store $store,
        Customer $customer,
        string $imageUrl,
        string $body,
        array $buttons,
        ?string $footer = null
    ): array {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $body],
            'action' => [
                'buttons' => collect($buttons)->take(3)->map(function (array $button) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $button['title'],
                        ],
                    ];
                })->values()->all(),
            ],
            'header' => [
                'type' => 'image',
                'image' => [
                    'link' => $imageUrl,
                ],
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        return $this->dispatch($store, $customer, [
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    public function sendListMessage(
        Store $store,
        Customer $customer,
        string $body,
        string $buttonText,
        array $sections,
        ?string $footer = null,
        ?string $headerText = null
    ): array {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $body],
            'action' => [
                'button' => $buttonText,
                'sections' => $sections,
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $headerText,
            ];
        }

        return $this->dispatch($store, $customer, [
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    public function sendMultiProductMessage(
        Store $store,
        Customer $customer,
        string $body,
        array $sections,
        ?string $footer = null,
        ?string $headerText = null
    ): array {
        $interactive = [
            'type' => 'product_list',
            'body' => ['text' => $body],
            'action' => [
                'catalog_id' => $store->meta_catalog_id,
                'sections' => $sections,
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $headerText,
            ];
        }

        return $this->dispatch($store, $customer, [
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    public function sendStructuredMessage(Store $store, Customer $customer, array $message): array
    {
        return match ($message['kind'] ?? 'text') {
            'buttons' => $this->sendButtonMessage(
                $store,
                $customer,
                $message['body'],
                $message['buttons'] ?? [],
                $message['footer'] ?? null,
                $message['header_text'] ?? null
            ),
            'image_buttons' => $this->sendImageButtonMessage(
                $store,
                $customer,
                $message['image_url'],
                trim(implode("\n", array_filter([
                    $message['header_text'] ?? null,
                    $message['body'] ?? null,
                ]))),
                $message['buttons'] ?? [],
                $message['footer'] ?? null
            ),
            'list' => $this->sendListMessage(
                $store,
                $customer,
                $message['body'],
                $message['button_text'] ?? 'Browse',
                $message['sections'] ?? [],
                $message['footer'] ?? null,
                $message['header_text'] ?? null
            ),
            'product_list' => $this->sendMultiProductMessage(
                $store,
                $customer,
                $message['body'],
                $message['sections'] ?? [],
                $message['footer'] ?? null,
                $message['header_text'] ?? null
            ),
            default => $this->sendTextMessage($store, $customer, $message['body']),
        };
    }

    protected function dispatch(Store $store, Customer $customer, array $payload): array
    {
        $token = $store->getRawOriginal('meta_access_token') ?: config('services.whatsapp.token');
        $phoneNumberId = $store->whatsapp_phone_number_id ?: config('services.whatsapp.phone_number_id');

        if (! $token || ! $phoneNumberId) {
            return [
                'dispatched' => false,
                'reason' => 'missing_credentials',
                'payload' => $payload,
            ];
        }

        $requestBody = array_merge([
            'messaging_product' => 'whatsapp',
            'to' => $customer->phone,
        ], $payload);

        $response = Http::withToken($token)
            ->acceptJson()
            ->baseUrl(rtrim(config('services.whatsapp.base_url', 'https://graph.facebook.com/v20.0'), '/'))
            ->post('/'.$phoneNumberId.'/messages', $requestBody);

        return [
            'dispatched' => $response->successful(),
            'status' => $response->status(),
            'request' => $requestBody,
            'response' => $response->json(),
            'message_id' => Arr::get($response->json(), 'messages.0.id'),
        ];
    }
}
