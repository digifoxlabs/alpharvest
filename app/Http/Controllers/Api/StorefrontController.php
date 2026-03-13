<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreEngineService;
use Illuminate\Http\JsonResponse;

class StorefrontController extends Controller
{
    public function __construct(protected StoreEngineService $storeEngine)
    {
    }

    public function show(Store $store): JsonResponse
    {
        return response()->json($this->storeEngine->catalogPayload($store));
    }

    public function products(Store $store): JsonResponse
    {
        return response()->json([
            'store' => $store->only(['name', 'slug', 'currency']),
            'products' => $store->products()
                ->where('is_active', true)
                ->with('category')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
