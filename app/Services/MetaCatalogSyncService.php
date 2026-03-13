<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCatalogSyncService
{
    public function __construct(protected MetaCatalogFeedService $feedService)
    {
    }

    public function syncSavedProduct(Product $product): ?array
    {
        $product->loadMissing('store', 'category');

        if (! $this->shouldSync($product)) {
            return null;
        }

        if (! $product->is_active || ! $product->store->is_active) {
            return $this->deleteProduct($product);
        }

        return $this->dispatch($product, [
            'requests' => [[
                'method' => 'UPDATE',
                'data' => $this->feedService->batchItemData($product),
            ]],
        ]);
    }

    public function deleteProduct(Product $product): ?array
    {
        $product->loadMissing('store');

        if (! $this->canCallMeta($product)) {
            return null;
        }

        return $this->dispatch($product, [
            'requests' => [[
                'method' => 'DELETE',
                'retailer_id' => $product->meta_retailer_id ?: $product->sku,
            ]],
        ]);
    }

    protected function shouldSync(Product $product): bool
    {
        return $this->canCallMeta($product) && (bool) ($product->meta_retailer_id ?: $product->sku);
    }

    protected function canCallMeta(Product $product): bool
    {
        $store = $product->store;

        if (! $store) {
            return false;
        }

        return (bool) ($store->meta_catalog_id && $this->tokenFor($product));
    }

    protected function dispatch(Product $product, array $payload): ?array
    {
        $store = $product->store;
        $token = $this->tokenFor($product);

        if (! $store?->meta_catalog_id || ! $token) {
            return null;
        }

        $url = rtrim(config('services.whatsapp.base_url', 'https://graph.facebook.com/v20.0'), '/')
            .'/'.$store->meta_catalog_id.'/items_batch';

        try {
            $response = Http::acceptJson()->post($url, array_merge($payload, [
                'access_token' => $token,
            ]));

            return [
                'successful' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->json(),
            ];
        } catch (ConnectionException $exception) {
            Log::warning('Meta catalog sync failed to reach Graph API.', [
                'product_id' => $product->id,
                'catalog_id' => $store->meta_catalog_id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function tokenFor(Product $product): ?string
    {
        return $product->store?->getRawOriginal('meta_access_token') ?: config('services.whatsapp.token');
    }
}
