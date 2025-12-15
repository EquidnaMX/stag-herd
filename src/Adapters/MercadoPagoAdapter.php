<?php

/**
 * Adapter for Mercado Pago payment API integration.
 *
 * Handles Mercado Pago payment creation, details retrieval, and refund operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MercadoPagoAdapter
{
    private string $accessToken;

    public function __construct()
    {
        $this->accessToken = (string) config(
            'stag-herd.mercadopago.access_token',
            env('MERCADOPAGO_ACCESS_TOKEN', '')
        );

        if (!$this->accessToken) {
            throw new RuntimeException('Mercado Pago access token not configured');
        }
    }

    public function requestPayment(
        float $amount,
        string $description,
    ): object {
        $response = Http::withToken($this->accessToken)->post(
            'https://api.mercadopago.com/checkout/preferences',
            [
                'items' => [
                    [
                        'title' => $description,
                        'quantity' => 1,
                        'unit_price' => $amount,
                    ],
                ],
                'back_urls' => [
                    'success' => route('stag-herd.mercado-pago.confirm'),
                    'failure' => route('stag-herd.mercado-pago.confirm'),
                    'pending' => route('stag-herd.mercado-pago.confirm'),
                ],
                'auto_return' => 'approved',
            ]
        );

        if (!$response->successful()) {
            throw new UnprocessableEntityException('Mercado Pago preference creation failed');
        }

        return (object) $response->json();
    }

    public function getPaymentDetails(string $paymentId): object
    {
        $response = Http::withToken($this->accessToken)->get(
            'https://api.mercadopago.com/v1/payments/' . $paymentId
        );

        if (!$response->successful()) {
            throw new UnprocessableEntityException('Failed to get Mercado Pago payment details');
        }

        return (object) $response->json();
    }

    public function getRefund(
        string $paymentId,
        float $amount,
    ): object {
        $response = Http::withToken($this->accessToken)->post(
            'https://api.mercadopago.com/v1/payments/' . $paymentId . '/refunds',
            ['amount' => $amount]
        );

        if (!$response->successful()) {
            throw new UnprocessableEntityException('Mercado Pago refund failed');
        }

        return (object) $response->json();
    }
}
