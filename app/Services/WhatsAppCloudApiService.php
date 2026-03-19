<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsAppCloudApiService
{
    protected const INTERACTIVE_BODY_MAX = 1024;
    protected const INTERACTIVE_FOOTER_MAX = 60;
    protected const INTERACTIVE_HEADER_TEXT_MAX = 60;
    protected const INTERACTIVE_BUTTON_TITLE_MAX = 20;
    protected const LIST_BUTTON_TEXT_MAX = 20;

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
            'body' => ['text' => $this->fitInteractiveBody($body)],
            'action' => [
                'buttons' => collect($buttons)->take(3)->map(function (array $button) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $this->fitInteractiveButtonTitle($button['title']),
                        ],
                    ];
                })->values()->all(),
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $this->fitInteractiveFooter($footer)];
        }

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $this->fitInteractiveHeader($headerText),
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
            'body' => ['text' => $this->fitInteractiveBody($body)],
            'action' => [
                'buttons' => collect($buttons)->take(3)->map(function (array $button) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $this->fitInteractiveButtonTitle($button['title']),
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
            $interactive['footer'] = ['text' => $this->fitInteractiveFooter($footer)];
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
            'body' => ['text' => $this->fitInteractiveBody($body)],
            'action' => [
                'button' => $this->fitListButtonText($buttonText),
                'sections' => $sections,
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $this->fitInteractiveFooter($footer)];
        }

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $this->fitInteractiveHeader($headerText),
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
            'body' => ['text' => $this->fitInteractiveBody($body)],
            'action' => [
                'catalog_id' => $store->meta_catalog_id,
                'sections' => $sections,
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $this->fitInteractiveFooter($footer)];
        }

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $this->fitInteractiveHeader($headerText),
            ];
        }

        return $this->dispatch($store, $customer, [
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    public function sendCatalogMessage(
        Store $store,
        Customer $customer,
        string $body,
        ?string $footer = null
    ): array {
        $interactive = [
            'type' => 'catalog_message',
            'body' => ['text' => $this->fitInteractiveBody($body)],
            'action' => [
                'name' => 'catalog_message',
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $this->fitInteractiveFooter($footer)];
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
            'catalog_message' => $this->sendCatalogMessage(
                $store,
                $customer,
                $message['body'],
                $message['footer'] ?? null
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
            'recipient_type' => 'individual',
            'to' => $customer->phone,
        ], $payload);

        $response = Http::withToken($token)
            ->acceptJson()
            ->baseUrl(rtrim(config('services.whatsapp.base_url', 'https://graph.facebook.com/v20.0'), '/'))
            ->post('/'.$phoneNumberId.'/messages', $requestBody);

        $responseJson = $response->json();

        return [
            'dispatched' => $response->successful(),
            'status' => $response->status(),
            'request' => $requestBody,
            'response' => $responseJson,
            'response_body' => $response->body(),
            'error_message' => Arr::get($responseJson, 'error.message') ?: (! $response->successful() ? $response->body() : null),
            'message_id' => Arr::get($responseJson, 'messages.0.id'),
        ];
    }

    protected function fitInteractiveBody(string $value): string
    {
        return $this->fitText(trim($value), self::INTERACTIVE_BODY_MAX);
    }

    protected function fitInteractiveFooter(string $value): string
    {
        return $this->fitText(trim($value), self::INTERACTIVE_FOOTER_MAX);
    }

    protected function fitInteractiveHeader(string $value): string
    {
        return $this->fitText(trim($value), self::INTERACTIVE_HEADER_TEXT_MAX);
    }

    protected function fitInteractiveButtonTitle(string $value): string
    {
        return $this->fitText(trim($value), self::INTERACTIVE_BUTTON_TITLE_MAX);
    }

    protected function fitListButtonText(string $value): string
    {
        return $this->fitText(trim($value), self::LIST_BUTTON_TEXT_MAX);
    }

    protected function fitText(string $value, int $maxLength): string
    {
        $overflow = '...';
        $sliceLength = max($maxLength - strlen($overflow), 1);

        return Str::limit($value, $sliceLength, $overflow);
    }


public function markAsRead(Store $store, string $messageId): array
{
    $token = $store->getRawOriginal('meta_access_token') ?: config('services.whatsapp.token');
    $phoneNumberId = $store->whatsapp_phone_number_id ?: config('services.whatsapp.phone_number_id');

    if (! $token || ! $phoneNumberId) {
        return [
            'dispatched' => false,
            'reason' => 'missing_credentials',
        ];
    }

    $response = Http::withToken($token)
        ->acceptJson()
        ->baseUrl(rtrim(config('services.whatsapp.base_url', 'https://graph.facebook.com/v20.0'), '/'))
        ->post('/'.$phoneNumberId.'/messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);

    return [
        'dispatched' => $response->successful(),
        'status' => $response->status(),
        'response' => $response->json(),
    ];
}


}
