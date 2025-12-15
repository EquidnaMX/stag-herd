<?php

/**
 * Webhook controller for Conekta payment events.
 *
 * Handles Conekta (OXXO) webhook confirmations and signature verification.
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

class ConektaController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $verification = WebhookVerifier::verifyConektaSignature($request);
            if (!$verification['valid']) {
                Log::warning('Conekta Webhook Verification Failed: ' . ($verification['reason'] ?? 'Unknown'));

                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }

            $eventId = $verification['eventId'] ?? null;
            if ($eventId && !WebhookVerifier::isIdempotentAndStore('conekta', $eventId, config('stag-herd.idempotency_ttl'))) {
                return response()->json(['status' => 'success', 'message' => 'Event already processed']);
            }

            $data = $request->all();

            if (!isset($data['type']) || $data['type'] != 'order.paid') {
                return response()->json(['status' => 'ignored']);
            }

            // Conekta: Validar que sea 'order.paid' y obtener object['id']
            $order_id = $data['data']['object']['id'] ?? null;

            if (!$order_id) {
                return response()->json(['status' => 'error', 'message' => 'Missing order ID'], 400);
            }

            $payment = Payment::fromMethodID('CONEKTA', $order_id);
            $payment->approvePayment();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Conekta Webhook Error: ' . $e->getMessage());

            return response()->json(['status' => 'error'], 500);
        }
    }
}
