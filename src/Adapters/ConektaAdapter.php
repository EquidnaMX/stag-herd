<?php

/**
 * Adapter for Conekta payment API integration (stub).
 *
 * Placeholder for Conekta API integration. Requires implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Equidna\StagHerd\Contracts\ConektaGateway;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ConektaAdapter implements ConektaGateway
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = (string) config('stag-herd.conekta.api_secret');

        if ($this->secretKey === '') {
            throw new RuntimeException('Conekta secret key not configured');
        }
    }

    /**
     * Creates a Conekta order with OXXO or card payment method.
     *
     * @param float                $amount
     * @param string               $description
     * @param string               $customerEmail
     * @param string|null          $customerName
     * @param array<string, mixed> $metadata
     * @param string               $paymentMethodType
     * @param string|null          $tokenId
     *
     * @return object
     */
    public function requestPayment(
        float $amount,
        string $description,
        string $customerEmail,
        ?string $customerName = null,
        array $metadata = [],
        string $paymentMethodType = 'oxxo_cash',
        ?string $tokenId = null,
    ): object {
        if ($customerEmail === '') {
            throw new Exception('Conekta customer email is required');
        }

        if ($paymentMethodType === 'card' && !$tokenId) {
            throw new Exception('Conekta card token is required');
        }

        $payload = [
            'currency' => 'MXN',
            'customer_info' => [
                'email' => $customerEmail,
                'name' => $customerName ?: $customerEmail,
            ],
            'line_items' => [
                [
                    'name' => $description,
                    'unit_price' => (int) round($amount * 100),
                    'quantity' => 1,
                ],
            ],
            'charges' => [
                [
                    'payment_method' => array_filter(
                        [
                            'type' => $paymentMethodType,
                            'token_id' => $tokenId,
                        ],
                        fn ($value) => !is_null($value),
                    ),
                ],
            ],
        ];

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->withHeaders([
                'Accept' => 'application/vnd.conekta-v2.0.0+json',
            ])
            ->timeout(15)
            ->retry(2, 200)
            ->post('https://api.conekta.io/orders', $payload);

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Conekta order creation failed', [
                'status' => $response->status(),
            ]);

            throw new Exception('Conekta order creation failed');
        }

        $data = (object) $response->json();
        $paymentUrl = data_get($data, 'charges.data.0.payment_method.barcode_url')
            ?? data_get($data, 'charges.data.0.payment_method.reference')
            ?? null;

        $data->payment_url = $paymentUrl;
        $data->charge_id = data_get($data, 'charges.data.0.id');

        return $data;
    }

    public function getOrderDetails(string $orderId): object
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->withHeaders([
                'Accept' => 'application/vnd.conekta-v2.0.0+json',
            ])
            ->timeout(15)
            ->retry(2, 200)
            ->get('https://api.conekta.io/orders/' . $orderId);

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Failed to get Conekta order details', [
                'status' => $response->status(),
            ]);

            throw new Exception('Failed to get Conekta order details');
        }

        return (object) $response->json();
    }
}
