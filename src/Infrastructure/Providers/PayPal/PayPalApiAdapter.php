<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Exceptions\ProviderAuthenticationException;
use Equidna\StagHerd\Exceptions\ProviderCommunicationException;
use Equidna\StagHerd\Exceptions\ProviderNotConfiguredException;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PayPalApiAdapter implements PayPalGateway
{
    private const PROVIDER = 'paypal';

    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v2/checkout/orders',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
        );
    }

    public function getOrder(string $orderId): array
    {
        return $this->send(
            method: 'get',
            endpoint: "/v2/checkout/orders/{$orderId}",
        );
    }

    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: "/v2/checkout/orders/{$orderId}/capture",
            payload: [],
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
        );
    }

    public function getCapture(string $captureId): array
    {
        return $this->send(
            method: 'get',
            endpoint: "/v2/payments/captures/{$captureId}",
        );
    }

    public function refundCapture(
        string $captureId,
        ?int $amount = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
    ): array {
        $payload = [];

        if ($amount !== null) {
            $payload['amount'] = [
                'value' => MoneyFormatter::toDecimal($amount),
                'currency_code' => strtoupper($currency ?: 'MXN'),
            ];
        }

        return $this->send(
            method: 'post',
            endpoint: "/v2/payments/captures/{$captureId}/refund",
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $endpoint,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): array {
        try {
            $request = $this->request($idempotencyKey);

            $response = match (strtolower($method)) {
                'get' => $request->get($endpoint, $payload),
                'post' => $request->post($endpoint, $payload),
                default => throw ProviderCommunicationException::invalidResponse(
                    self::PROVIDER,
                    [
                        'reason' => "Unsupported HTTP method [{$method}].",
                    ],
                ),
            };

            if ($response->status() === 401) {
                Cache::forget($this->cacheKey());

                throw ProviderAuthenticationException::unauthorized(
                    self::PROVIDER,
                    $response->json() ?? [],
                );
            }

            if ($response->failed()) {
                throw ProviderCommunicationException::requestFailed(
                    self::PROVIDER,
                    $response->status(),
                    $response->json() ?? [],
                );
            }

            return $response->json() ?? [];
        } catch (ProviderAuthenticationException | ProviderCommunicationException | ProviderNotConfiguredException $exception) {
            throw $exception;
        } catch (RequestException $exception) {
            throw ProviderCommunicationException::connectionFailed(
                self::PROVIDER,
                $exception->getMessage(),
            );
        } catch (Throwable $exception) {
            throw ProviderCommunicationException::connectionFailed(
                self::PROVIDER,
                $exception->getMessage(),
            );
        }
    }

    private function request(?string $idempotencyKey = null): PendingRequest
    {
        $request = Http::baseUrl($this->baseUri())
            ->timeout((int) config('stag-herd.providers.paypal.http.timeout', 15))
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken());

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders([
                'PayPal-Request-Id' => $idempotencyKey,
                'Prefer' => 'return=representation',
            ]);
        }

        return $request;
    }

    private function accessToken(): string
    {
        return Cache::remember(
            $this->cacheKey(),
            now()->addMinutes(50),
            function () {
                $clientId = config('stag-herd.providers.paypal.credentials.client_id');
                $secret = config('stag-herd.providers.paypal.credentials.secret');

                if (! $clientId) {
                    throw ProviderNotConfiguredException::missingCredential(
                        self::PROVIDER,
                        'client_id',
                    );
                }

                if (! $secret) {
                    throw ProviderNotConfiguredException::missingCredential(
                        self::PROVIDER,
                        'secret',
                    );
                }

                $response = Http::baseUrl($this->baseUri())
                    ->timeout((int) config('stag-herd.providers.paypal.http.timeout', 15))
                    ->acceptJson()
                    ->asForm()
                    ->withBasicAuth((string) $clientId, (string) $secret)
                    ->post('/v1/oauth2/token', [
                        'grant_type' => 'client_credentials',
                    ]);

                if ($response->status() === 401) {
                    throw ProviderAuthenticationException::unauthorized(
                        self::PROVIDER,
                        $response->json() ?? [],
                    );
                }

                if ($response->failed()) {
                    throw ProviderCommunicationException::requestFailed(
                        self::PROVIDER,
                        $response->status(),
                        $response->json() ?? [],
                    );
                }

                $token = $response->json('access_token');

                if (! $token) {
                    throw ProviderCommunicationException::invalidResponse(
                        self::PROVIDER,
                        $response->json() ?? [],
                    );
                }

                return (string) $token;
            }
        );
    }

    private function baseUri(): string
    {
        return (string) config(
            'stag-herd.providers.paypal.http.base_uri',
            'https://api-m.sandbox.paypal.com',
        );
    }

    private function cacheKey(): string
    {
        return 'stag-herd:paypal:access-token:' . md5($this->baseUri());
    }
}
