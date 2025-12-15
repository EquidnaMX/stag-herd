<?php

/**
 * Adapter for Openpay payment API integration.
 *
 * Handles Openpay bank charge creation, details retrieval, and refund operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Exception;
use Illuminate\Support\Facades\Http;

class OpenPayAdapter
{
    private string $merchantId;

    private string $privateKey;

    private bool $sandbox;

    public function __construct()
    {
        $this->merchantId = (string) config(
            'stag-herd.openpay.merchant_id',
            env('OPENPAY_MERCHANT_ID', '')
        );

        $this->privateKey = (string) config(
            'stag-herd.openpay.private_key',
            env('OPENPAY_PRIVATE_KEY', '')
        );

        $this->sandbox = (bool) config(
            'stag-herd.openpay.sandbox',
            env('OPENPAY_SANDBOX', true)
        );

        if (!$this->merchantId || !$this->privateKey) {
            throw new Exception('Openpay credentials not configured');
        }
    }

    private function getApiUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox-api.openpay.mx/v1/' . $this->merchantId
            : 'https://api.openpay.mx/v1/' . $this->merchantId;
    }

    public function createBankCharge(
        float $amount,
        string $description,
        string $customerName,
        string $customerEmail,
    ): object {
        $response = Http::withBasicAuth(
            $this->privateKey,
            ''
        )->post(
            $this->getApiUrl() . '/charges',
            [
                'method' => 'bank_account',
                'amount' => $amount,
                'description' => $description,
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                ],
            ]
        );

        if (!$response->successful()) {
            throw new Exception('Openpay charge creation failed');
        }

        return (object) $response->json();
    }

    public function getChargeDetails(string $chargeId): object
    {
        $response = Http::withBasicAuth(
            $this->privateKey,
            ''
        )->get(
            $this->getApiUrl() . '/charges/' . $chargeId
        );

        if (!$response->successful()) {
            throw new Exception('Failed to get Openpay charge details');
        }

        return (object) $response->json();
    }

    public function getRefund(
        string $chargeId,
        float $amount,
    ): object {
        $response = Http::withBasicAuth(
            $this->privateKey,
            ''
        )->post(
            $this->getApiUrl() . '/charges/' . $chargeId . '/refund',
            ['amount' => $amount]
        );

        if (!$response->successful()) {
            throw new Exception('Openpay refund failed');
        }

        return (object) $response->json();
    }
}
