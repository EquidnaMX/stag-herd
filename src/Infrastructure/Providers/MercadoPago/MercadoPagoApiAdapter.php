<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Exceptions\ProviderAuthenticationException;
use Equidna\StagHerd\Exceptions\ProviderCommunicationException;
use Equidna\StagHerd\Exceptions\ProviderNotConfiguredException;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MercadoPagoApiAdapter implements MercadoPagoGateway
{
    private const PROVIDER = 'mercado_pago';

    public function createPayment(
        array $payload,
        ?string $idempotencyKey = null,
        ?string $deviceId = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v1/payments',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            deviceId: $deviceId,
        );
    }

    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
        ?string $deviceId = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v1/orders',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            deviceId: $deviceId,
        );
    }

    public function getOrder(string $providerOrderId): array
    {
        return $this->send(
            method: 'get',
            endpoint: "/v1/orders/{$providerOrderId}",
        );
    }

    public function getPayment(string $providerPaymentId): array
    {
        return $this->send(
            method: 'get',
            endpoint: "/v1/payments/{$providerPaymentId}",
        );
    }

    public function searchPayments(array $filters = []): array
    {
        return $this->send(
            method: 'get',
            endpoint: '/v1/payments/search',
            payload: $filters,
        );
    }

    public function cancelPayment(string $providerPaymentId): array
    {
        return $this->send(
            method: 'put',
            endpoint: "/v1/payments/{$providerPaymentId}",
            payload: [
                'status' => 'canceled',
            ],
        );
    }

    public function refundPayment(
        string $providerPaymentId,
        ?int $amount = null,
        ?string $idempotencyKey = null,
    ): array {
        $payload = [];

        if ($amount !== null) {
            $payload['amount'] = MoneyFormatter::toDecimal($amount);
        }

        return $this->send(
            method: 'post',
            endpoint: "/v1/payments/{$providerPaymentId}/refunds",
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
        ?string $deviceId = null,
    ): array {
        try {
            $request = $this->request($idempotencyKey, $deviceId);

            $response = match (strtolower($method)) {
                'get' => $request->get($endpoint, $payload),
                'post' => $request->post($endpoint, $payload),
                'put' => $request->put($endpoint, $payload),
                default => throw ProviderCommunicationException::invalidResponse(
                    self::PROVIDER,
                    [
                        'reason' => "Unsupported HTTP method [{$method}].",
                    ],
                ),
            };

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

            return $response->json() ?? [];
        } catch (ProviderAuthenticationException | ProviderCommunicationException | ProviderNotConfiguredException $exception) {
            throw $exception;
        } catch (ConnectionException | RequestException $exception) {
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

    private function request(
        ?string $idempotencyKey = null,
        ?string $deviceId = null,
    ): PendingRequest {
        $accessToken = config('stag-herd.providers.mercado_pago.credentials.access_token');

        if (! $accessToken) {
            throw ProviderNotConfiguredException::missingCredential(
                self::PROVIDER,
                'access_token',
            );
        }

        $headers = [];

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $headers['X-Idempotency-Key'] = substr($idempotencyKey, 0, 64);
        }

        if ($deviceId !== null && trim($deviceId) !== '') {
            $headers['X-meli-session-id'] = $deviceId;
        }

        return Http::baseUrl((string) config(
            'stag-herd.providers.mercado_pago.http.base_uri',
            'https://api.mercadopago.com',
        ))
            ->timeout((int) config('stag-herd.providers.mercado_pago.http.timeout', 15))
            ->acceptJson()
            ->asJson()
            ->withToken((string) $accessToken)
            ->withHeaders($headers);
    }
}
