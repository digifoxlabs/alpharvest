<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudApiService
{
    public function sendTextMessage(Store $store, Customer $customer, string $text): array
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
                'to' => $customer->phone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $text,
                ],
            ]);

        return [
            'dispatched' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json(),
        ];
    }
}
