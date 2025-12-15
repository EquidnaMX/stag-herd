<?php

/**
 * Adapter for PayPal payment API integration.
 *
 * Handles PayPal order creation, details retrieval, and refund operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalAdapter
{
    private string $apiUrl;
    private string $clientId;
    private string $clientSecret;
    private bool $sandbox;

    public function __construct()
    {
        $this->sandbox = (bool) config(
            'stag-herd.paypal.sandbox',
            env('PAYPAL_SANDBOX', true)
        );

        $this->apiUrl = $this->sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        $this->clientId = (string) config(
            'stag-herd.paypal.key',
            env('PAYPAL_KEY', '')
        );

        $this->clientSecret = (string) config(
            'stag-herd.paypal.secret',
            env('PAYPAL_SECRET', '')
        );

        if (!$this->clientId || !$this->clientSecret) {
            throw new RuntimeException('PayPal credentials not configured');
        }
    }

    private function getAccessToken(): string
    {
        $cacheKey = 'paypal_access_token_' . ($this->sandbox ? 'sandbox' : 'live');
        $ttl = (int) config(
            'stag-herd.paypal.token_ttl',
            env('PAYPAL_TOKEN_TTL', 3600)
        );

        return Cache::remember(
            $cacheKey,
            $ttl,
            function () {
                $response = Http::withBasicAuth(
                    $this->clientId,
                    $this->clientSecret
                )->asForm()->post(
                    $this->apiUrl . '/v1/oauth2/token',
                    ['grant_type' => 'client_credentials']
                );

                if (!$response->successful()) {
                    throw new RuntimeException('Failed to get PayPal access token');
                }

                return $response->json('access_token');
            }
        );
    }

    public function requestPayment(
        float $amount,
        string $description,
    ): object {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post(
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
                                ''
                            ),
                        ],
                        'description' => $description,
                    ],
                ],
                'application_context' => [
                    'return_url' => route('stag-herd.paypal.confirm'),
                    'cancel_url' => route('stag-herd.paypal.confirm'),
                ],
            ]
        );

        if (!$response->successful()) {
            throw new UnprocessableEntityException('PayPal order creation failed');
        }

        return (object) $response->json();
    }

    public function getOrderDetails(string $orderId): object
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->get(
            $this->apiUrl . '/v2/checkout/orders/' . $orderId
        );

        if (!$response->successful()) {
            throw new UnprocessableEntityException('Failed to get PayPal order details');
        }

        return (object) $response->json();
    }

    public function getRefund(
        string $orderId,
        float $amount,
    ): object {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post(
            $this->apiUrl . '/v2/payments/captures/' . $orderId . '/refund',
            [
                'amount' => [
                    'currency_code' => 'MXN',
                    'value' => number_format(
                        $amount,
                        2,
                        '.',
                        ''
                    ),
                ],
            ]
        );

        if (!$response->successful()) {
            throw new UnprocessableEntityException('PayPal refund failed');
        }

        return (object) $response->json();
    }
}
