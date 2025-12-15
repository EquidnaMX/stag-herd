<?php

/**
 * Webhook payload validation utilities for payment providers.
 *
 * Validates structure and required fields for webhooks from supported payment providers.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Support;

use Illuminate\Http\Request;

class WebhookPayloadValidator
{
    /**
     * Validates Stripe webhook payload structure.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string}
     */
    public static function validateStripePayload(Request $request): array
    {
        $payload = $request->json()->all();

        if (!isset($payload['id'], $payload['type'], $payload['data']['object'])) {
            return ['valid' => false, 'reason' => 'Missing required fields: id, type, or data.object'];
        }

        if (!is_string($payload['id']) || !str_starts_with($payload['id'], 'evt_')) {
            return ['valid' => false, 'reason' => 'Invalid event ID format'];
        }

        $allowedTypes = [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'charge.succeeded',
            'charge.failed',
            'checkout.session.completed',
        ];

        if (!in_array($payload['type'], $allowedTypes, true)) {
            return ['valid' => false, 'reason' => 'Unsupported event type: ' . $payload['type']];
        }

        return ['valid' => true];
    }

    /**
     * Validates PayPal webhook payload structure.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string}
     */
    public static function validatePayPalPayload(Request $request): array
    {
        $payload = $request->json()->all();

        if (!isset($payload['id'], $payload['event_type'], $payload['resource'])) {
            return ['valid' => false, 'reason' => 'Missing required fields: id, event_type, or resource'];
        }

        if (!is_string($payload['id']) || strlen($payload['id']) < 5) {
            return ['valid' => false, 'reason' => 'Invalid event ID'];
        }

        $allowedTypes = [
            'PAYMENT.CAPTURE.COMPLETED',
            'PAYMENT.CAPTURE.DENIED',
            'CHECKOUT.ORDER.APPROVED',
            'CHECKOUT.ORDER.COMPLETED',
        ];

        if (!in_array($payload['event_type'], $allowedTypes, true)) {
            return ['valid' => false, 'reason' => 'Unsupported event type: ' . $payload['event_type']];
        }

        return ['valid' => true];
    }

    /**
     * Validates Mercado Pago webhook payload structure.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string}
     */
    public static function validateMercadoPagoPayload(Request $request): array
    {
        $payload = $request->json()->all();

        if (!isset($payload['type'], $payload['data']['id'])) {
            return ['valid' => false, 'reason' => 'Missing required fields: type or data.id'];
        }

        $allowedTypes = ['payment', 'merchant_order'];

        if (!in_array($payload['type'], $allowedTypes, true)) {
            return ['valid' => false, 'reason' => 'Unsupported notification type: ' . $payload['type']];
        }

        if (!is_numeric($payload['data']['id'])) {
            return ['valid' => false, 'reason' => 'Invalid data.id format'];
        }

        return ['valid' => true];
    }

    /**
     * Validates Conekta webhook payload structure.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string}
     */
    public static function validateConektaPayload(Request $request): array
    {
        $payload = $request->json()->all();

        if (!isset($payload['type'], $payload['data']['object'])) {
            return ['valid' => false, 'reason' => 'Missing required fields: type or data.object'];
        }

        $allowedTypes = [
            'order.paid',
            'order.pending_payment',
            'order.canceled',
            'charge.paid',
            'charge.pending_payment',
        ];

        if (!in_array($payload['type'], $allowedTypes, true)) {
            return ['valid' => false, 'reason' => 'Unsupported event type: ' . $payload['type']];
        }

        return ['valid' => true];
    }

    /**
     * Validates Kueski Pay webhook payload structure.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string}
     */
    public static function validateKueskiPayload(Request $request): array
    {
        $payload = $request->json()->all();

        if (!isset($payload['event'], $payload['data'])) {
            return ['valid' => false, 'reason' => 'Missing required fields: event or data'];
        }

        $allowedEvents = [
            'payment.approved',
            'payment.rejected',
            'payment.pending',
        ];

        if (!in_array($payload['event'], $allowedEvents, true)) {
            return ['valid' => false, 'reason' => 'Unsupported event type: ' . $payload['event']];
        }

        return ['valid' => true];
    }

    /**
     * Validates Openpay webhook payload structure.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string}
     */
    public static function validateOpenpayPayload(Request $request): array
    {
        $payload = $request->json()->all();

        if (!isset($payload['type'], $payload['transaction'])) {
            return ['valid' => false, 'reason' => 'Missing required fields: type or transaction'];
        }

        $allowedTypes = [
            'charge.succeeded',
            'charge.failed',
            'charge.cancelled',
            'charge.created',
        ];

        if (!in_array($payload['type'], $allowedTypes, true)) {
            return ['valid' => false, 'reason' => 'Unsupported event type: ' . $payload['type']];
        }

        if (!isset($payload['transaction']['id'])) {
            return ['valid' => false, 'reason' => 'Missing transaction.id'];
        }

        return ['valid' => true];
    }

    /**
     * Validates Clip webhook payload structure.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string}
     */
    public static function validateClipPayload(Request $request): array
    {
        $payload = $request->json()->all();

        if (!isset($payload['event'], $payload['data']['id'])) {
            return ['valid' => false, 'reason' => 'Missing required fields: event or data.id'];
        }

        $allowedEvents = [
            'payment.created',
            'payment.completed',
            'payment.failed',
        ];

        if (!in_array($payload['event'], $allowedEvents, true)) {
            return ['valid' => false, 'reason' => 'Unsupported event type: ' . $payload['event']];
        }

        return ['valid' => true];
    }
}
