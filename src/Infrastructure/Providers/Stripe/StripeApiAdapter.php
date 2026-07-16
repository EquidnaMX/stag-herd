<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Exceptions\ProviderAuthenticationException;
use Equidna\StagHerd\Exceptions\ProviderCommunicationException;
use Equidna\StagHerd\Exceptions\ProviderNotConfiguredException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class StripeApiAdapter implements StripeGateway
{
    private const PROVIDER = 'stripe';

    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->send(
            method: 'post',
            endpoint: '/v1/payment_intents',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
        );
    }

    public function getPaymentIntent(string $paymentIntentId): array
    {
        return $this->send(
            method: 'get',
            endpoint: "/v1/payment_intents/{$paymentIntentId}",
        );
    }

    public function confirmPaymentIntent(
        string $paymentIntentId,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: "/v1/payment_intents/{$paymentIntentId}/confirm",
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
        );
    }

    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        return $this->send(
            method: 'post',
            endpoint: "/v1/payment_intents/{$paymentIntentId}/cancel",
        );
    }

    public function createCustomer(
        array $payload,
        ?string $idempotencyKey = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v1/customers',
            payload: $payload,
            idempotencyKey: $idempotencyKey
                ?? (string) Str::uuid(),
        );
    }

    public function createSetupIntent(
        array $payload,
        ?string $idempotencyKey = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v1/setup_intents',
            payload: $payload,
            idempotencyKey: $idempotencyKey
                ?? (string) Str::uuid(),
        );
    }

    public function getSetupIntent(
        string $setupIntentId,
    ): array {
        return $this->send(
            method: 'get',
            endpoint: "/v1/setup_intents/{$setupIntentId}",
        );
    }

    public function getPaymentMethod(
        string $paymentMethodId,
    ): array {
        return $this->send(
            method: 'get',
            endpoint: "/v1/payment_methods/{$paymentMethodId}",
        );
    }

    public function createRefund(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->send(
            method: 'post',
            endpoint: '/v1/refunds',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
        );
    }

    public function getCustomer(string $customerId): array
    {
        return $this->send(
            method: 'get',
            endpoint: "/v1/customers/{$customerId}",
        );
    }

    public function detachPaymentMethod(
        string $paymentMethodId,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: "/v1/payment_methods/{$paymentMethodId}/detach",
        );
    }

    public function updateCustomer(
        string $customerId,
        array $payload,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: "/v1/customers/{$customerId}",
            payload: $payload,
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
                    ['reason' => "Unsupported HTTP method [{$method}]."],
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
        } catch (
            ProviderAuthenticationException |
            ProviderCommunicationException |
            ProviderNotConfiguredException $exception
        ) {
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
        $secretKey = config('stag-herd.providers.stripe.credentials.secret_key');

        if (! $secretKey) {
            throw ProviderNotConfiguredException::missingCredential(
                self::PROVIDER,
                'secret_key',
            );
        }

        $request = Http::baseUrl((string) config(
            'stag-herd.providers.stripe.http.base_uri',
            'https://api.stripe.com',
        ))
            ->timeout((int) config('stag-herd.providers.stripe.http.timeout', 15))
            ->acceptJson()
            ->asForm()
            ->withBasicAuth((string) $secretKey, '');

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $request = $request->withHeaders([
                'Idempotency-Key' => substr($idempotencyKey, 0, 255),
            ]);
        }

        return $request;
    }
}
