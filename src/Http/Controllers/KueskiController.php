<?php

/**
 * Webhook controller for Kueski Pay payment events.
 *
 * Handles Kueski Pay webhook confirmations and signature verification.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class KueskiController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $secret = config('stag-herd.kueski.webhook_secret');
            $verification = WebhookVerifier::verifyKueskiSignature($request, (string)$secret);

            if (!$verification['valid']) {
                Log::warning('Kueski Webhook Verification Failed: ' . ($verification['reason'] ?? 'Unknown'));

                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }

            $eventId = $verification['eventId'] ?? null;
            if ($eventId && !WebhookVerifier::isIdempotentAndStore('kueski', $eventId, config('stag-herd.idempotency_ttl'))) {
                return response()->json(['status' => 'success', 'message' => 'Event already processed']);
            }

            $data = $request->all();

            // Check for success event
            $event = $data['event'] ?? null;
            if ($event !== 'payment.created' && $event !== 'payment.updated') {
                // Expanding to include potential 'updated' if 'created' isn't the only one
                return response()->json(['status' => 'ignored']);
            }

            $payment_id = $data['data']['id'] ?? null;
            $status = $data['data']['status'] ?? null; // e.g. 'approved' or 'paid'

            if (!$payment_id) {
                return response()->json(['status' => 'error', 'message' => 'Missing payment ID'], 400);
            }

            // Should valid status? Kueski 'approved'
            // If explicit status check is needed:
            // if ($status !== 'approved' && $status !== 'paid') { return ... }

            $payment = Payment::fromMethodID('KUESKIPAY', $payment_id);
            $payment->approvePayment();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Kueski Webhook Error: ' . $e->getMessage());

            return response()->json(['status' => 'error'], 500);
        }
    }
}
