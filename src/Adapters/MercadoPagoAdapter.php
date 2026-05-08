<?php

/**
 * Adapter for Mercado Pago payment API integration.
 *
 * Handles Mercado Pago payment creation, details retrieval, and refund operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MercadoPagoAdapter
{
    private string $accessToken;

    public function __construct()
    {
        $this->accessToken = (string) config('stag-herd.mercadopago.access_token');

        if (!$this->accessToken) {
            throw new RuntimeException('Mercado Pago access token not configured');
        }
    }

    /**
     * Creates a payment using the Payments API.
     *
     * @param float                $amount
     * @param string               $description
     * @param array<string, mixed> $payload
     *
     * @return object
     */
    public function requestPayment(float $amount, string $description, array $payload = []): object
    {
        $body = [
            'transaction_amount' => $amount,
            'description' => $description,
            'payment_method_id' => $payload['payment_method_id'] ?? null,
            'token' => $payload['token'] ?? null,
            'payer' => $payload['payer'] ?? null,
        ];

        if (isset($payload['installments'])) {
            $body['installments'] = $payload['installments'];
        }

        if (isset($payload['issuer_id'])) {
            $body['issuer_id'] = $payload['issuer_id'];
        }

        if (isset($payload['external_reference'])) {
            $body['external_reference'] = $payload['external_reference'];
        }

        if (isset($payload['additional_info'])) {
            $body['additional_info'] = $payload['additional_info'];
        }

        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->retry(2, 200)
            ->post('https://api.mercadopago.com/v1/payments', $body);

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Mercado Pago payment creation failed', [
                'status' => $response->status(),
            ]);

            throw new UnprocessableEntityException('Mercado Pago payment creation failed');
        }

        return (object) $response->json();
    }

    public function getPaymentDetails(string $paymentId): object
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->retry(2, 200)
            ->get('https://api.mercadopago.com/v1/payments/' . $paymentId);

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Failed to get Mercado Pago payment details', [
                'status' => $response->status(),
            ]);

            throw new UnprocessableEntityException('Failed to get Mercado Pago payment details');
        }

        return (object) $response->json();
    }

    public function getOrderDetails(string $orderId): object
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->retry(2, 200)
            ->get('https://api.mercadopago.com/v1/orders/' . $orderId);

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Failed to get Mercado Pago order details', [
                'status' => $response->status(),
            ]);

            throw new Exception('Failed to get Mercado Pago order details');
        }

        return (object) $response->json();
    }

    public function getRefund(
        string $paymentId,
        float $amount,
    ): object {
        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->retry(2, 200)
            ->post(
                'https://api.mercadopago.com/v1/payments/' . $paymentId . '/refunds',
                ['amount' => $amount],
            );

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Mercado Pago refund failed', [
                'status' => $response->status(),
            ]);

            throw new UnprocessableEntityException('Mercado Pago refund failed');
        }

        return (object) $response->json();
    }
}
