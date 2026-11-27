<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Data\PayPalRequestContextData;
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

    public function createCatalogProduct(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v1/catalogs/products',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            context: $context,
        );
    }

    public function createPlan(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v1/billing/plans',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            context: $context,
        );
    }

    public function createSubscription(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v1/billing/subscriptions',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            context: $context,
        );
    }

    public function getSubscription(
        string $subscriptionId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'get',
            endpoint: "/v1/billing/subscriptions/{$subscriptionId}",
            context: $context,
        );
    }

    public function cancelSubscription(
        string $subscriptionId,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: "/v1/billing/subscriptions/{$subscriptionId}/cancel",
            payload: $payload === [] ? ['reason' => 'Requested by merchant.'] : $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            context: $context,
        );
    }

    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v2/checkout/orders',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            context: $context,
        );
    }

    public function getOrder(
        string $orderId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'get',
            endpoint: "/v2/checkout/orders/{$orderId}",
            context: $context,
        );
    }

    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: "/v2/checkout/orders/{$orderId}/capture",
            payload: new \stdClass(),
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            context: $context,
        );
    }

    public function getCapture(
        string $captureId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'get',
            endpoint: "/v2/payments/captures/{$captureId}",
            context: $context,
        );
    }

    public function refundCapture(
        string $captureId,
        ?int $amount = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        $payload = new \stdClass();

        if ($amount !== null) {
            $payload = [
                'amount' => [
                    'value' => MoneyFormatter::toDecimal($amount),
                    'currency_code' => strtoupper($currency ?: 'MXN'),
                ],
            ];
        }

        return $this->send(
            method: 'post',
            endpoint: "/v2/payments/captures/{$captureId}/refund",
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            context: $context,
        );
    }

    public function getPaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'get',
            endpoint: "/vault/payment-tokens/{$paymentTokenId}",
            context: $context,
        );
    }

    public function deletePaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'delete',
            endpoint: "/vault/payment-tokens/{$paymentTokenId}",
            context: $context,
        );
    }

    public function createPartnerReferral(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/v2/customer/partner-referrals',
            payload: $payload,
            idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
            context: $context,
        );
    }

    public function getMerchantIntegration(
        string $partnerMerchantId,
        string $sellerMerchantId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return $this->send(
            method: 'get',
            endpoint: "/v1/customer/partners/{$partnerMerchantId}/merchant-integrations/{$sellerMerchantId}",
            context: $context,
        );
    }

    public function verifyWebhookSignature(
        array $payload,
        ?PayPalRequestContextData $context = null,
    ): bool {
        $response = $this->send(
            method: 'post',
            endpoint: '/v1/notifications/verify-webhook-signature',
            payload: $payload,
            context: $context,
        );

        return strtoupper((string) ($response['verification_status'] ?? '')) === 'SUCCESS';
    }

    /**
     * @param array<string, mixed>|object $payload
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $endpoint,
        array|object $payload = [],
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        try {
            $request = $this->request($idempotencyKey, $context);

            $response = match (strtolower($method)) {
                'get' => $request->get($endpoint, is_array($payload) ? $payload : []),
                'post' => $request->post($endpoint, $payload),
                'delete' => $request->delete($endpoint, is_array($payload) ? $payload : []),
                default => throw ProviderCommunicationException::invalidResponse(
                    self::PROVIDER,
                    [
                        'reason' => "Unsupported HTTP method [{$method}].",
                    ],
                ),
            };

            if ($response->status() === 401) {
                Cache::forget($this->cacheKey($context));

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

    private function request(
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): PendingRequest {
        $request = Http::baseUrl($this->baseUri())
            ->timeout((int) config('stag-herd.providers.paypal.http.timeout', 15))
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken($context));

        $platformAttributionId = $context?->platformAttributionId
            ?? config('stag-herd.providers.paypal.credentials.platform_attribution_id');

        if (is_string($platformAttributionId) && trim($platformAttributionId) !== '') {
            $request = $request->withHeaders([
                'PayPal-Partner-Attribution-Id' => trim($platformAttributionId),
            ]);
        }

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders([
                'PayPal-Request-Id' => $idempotencyKey,
                'Prefer' => 'return=representation',
            ]);
        }

        return $request;
    }

    private function accessToken(?PayPalRequestContextData $context = null): string
    {
        return Cache::remember(
            $this->cacheKey($context),
            now()->addMinutes(50),
            function () {
                $clientId = config('stag-herd.providers.paypal.credentials.client_id');
                $secret = config('stag-herd.providers.paypal.credentials.secret');

                if (!$clientId) {
                    throw ProviderNotConfiguredException::missingCredential(
                        self::PROVIDER,
                        'client_id',
                    );
                }

                if (!$secret) {
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

                if (!$token) {
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

    private function cacheKey(?PayPalRequestContextData $context = null): string
    {
        $environment = $context?->environment
            ?? config('stag-herd.providers.paypal.credentials.environment', 'sandbox');

        $credentialContext = $context?->credentialContext ?? 'default';

        return implode(':', [
            'stag-herd',
            'paypal',
            'access-token',
            strtolower((string) $environment),
            $credentialContext,
            hash('sha256', $this->baseUri()),
            $this->credentialFingerprint(),
        ]);
    }

    private function credentialFingerprint(): string
    {
        $clientId = (string) config('stag-herd.providers.paypal.credentials.client_id', '');
        $secret = (string) config('stag-herd.providers.paypal.credentials.secret', '');

        return hash('sha256', $clientId . '|' . $secret);
    }
}
