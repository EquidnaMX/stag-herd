<?php

/**
 * Adapter for Clip payment API integration.
 *
 * Handles Clip payment creation, details retrieval, and refund operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Equidna\StagHerd\Contracts\ClipGateway;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClipAdapter implements ClipGateway
{
    private string $apiKey;

    private string $apiBaseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('stag-herd.clip.api_key');

        if (!$this->apiKey) {
            throw new Exception('Clip API key not configured');
        }

        $this->apiBaseUrl = rtrim((string) config('stag-herd.clip.api_base_url', 'https://api-gw.payclip.com'), '/');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function requestPayment(
        float $amount,
        string $description,
        array $options = [],
    ): object {
        $body = array_filter([
            'amount' => $amount,
            'currency' => $options['currency'] ?? config('stag-herd.clip.currency', 'MXN'),
            'purchase_description' => mb_substr($description, 0, 250),
            'redirection_url' => $options['redirection_url'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'override_settings' => $options['override_settings'] ?? null,
            'webhook_url' => $options['webhook_url'] ?? null,
        ], fn ($value) => !is_null($value));

        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->retry(2, 200)
            ->post($this->apiBaseUrl . '/checkout', $body);

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Clip payment link creation failed', [
                'status' => $response->status(),
            ]);

            throw new Exception('Clip payment link creation failed');
        }

        $data = (object) $response->json();
        $data->id = $data->payment_request_id ?? $data->id ?? null;
        $data->payment_url = $data->payment_request_url ?? $data->payment_url ?? null;

        return $data;
    }

    public function getPaymentDetails(string $paymentId): object
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->retry(2, 200)
            ->get($this->apiBaseUrl . '/checkout/' . $paymentId);

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Failed to get Clip payment link status', [
                'status' => $response->status(),
            ]);

            throw new Exception('Failed to get Clip payment link status');
        }

        return (object) $response->json();
    }

    public function getRefund(
        string $paymentId,
        float $amount,
    ): object {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->retry(2, 200)
            ->post(
                $this->apiBaseUrl . '/payments/' . $paymentId . '/refund',
                ['amount' => $amount],
            );

        if (!$response->successful()) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Clip refund failed', [
                'status' => $response->status(),
            ]);

            throw new Exception('Clip refund failed');
        }

        return (object) $response->json();
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/vnd.com.payclip.v2+json',
        ];
    }
}
