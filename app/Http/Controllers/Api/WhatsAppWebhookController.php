<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function __construct(protected WhatsAppWebhookService $webhooks)
    {
    }

    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if (
            $mode === 'subscribe'
            && hash_equals((string) config('services.whatsapp.verify_token'), (string) $verifyToken)
        ) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['message' => 'Invalid verify token.'], 403);
    }

    public function handle(Request $request): JsonResponse
    {
        $this->webhooks->ingest($request->all());

        return response()->json(['status' => 'accepted']);
    }
}
