<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Http\Requests\Payments\Stripe\ConfirmPaymentIntentRequest;
use Equidna\StagHerd\Http\Requests\Payments\Stripe\ProcessTokenizedCardRequest;
use Equidna\StagHerd\Http\Requests\Payments\Stripe\ProcessWalletPaymentRequest;
use Equidna\StagHerd\Http\Requests\Payments\Stripe\CompleteSetupIntentRequest;
use Equidna\StagHerd\Http\Requests\Payments\Stripe\CreatePaymentIntentRequest;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeCardReuse;
use Equidna\StagHerd\Http\Requests\Payments\Stripe\CreateSetupIntentRequest;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeResultMapper;
use Equidna\StagHerd\Exceptions\ProviderCommunicationException;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Facades\StagHerd;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;

class StripeController extends Controller
{
    public function __construct(
        private readonly StripeResultMapper $stripeResultMapper,
        private readonly PaymentRepository $paymentRepository,
        private readonly StripeCardReuse $stripeCardReuse,
        private readonly StripeGateway $stripeGateway,
    ) {}

    public function createSetupIntent(CreateSetupIntentRequest $request): JsonResponse
    {
        try {
            $customerPayload = $this->buildStripeCustomerPayload(
                payerReference: $request->payerReference(),
                payerEmail: $request->payerEmail(),
                payerName: $request->payerName(),
                source: 'stag-herd-stripe-setup',
            );

            $customerId = $this->ensureStripeCustomerExists(
                customerId: $request->customerId(),
                customerPayload: $customerPayload,
            );

            if (! $customerId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Stripe no regresó customer_id.',
                ], 422);
            }

