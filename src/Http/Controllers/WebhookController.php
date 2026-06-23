<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Application\Actions\ProcessPaymentWebhook;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Events\PaymentWebhookFailed;
use Equidna\StagHerd\Exceptions\DuplicateWebhookException;
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
        private readonly ProcessPaymentWebhook $processPaymentWebhook,
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

        $payload = new WebhookPayloadData(
            provider: $provider,
            payload: $request->all(),
            headers: $request->headers->all(),
            query: $request->query->all(),
            rawBody: $request->getContent(),
            ipAddress: $request->ip(),
        );

        try {
            $payment = $this->processPaymentWebhook->handle($payload);

            return response()->json([
                'message' => 'Webhook processed.',
                'provider' => $provider,
                'payment_id' => $payment?->id,
                'status' => $payment?->status->value,
            ]);
        } catch (DuplicateWebhookException) {
            return response()->json([
                'message' => 'Webhook already processed.',
                'provider' => $provider,
            ]);
        } catch (Throwable $exception) {
            event(new PaymentWebhookFailed($payload, $exception));

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
