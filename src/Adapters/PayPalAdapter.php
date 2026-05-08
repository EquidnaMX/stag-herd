<?php

/**
 * Adapter for PayPal payment API integration.
 *
 * Handles PayPal order creation, details retrieval, and refund operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Equidna\StagHerd\Contracts\PayPalGateway;
use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalAdapter implements PayPalGateway
{
    private string $apiUrl;

    private string $clientId;

    private string $clientSecret;

    private bool $sandbox;

    public function __construct()
    {
        $this->sandbox = (bool) config('stag-herd.paypal.sandbox', true);

        $this->apiUrl = $this->sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        $this->clientId = (string) config('stag-herd.paypal.client_id');

        $this->clientSecret = (string) config('stag-herd.paypal.client_secret');

        if (!$this->clientId || !$this->clientSecret) {
            throw new RuntimeException('PayPal credentials not configured');
        }
    }

    private function getAccessToken(): string
    {
        $cacheKey = 'paypal_access_token_' . ($this->sandbox ? 'sandbox' : 'live');
        $ttl = (int) config('stag-herd.paypal.token_ttl', 3600);

        return Cache::remember(
            $cacheKey,
            $ttl,
            function () {
                $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                    ->timeout(15)
                    ->retry(2, 200)
                    ->asForm()
                    ->post(
                        $this->apiUrl . '/v1/oauth2/token',
                        ['grant_type' => 'client_credentials'],
                    );

                if (!$response->successful()) {
                    Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Failed to get PayPal access token', [
                        'status' => $response->status(),
                    ]);

                    throw new RuntimeException('Failed to get PayPal access token');
                }

                return $response->json('access_token');
            },
        );
    }

    public function requestPayment(
        float $amount,
        string $description,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): object {
        $token = $this->getAccessToken();

        $finalReturnUrl = $returnUrl ?? url('/');
        $finalCancelUrl = $cancelUrl ?? url('/');

        $response = Http::withToken($token)
            ->timeout(15)
            ->retry(2, 200)
            ->post(
                $this->apiUrl . '/v2/checkout/orders',
                [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'amount' => [
                                'currency_code' => 'MXN',
                                'value' => number_format(
                                    $amount,
                                    2,
                                    '.',
                                    '',
                                ),
                            ],
                            'description' => $description,
                        ],
                    ],
                    'application_context' => [
                        'return_url' => $finalReturnUrl,
                        'cancel_url' => $finalCancelUrl,
                    ],
                ],
            );
        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('PayPal order creation failed', [
                'status' => $response->status(),
            ]);

            throw new UnprocessableEntityException('PayPal order creation failed');
        }

        return (object) $response->json();
    }

    public function getOrderDetails(string $orderId): object
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(15)
            ->retry(2, 200)
            ->get(
                $this->apiUrl . '/v2/checkout/orders/' . $orderId,
            );

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Failed to get PayPal order details', [
                'status' => $response->status(),
            ]);

            throw new UnprocessableEntityException('Failed to get PayPal order details');
        }

        return (object) $response->json();
    }

    public function getRefund(
        string $orderId,
        float $amount,
    ): object {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(15)
            ->retry(2, 200)
            ->post(
                $this->apiUrl . '/v2/payments/captures/' . $orderId . '/refund',
                [
                    'amount' => [
                        'currency_code' => 'MXN',
                        'value' => number_format(
                            $amount,
                            2,
                            '.',
                            '',
                        ),
                    ],
                ],
            );

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('PayPal refund failed', [
                'status' => $response->status(),
            ]);

            throw new UnprocessableEntityException('PayPal refund failed');
        }

        return (object) $response->json();
    }

    public function captureOrder(string $orderId): object
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders([
                'PayPal-Request-Id' => 'stag-herd-capture-' . $orderId,
            ])
            ->withBody('{}', 'application/json')
            ->timeout(15)
            ->retry(2, 200)
            ->post($this->apiUrl . '/v2/checkout/orders/' . $orderId . '/capture');

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('PayPal order capture failed', [
                'status' => $response->status(),
                'order_id' => $orderId,
            ]);

            throw new UnprocessableEntityException('PayPal order capture failed');
        }

        return (object) $response->json();
    }
}
