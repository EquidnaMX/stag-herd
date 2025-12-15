<?php

/**
 * Unified Webhook Controller.
 *
 * Handles webhook requests from multiple payment providers by delegating
 * verification and processing to the appropriate PaymentHandlers.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Payment\PaymentManager;
use Equidna\StagHerd\Support\WebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Controller for receiving and dispatching webhooks.
 */
class WebhookController extends Controller
{
    /**
     * Creates a new WebhookController instance.
     *
     * @param PaymentManager $paymentManager  Payment service.
     */
    public function __construct(
        protected PaymentManager $paymentManager
    ) {
        //
    }

    /**
     * Handles incoming webhook requests.
     *
     * @param  Request      $request   The incoming HTTP request.
     * @param  string       $provider  The provider key from the route (e.g., 'paypal').
     * @return JsonResponse            Response indicating success or failure.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        $provider = strtoupper($provider);

        try {
            $handlerClass = $this->paymentManager->getHandlerClass($provider);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid provider'], 404);
        }

        if (!is_subclass_of($handlerClass, \Equidna\StagHerd\Payment\Handlers\PaymentHandler::class)) {
            return response()->json(['message' => 'Invalid handler configuration'], 500);
        }

        // Verify Signature
        if (method_exists($handlerClass, 'verifyWebhook')) {
            $verification = $handlerClass::verifyWebhook($request);

            if (!$verification['valid']) {
                return response()->json([
                    'message' => $verification['reason'] ?? 'Invalid signature',
                ], 401);
            }

            // Deduplication
            $eventId = $verification['eventId'] ?? null;
            if ($eventId) {
                $ttl = (int) config('stag-herd.idempotency_ttl', 604800);

                if (!WebhookVerifier::isIdempotentAndStore(strtolower($provider), (string) $eventId, $ttl)) {
                    return response()->json(['message' => 'OK (Idempotent)']);
                }
            }

            // Dispatch/Process
            if (method_exists($handlerClass, 'processWebhook')) {
                try {
                    $handlerClass::processWebhook($request);

                    return response()->json(['message' => 'OK']);
                } catch (Throwable $e) {
                    Log::error("Webhook error [$provider]: " . $e->getMessage());

                    return response()->json(['message' => 'Error processing'], 500);
                }
            }
        }

        return response()->json(['message' => 'Handler not compatible'], 500);
    }
}
