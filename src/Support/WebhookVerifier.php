<?php

/**
 * Webhook signature verification utilities for payment providers.
 *
 * Provides cryptographic signature validation for webhooks from Stripe, PayPal, Mercado Pago,
 * Conekta, Kueski Pay, and Openpay. Includes idempotency checking to prevent duplicate processing.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Support;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WebhookVerifier
{
    /**
     * Verifies Stripe webhook signature.
     *
     * @param  Request $request
     * @param  string  $secret
     * @param  int     $tolerance   Allowed time drift in seconds.
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyStripeSignature(Request $request, string $secret, int $tolerance = 300): array
    {
        try {
            $sigHeader = $request->header('Stripe-Signature');
            if (!$sigHeader || !$secret) {
                return ['valid' => false, 'reason' => 'Missing signature or secret'];
            }

            $parts = [];
            foreach (explode(',', $sigHeader) as $kv) {
                $kv = trim($kv);
                if ($kv === '' || !str_contains($kv, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $kv, 2));
                $parts[$k] = $v;
            }

            $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
            $signature = $parts['v1'] ?? '';
            if ($timestamp <= 0 || $signature === '') {
                return ['valid' => false, 'reason' => 'Malformed signature header'];
            }

            if (abs(time() - $timestamp) > $tolerance) {
                return ['valid' => false, 'reason' => 'Timestamp outside tolerance'];
            }

            $payload = $request->getContent();
            $signedPayload = $timestamp . '.' . $payload;
            $computed = hash_hmac('sha256', $signedPayload, $secret);

            if (!hash_equals($computed, $signature)) {
                return ['valid' => false, 'reason' => 'Signature mismatch'];
            }

            $json = json_decode($payload, true) ?: [];
            $eventId = $json['id'] ?? null;

            return ['valid' => true, 'eventId' => $eventId];
        } catch (Exception $e) {
            return ['valid' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Verifies PayPal webhook via remote API.
     *
     * @param  Request $request
     * @param  string  $webhookId
     * @param  bool    $sandbox
     * @param  string  $clientId
     * @param  string  $clientSecret
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyPayPalSignature(Request $request, string $webhookId, bool $sandbox, string $clientId, string $clientSecret): array
    {
        try {
            if (!$webhookId || !$clientId || !$clientSecret) {
                return ['valid' => false, 'reason' => 'Missing PayPal configuration'];
            }

            $authAlgo = $request->header('PAYPAL-AUTH-ALGO');
            $certUrl = $request->header('PAYPAL-CERT-URL');
            $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID');
            $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG');
            $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME');

            if (!$authAlgo || !$certUrl || !$transmissionId || !$transmissionSig || !$transmissionTime) {
                return ['valid' => false, 'reason' => 'Missing transmission headers'];
            }

            $cacheKey = 'paypal_oauth_token';
            $token = Cache::get($cacheKey);
            if (!$token) {
                $oauthUrl = $sandbox ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token' : 'https://api-m.paypal.com/v1/oauth2/token';
                $oauthResp = Http::asForm()->withBasicAuth($clientId, $clientSecret)->post($oauthUrl, [
                    'grant_type' => 'client_credentials',
                ]);
                if (!$oauthResp->ok()) {
                    return ['valid' => false, 'reason' => 'OAuth token request failed'];
                }
                $token = $oauthResp->json('access_token');
                $expires = (int) ($oauthResp->json('expires_in') ?? 3000);
                Cache::put($cacheKey, $token, $expires - 60);
            }

            $verifyUrl = $sandbox ? 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' : 'https://api-m.paypal.com/v1/notifications/verify-webhook-signature';
            $payload = $request->getContent();
            $body = [
                'auth_algo' => $authAlgo,
                'cert_url' => $certUrl,
                'transmission_id' => $transmissionId,
                'transmission_sig' => $transmissionSig,
                'transmission_time' => $transmissionTime,
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($payload, true),
            ];

            $resp = Http::withToken($token)->post($verifyUrl, $body);
            if (!$resp->ok()) {
                return ['valid' => false, 'reason' => 'Verify signature API error'];
            }

            $status = $resp->json('verification_status');
            $event = json_decode($payload, true) ?: [];
            $eventId = $event['id'] ?? null;

            return ['valid' => $status === 'SUCCESS', 'eventId' => $eventId, 'reason' => $status];
        } catch (Exception $e) {
            return ['valid' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Verifies Mercado Pago webhook signature.
     *
     * @param  Request $request
     * @param  string  $secret
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyMercadoPagoSignature(Request $request, string $secret): array
    {
        try {
            $signature = $request->header('x-signature');
            $requestId = $request->header('x-request-id');
            if (!$signature || !$requestId || !$secret) {
                return ['valid' => false, 'reason' => 'Missing headers or secret'];
            }

            $parts = [];
            foreach (explode(',', $signature) as $kv) {
                $kv = trim($kv);
                if ($kv === '' || !str_contains($kv, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $kv, 2));
                $parts[$k] = $v;
            }
            $ts = $parts['ts'] ?? '';
            $v1 = $parts['v1'] ?? '';
            if ($ts === '' || $v1 === '') {
                return ['valid' => false, 'reason' => 'Malformed x-signature'];
            }

            $dataId = $request->query('data.id') ?? $request->input('data.id') ?? $request->input('id');
            if (!$dataId) {
                $json = $request->json()->all();
                $dataId = data_get($json, 'data.id') ?? data_get($json, 'id');
            }

            $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
            $computed = hash_hmac('sha256', $manifest, $secret);

            if (!hash_equals($computed, $v1)) {
                return ['valid' => false, 'reason' => 'Signature mismatch'];
            }

            return ['valid' => true, 'eventId' => (string) ($dataId ?? $requestId)];
        } catch (Exception $e) {
            return ['valid' => false, 'reason' => $e->getMessage()];
        }
    }

    // ... existing methods ...

    /**
     * Verifies Conekta digest header.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyConektaSignature(Request $request): array
    {
        try {
            $digest = $request->header('Digest');
            if (!$digest) {
                return ['valid' => false, 'reason' => 'Missing Digest header'];
            }

            $secret = config('stag-herd.conekta.secret');
            if (!$secret) {
                return ['valid' => false, 'reason' => 'Missing Conekta secret'];
            }

            // Conekta uses SHA-256 HMAC digest verification
            // Format: "sha-256=<base64_encoded_hmac>"
            if (!str_starts_with($digest, 'sha-256=')) {
                return ['valid' => false, 'reason' => 'Invalid Digest format'];
            }

            $providedDigest = substr($digest, 8); // Remove "sha-256=" prefix
            $payload = $request->getContent();
            $computedDigest = base64_encode(hash_hmac('sha256', $payload, $secret, true));

            if (!hash_equals($computedDigest, $providedDigest)) {
                return ['valid' => false, 'reason' => 'Digest mismatch'];
            }

            return ['valid' => true, 'eventId' => $request->input('id')];
        } catch (Exception $e) {
            return ['valid' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Verifies Kueski Pay webhook signature.
     *
     * @param  Request $request
     * @param  string  $secret
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyKueskiSignature(Request $request, string $secret): array
    {
        try {
            $signature = $request->header('X-Kueski-Signature');
            $timestamp = $request->header('X-Kueski-Timestamp');

            if (!$signature || !$timestamp || !$secret) {
                return ['valid' => false, 'reason' => 'Missing Kueski Pay headers or secret'];
            }

            $payload = $request->getContent();
            // Kueski Pay signature: HMAC-SHA256(timestamp + payload, secret)
            $signedPayload = $timestamp . $payload;
            $computed = hash_hmac('sha256', $signedPayload, $secret);

            if (!hash_equals($computed, $signature)) {
                return ['valid' => false, 'reason' => 'Signature mismatch'];
            }

            $eventId = $request->input('event_id') ?? $request->input('id') ?? $timestamp;

            return ['valid' => true, 'eventId' => (string) $eventId];
        } catch (Exception $e) {
            return ['valid' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Verifies Openpay webhook signature.
     *
     * @param  Request $request
     * @param  string  $secret
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyOpenpaySignature(Request $request, string $secret): array
    {
        try {
            $authHeader = $request->header('verification-signature') ?? $request->header('signature-digest');
            if (!$authHeader || !$secret) {
                return ['valid' => false, 'reason' => 'Missing signature header or secret'];
            }

            // Parse header: t=TIMESTAMP,v1=SIGNATURE
            $parts = [];
            foreach (explode(',', $authHeader) as $kv) {
                $kv = trim($kv);
                if ($kv === '' || !str_contains($kv, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $kv, 2));
                $parts[$k] = $v;
            }
            $ts = $parts['t'] ?? '';
            $sig = $parts['v1'] ?? '';

            if (!$ts || !$sig) {
                return ['valid' => false, 'reason' => 'Malformed signature header'];
            }

            // Use raw request body to avoid JSON encoding drift
            // Openpay signature: HMAC-SHA256(timestamp.rawBody, secret)
            $rawBody = $request->getContent();
            $signedPayload = $ts . '.' . $rawBody;
            $computed = hash_hmac('sha256', $signedPayload, $secret);

            if (!hash_equals($computed, $sig)) {
                return ['valid' => false, 'reason' => 'Signature mismatch'];
            }

            $json = json_decode($rawBody, true) ?: [];

            return ['valid' => true, 'eventId' => $json['id'] ?? $json['event_id'] ?? null];
        } catch (Exception $e) {
            return ['valid' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Stores idempotency key and returns true if new.
     *
     * @param  string $provider
     * @param  string $eventId
     * @param  int    $ttl
     * @return bool
     */
    public static function isIdempotentAndStore(string $provider, string $eventId, int $ttl): bool
    {
        $key = sprintf('webhook:%s:%s', $provider, $eventId);

        return Cache::add($key, 1, $ttl);
    }

    /**
     * Check if an event has already been processed (deduplication).
     *
     * @param string $eventId
     * @param string $provider
     * @param int $ttl
     * @return bool True if duplicate (already exists), False if new (and now stored)
     */
    /**
     * Checks if an event has already been processed (deduplication).
     *
     * @param  string $eventId
     * @param  string $provider
     * @param  int    $ttl
     * @return bool True if duplicate (already exists), false if new.
     */
    public static function checkIdempotency(string $eventId, string $provider = 'test', int $ttl = 300): bool
    {
        // isIdempotentAndStore returns true if added (NEW defined), false if existing (DUPLICATE)
        // checkIdempotency expects true if DUPLICATE
        return !self::isIdempotentAndStore($provider, $eventId, $ttl);
    }
}
