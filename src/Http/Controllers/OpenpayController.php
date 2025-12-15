<?php

/**
 * Webhook controller for Openpay payment events.
 *
 * Handles Openpay webhook confirmations and signature verification.
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

class OpenpayController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $secret = config('stag-herd.openpay.secret');
            $verification = WebhookVerifier::verifyOpenpaySignature($request, (string)$secret);

            if (!$verification['valid']) {
                Log::warning('Openpay Webhook Verification Failed: ' . ($verification['reason'] ?? 'Unknown'));

                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }

            $eventId = $verification['eventId'] ?? null;
            if ($eventId && !WebhookVerifier::isIdempotentAndStore('openpay', $eventId, config('stag-herd.idempotency_ttl'))) {
                return response()->json(['status' => 'success', 'message' => 'Event already processed']);
            }

            $data = $request->all();

            if (!isset($data['type'])) {
                return response()->json(['status' => 'ignored', 'message' => 'No event type']);
            }

            // Only process successful charges (or refunds if we handle them)
            // 'charge.succeeded', 'payout.succeeded'
            if ($data['type'] !== 'charge.succeeded') {
                // can handle checks like charge.refunded logic here if needed
                return response()->json(['status' => 'ignored']);
            }

            // Transaction object usually at $data['transaction'] for charge events
            $transaction = $data['transaction'] ?? null;
            $method_id = $transaction['id'] ?? null;

            if (!$method_id) {
                return response()->json(['status' => 'error', 'message' => 'Missing transaction ID'], 400);
            }

            // Find payment by method ID
            $payment = Payment::fromMethodID('OPENPAY', $method_id);

            // Approve
            $payment->approvePayment();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('OpenPay Webhook Error: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