            if (! str_starts_with($customerId, 'cus_')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El customer_id de Stripe no es válido.',
                ], 422);
            }

            $setupIntent = $this->stripeGateway->createSetupIntent(
                payload: array_filter(
                    [
                        'customer' => $customerId,
                        'payment_method_types' => ['card'],
                        'usage' => 'off_session',
                        'return_url' => $request->returnUrl(),
                        'metadata' => array_replace_recursive(
                            $request->customMetadata(),
                            [
                                'payer_reference' => $request->payerReference(),
                                'source' => 'stag-herd-stripe-setup',
                            ],
                        ),
                    ],
                    fn($value) => $value !== null && $value !== '' && $value !== [],
                ),
                idempotencyKey: $request->idempotencyKey(),
            );

            $setupIntentId = data_get($setupIntent, 'id');
            $clientSecret = data_get($setupIntent, 'client_secret');

            if (! $setupIntentId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Stripe no regresó setup_intent_id.',
                    'setup_intent' => $setupIntent,
                ], 422);
            }

            if (! $clientSecret) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Stripe no regresó client_secret.',
                    'setup_intent' => $setupIntent,
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => 'SetupIntent de Stripe creado correctamente.',
                'customer_id' => $customerId,
                'setup_intent_id' => $setupIntentId,
                'client_secret' => $clientSecret,
                'status' => data_get($setupIntent, 'status'),
                'setup_intent' => $setupIntent,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function completeSetupIntent(CompleteSetupIntentRequest $request): JsonResponse
    {
        try {
            $setupIntent = $this->stripeGateway->getSetupIntent(
                $request->setupIntentId(),
            );

            $setupIntentId = data_get($setupIntent, 'id');
            $status = data_get($setupIntent, 'status');
            $setupCustomerId = data_get($setupIntent, 'customer');
            $paymentMethodId = data_get($setupIntent, 'payment_method');

            if ($status !== 'succeeded') {
                return response()->json([
                    'ok' => false,
                    'message' => 'La tarjeta todavía no fue configurada correctamente.',
                    'setup_intent_id' => $setupIntentId,
                    'status' => $status,
                ], 422);
            }

            if ((string) $setupCustomerId !== (string) $request->customerId()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El SetupIntent no pertenece al customer indicado.',
                ], 422);
            }

            if (! $paymentMethodId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Stripe no regresó payment_method_id.',
                    'setup_intent' => $setupIntent,
                ], 422);
            }

            $resolvedPaymentMethod = $this->stripeCardReuse->resolve(
                (string) $setupCustomerId,
                (string) $paymentMethodId,
            );

            $paymentMethodId = $resolvedPaymentMethod['payment_method_id'];
            $paymentMethod = $resolvedPaymentMethod['payment_method'];

            return response()->json([
                'ok' => true,
                'message' => 'Tarjeta tokenizada correctamente.',
                'customer_id' => (string) $setupCustomerId,
                'payment_method_id' => (string) $paymentMethodId,
                'card' => [
                    'brand' => data_get($paymentMethod, 'card.brand'),
                    'last_four' => data_get($paymentMethod, 'card.last4'),
                    'exp_month' => data_get($paymentMethod, 'card.exp_month'),
                    'exp_year' => data_get($paymentMethod, 'card.exp_year'),
                    'funding' => data_get($paymentMethod, 'card.funding'),
                    'country' => data_get($paymentMethod, 'card.country'),
                    'fingerprint' => $resolvedPaymentMethod['fingerprint'],
                ],
                'setup_intent_id' => $setupIntentId,
                'status' => $status,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function processTokenizedCard(ProcessTokenizedCardRequest $request): JsonResponse
    {
        try {
            $payment = StagHerd::createPayment(
                $request->toPaymentRequestData(),
            );

            $paymentArray = $payment->toArray();

            return response()->json([
                'ok' => true,
                'message' => 'Pago con tarjeta guardada procesado correctamente.',
                'payment_id' => $payment->id,
                'payment_intent_id' => data_get(
                    $paymentArray,
                    'references.provider_payment_id',
                ) ?? data_get(
                    $paymentArray,
                    'metadata.stripe_payment_intent_id',
                ),
                'status' => data_get($paymentArray, 'status'),
                'provider_status' => data_get($paymentArray, 'provider_status'),
                'next_action' => data_get($paymentArray, 'next_action'),
                'payment' => $paymentArray,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function createPaymentIntent(CreatePaymentIntentRequest $request): JsonResponse
    {
        try {
            $customerId = $request->customerId();

            if ($request->savePaymentMethod() || $customerId) {
                $customerPayload = $this->buildStripeCustomerPayload(
                    payerReference: $request->payerReference(),
                    payerEmail: $request->payerEmail(),
                    payerName: null,
                    source: 'stag-herd-stripe-intent',
                );

                $customerId = $this->ensureStripeCustomerExists(
                    customerId: $customerId,
                    customerPayload: $customerPayload,
                );

                if (! $customerId) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Stripe no regresó customer_id.',
                    ], 422);
                }
            }

            if ($customerId && ! str_starts_with($customerId, 'cus_')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El customer_id de Stripe no es válido.',
                ], 422);
            }

            $intent = $this->stripeGateway->createPaymentIntent(
                payload: $request->basePayload($customerId),
                idempotencyKey: $request->idempotencyKey(),
            );

            $clientSecret = data_get($intent, 'client_secret');
            $paymentIntentId = data_get($intent, 'id');

            if (! $clientSecret) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Stripe no regresó client_secret.',
                    'stripe_response' => $intent,
                ], 422);
            }

            if (! $paymentIntentId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Stripe no regresó payment_intent_id.',
                    'stripe_response' => $intent,
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => 'PaymentIntent de Stripe creado correctamente.',
                'payment_intent_id' => $paymentIntentId,
                'client_secret' => $clientSecret,
                'customer_id' => $customerId,
                'payment' => [
                    'provider' => 'stripe',
                    'method' => 'card',
                    'status' => data_get($intent, 'status'),
                    'provider_status' => data_get($intent, 'status'),
                    'references' => [
                        'provider_payment_id' => $paymentIntentId,
                    ],
                    'metadata' => [
                        'stripe_client_secret' => $clientSecret,
                        'stripe_payment_intent_id' => $paymentIntentId,
                        'stripe_customer_id' => $customerId,
                    ],
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function confirmPaymentIntent(ConfirmPaymentIntentRequest $request): JsonResponse
    {
        try {
            $providerPaymentId = $request->providerPaymentId();

            $stripeResponse = $this->stripeGateway->getPaymentIntent($providerPaymentId);

            $result = $this->stripeResultMapper->mapPaymentIntentResponseToResult(
                method: 'card',
                response: $stripeResponse,
            );

            $providerStatus = strtolower((string) ($result->providerStatus ?? ''));

            if (! in_array($providerStatus, ['succeeded', 'processing', 'requires_capture'], true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Stripe todavía no confirmó el pago.',
                    'provider_status' => $result->providerStatus,
                    'stripe_response' => $stripeResponse,
                ], 422);
            }

            $metadata = $request->inputMetadata();

            $customerId = data_get($stripeResponse, 'customer');
            $paymentMethodId = data_get($stripeResponse, 'payment_method');

            $paymentMethod = null;
            $resolvedPaymentMethod = null;

            if (is_string($paymentMethodId) && str_starts_with($paymentMethodId, 'pm_')) {
                if (
                    is_string($customerId) &&
                    $customerId !== '' &&
                    str_starts_with($customerId, 'cus_')
                ) {
                    $resolvedPaymentMethod = $this->stripeCardReuse->resolve(
                        $customerId,
                        $paymentMethodId,
                    );

                    $paymentMethodId = $resolvedPaymentMethod['payment_method_id'];
                    $paymentMethod = $resolvedPaymentMethod['payment_method'];
                } else {
                    $paymentMethod = $this->stripeGateway->getPaymentMethod($paymentMethodId);
                }
            }

            $metadata = array_replace_recursive($metadata, [
                'source' => 'stag-herd-stripe-host-ui-confirm',
                'stripe_payment_intent_id' => $providerPaymentId,
                'stripe_status_from_client' => $request->stripeStatus(),
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => $paymentMethodId,
                'stripe_original_payment_method_id' => $resolvedPaymentMethod['original_payment_method_id'] ?? $paymentMethodId,
                'stripe_payment_method_deduplicated' => $resolvedPaymentMethod['duplicated'] ?? false,
                'stripe_payment_method_fingerprint' => $resolvedPaymentMethod['fingerprint'] ?? data_get($paymentMethod, 'card.fingerprint'),
                'card' => array_filter([
                    'brand' => data_get($paymentMethod, 'card.brand'),
                    'last4' => data_get($paymentMethod, 'card.last4'),
                    'exp_month' => data_get($paymentMethod, 'card.exp_month'),
                    'exp_year' => data_get($paymentMethod, 'card.exp_year'),
                    'fingerprint' => $resolvedPaymentMethod['fingerprint'] ?? data_get($paymentMethod, 'card.fingerprint'),
                ], fn($value) => $value !== null && $value !== ''),
            ]);

            $metadata = $this->cleanMetadata($metadata);

            $requestData = new PaymentRequestData(
                amount: (int) ($result->amount ?? 0),
                currency: strtoupper((string) ($result->currency ?? 'MXN')),
                method: 'card',
                provider: 'stripe',
                externalReference: $request->externalReference()
                    ?? data_get($stripeResponse, 'metadata.external_reference'),
                payerReference: $request->payerReference()
                    ?? data_get($stripeResponse, 'metadata.payer_reference')
                    ?? data_get($metadata, 'id_client'),
                payerEmail: $request->payerEmail()
                    ?? data_get($stripeResponse, 'receipt_email'),
                description: $request->description()
                    ?? data_get($stripeResponse, 'description')
                    ?? 'Payment from Stripe Card Element',
                returnUrl: data_get($metadata, 'return_url'),
                cancelUrl: null,
                metadata: $metadata,
            );

            $payment = $this->paymentRepository->storeFromResult(
                request: $requestData,
                result: $result,
            );

            return response()->json([
                'ok' => true,
                'message' => 'Pago Stripe confirmado y registrado correctamente.',
                'provider_payment_id' => $providerPaymentId,
                'provider_status' => $result->providerStatus,
                'payment' => $payment->toArray(),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function processApplePay(ProcessWalletPaymentRequest $request): JsonResponse
    {
        return $this->processWalletPayment(
            request: $request,
            method: 'apple_pay',
            source: 'stag-herd-stripe-apple-pay',
            successMessage: 'Pago con Apple Pay procesado correctamente.',
            defaultDescription: 'Payment with Apple Pay',
        );
    }

    public function processGooglePay(ProcessWalletPaymentRequest $request): JsonResponse
    {
        return $this->processWalletPayment(
            request: $request,
            method: 'google_pay',
            source: 'stag-herd-stripe-google-pay',
            successMessage: 'Pago con Google Pay procesado correctamente.',
            defaultDescription: 'Payment with Google Pay',
        );
    }

    private function processWalletPayment(
        ProcessWalletPaymentRequest $request,
        string $method,
        string $source,
        string $successMessage,
        string $defaultDescription,
    ): JsonResponse {
        try {
            $payment = StagHerd::createPayment(
                $request->toPaymentRequestData($method, $source, $defaultDescription),
            );

            $paymentArray = $payment->toArray();
            $clientSecret = data_get($paymentArray, 'metadata.stripe_client_secret');

            if (! $clientSecret) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Stripe no regresó client_secret para el wallet payment.',
                    'payment' => $paymentArray,
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => $successMessage,
                'payment_id' => $payment->id,
                'payment_intent_id' => data_get(
                    $paymentArray,
                    'references.provider_payment_id',
                ) ?? data_get(
                    $paymentArray,
                    'metadata.stripe_payment_intent_id',
                ),
                'client_secret' => $clientSecret,
                'status' => data_get($paymentArray, 'status'),
                'provider_status' => data_get($paymentArray, 'provider_status'),
                'next_action' => data_get($paymentArray, 'next_action'),
                'payment' => $paymentArray,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function errorResponse(Throwable $exception): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'type' => class_basename($exception),
            'message' => $exception->getMessage(),
            'file' => config('app.debug') ? $exception->getFile() : null,
            'line' => config('app.debug') ? $exception->getLine() : null,
        ], 422);
    }

    private function cleanMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->cleanMetadata($value);

                if ($nested === []) {
                    continue;
                }

                $clean[$key] = $nested;

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function buildStripeCustomerPayload(
        ?string $payerReference,
        ?string $payerEmail,
        ?string $payerName = null,
        string $source = 'stag-herd-stripe',
    ): array {
        return array_filter(
            [
                'email' => $payerEmail ?: null,
                'name' => $payerName ?: null,
                'metadata' => array_filter(
                    [
                        'payer_reference' => $payerReference ?: null,
                        'source' => $source,
                    ],
                    fn($value) => $value !== null && $value !== '',
                ),
            ],
            fn($value) => $value !== null && $value !== '' && $value !== [],
        );
    }

    private function createStripeCustomer(array $payload): array
    {
        return $this->stripeGateway->createCustomer(
            payload: $payload,
            idempotencyKey: 'stag-herd-stripe-customer-' . (string) Str::uuid(),
        );
    }

    private function ensureStripeCustomerExists(
        ?string $customerId,
        array $customerPayload,
    ): ?string {
        if (! $customerId) {
            $customer = $this->createStripeCustomer($customerPayload);

            return data_get($customer, 'id');
        }

        try {
            $customer = $this->stripeGateway->getCustomer($customerId);

            if (data_get($customer, 'deleted') === true) {
                $customer = $this->createStripeCustomer($customerPayload);

                return data_get($customer, 'id');
            }

            $normalizedCustomerId = data_get($customer, 'id', $customerId);

            if (
                is_string($normalizedCustomerId)
                && $normalizedCustomerId !== ''
                && ! empty($customerPayload)
            ) {
                $this->stripeGateway->updateCustomer(
                    $normalizedCustomerId,
                    $customerPayload,
                );
            }

            return $normalizedCustomerId;
        } catch (ProviderCommunicationException $exception) {
            $status = data_get($exception->getErrors(), 'status');
            $code = data_get($exception->getErrors(), 'response.error.code');
            $param = data_get($exception->getErrors(), 'response.error.param');

            if ($status === 404 || ($status === 400 && $code === 'resource_missing' && $param === 'customer')) {
                $customer = $this->createStripeCustomer($customerPayload);

                return data_get($customer, 'id');
            }

            throw $exception;
        }
    }
}
