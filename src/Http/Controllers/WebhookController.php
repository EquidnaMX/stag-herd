<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Exceptions\ProviderNotRegisteredException;
use Equidna\StagHerd\Support\ProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $providers,
    ) {
        //
    }

    public function handle(Request $request, string $provider): JsonResponse
    {
        $provider = strtolower($provider);

        Log::info('StagHerd webhook received', [
            'provider' => $provider,
            'content_sha256' => hash('sha256', $request->getContent()),
            'request_id' => $request->header('x-request-id')
                ?? $request->header('x-request-idempotency-key')
                ?? $request->header('x-signature'),
            'ip_address' => $request->ip(),
        ]);

        try {
            $this->providers->get($provider);
        } catch (ProviderNotRegisteredException) {
            return response()->json([
                'message' => 'Provider not registered.',
                'provider' => $provider,
            ], 404);
        }

        try {
            return response()->json([
                'message' => 'Webhook received.',
                'provider' => $provider,
            ]);
        } catch (Throwable $exception) {
            Log::error('StagHerd webhook processing failed', [
                'provider' => $provider,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Webhook processing failed.',
            ], 500);
        }
    }
}
