<?php

/**
 * Adapter for Clip payment API integration.
 *
 * Handles Clip payment creation, details retrieval, and refund operations.
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

class ClipAdapter
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config(
            'stag-herd.clip.api_key',
            env('CLIP_API_KEY', '')
        );

        if (!$this->apiKey) {
            throw new Exception('Clip API key not configured');
        }
    }

    public function requestPayment(
        float $amount,
        string $description,
    ): object {
        $response = Http::withToken($this->apiKey)->post(
            'https://api.clip.mx/v1/payments',
            [
                'amount' => $amount,
                'description' => $description,
            ]
        );

        if (!$response->successful()) {
            throw new Exception('Clip payment creation failed');
        }

        return (object) $response->json();
    }

    public function getPaymentDetails(string $paymentId): object
    {
        $response = Http::withToken($this->apiKey)->get(
            'https://api.clip.mx/v1/payments/' . $paymentId
        );

        if (!$response->successful()) {
            throw new Exception('Failed to get Clip payment details');
        }

        return (object) $response->json();
    }

    public function getRefund(
        string $paymentId,
        float $amount,
    ): object {
        $response = Http::withToken($this->apiKey)->post(
            'https://api.clip.mx/v1/payments/' . $paymentId . '/refund',
            ['amount' => $amount]
        );

        if (!$response->successful()) {
            throw new Exception('Clip refund failed');
        }

        return (object) $response->json();
    }
}
