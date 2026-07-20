<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Exceptions\ProviderCommunicationException;
use Equidna\StagHerd\Facades\StagHerd;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeCardReuse;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeResultMapper;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Throwable;

class StripeController extends Controller
{
    public function __construct(
        private readonly StripeGateway $stripeGateway,
        private readonly PaymentRepository $paymentRepository,
        private readonly StripeResultMapper $stripeResultMapper,
        private readonly StripeCardReuse $stripeCardReuse,
    ) {}
    public function createSetupIntent(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'customer_id' => ['nullable', 'string', 'max:255'],
                'payer_reference' => ['required', 'string', 'max:255'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'payer_name' => ['nullable', 'string', 'max:255'],
                'return_url' => ['nullable', 'url', 'max:500'],
                'idempotency_key' => ['nullable', 'string', 'max:255'],
                'metadata' => ['nullable', 'array'],
            ]);

            $payerReference = trim((string) $data['payer_reference']);
            $customerId = isset($data['customer_id'])
                ? trim((string) $data['customer_id'])
                : null;

            $customerPayload = $this->buildStripeCustomerPayload(
                payerReference: $payerReference,
                payerEmail: $data['payer_email'] ?? null,
                payerName: $data['payer_name'] ?? null,
                source: 'stag-herd-stripe-setup',
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

            if (! str_starts_with($customerId, 'cus_')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El customer_id de Stripe no es válido.',
                ], 422);
            }

            $idempotencyKey = substr(
                (string) (
                    $data['idempotency_key']
                    ?? $request->header('X-Idempotency-Key')
                    ?? 'stag-herd-stripe-setup-' . Str::uuid()
                ),
                0,
                255,
            );

            $customMetadata = $this->cleanMetadata($data['metadata'] ?? []);

            $setupIntent = $this->stripeGateway->createSetupIntent(
                payload: array_filter(
                    [
                        'customer' => $customerId,
                        'payment_method_types' => ['card'],
                        'usage' => 'off_session',
                        'return_url' => $data['return_url'] ?? null,
                        'metadata' => array_replace_recursive(
                            $customMetadata,
                            [
                                'payer_reference' => $payerReference,
                                'source' => 'stag-herd-stripe-setup',
                            ],
                        ),
                    ],
                    fn($value) => $value !== null && $value !== '' && $value !== [],
                ),
                idempotencyKey: $idempotencyKey,
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
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => config('app.debug') ? $exception->getFile() : null,
                'line' => config('app.debug') ? $exception->getLine() : null,
            ], 422);
        }
    }

    public function completeSetupIntent(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'setup_intent_id' => ['required', 'string', 'max:255'],
                'customer_id' => ['required', 'string', 'max:255'],
            ]);

            $setupIntent = $this->stripeGateway->getSetupIntent(
                $data['setup_intent_id'],
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

            if ((string) $setupCustomerId !== (string) $data['customer_id']) {
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
            return response()->json([
                'ok' => false,
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => config('app.debug') ? $exception->getFile() : null,
                'line' => config('app.debug') ? $exception->getLine() : null,
            ], 422);
        }
    }

    public function processTokenizedCard(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['required', 'string', 'size:3'],
                'external_reference' => ['nullable', 'string', 'max:255'],
                'payer_reference' => ['nullable', 'string', 'max:255'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
                'customer_id' => ['required', 'string', 'max:255'],
                'payment_method_id' => ['required', 'string', 'max:255'],
                'off_session' => ['nullable', 'boolean'],
                'return_url' => ['nullable', 'url', 'max:500'],
                'idempotency_key' => ['nullable', 'string', 'max:255'],
                'metadata' => ['nullable', 'array'],
            ]);

            if (! str_starts_with($data['customer_id'], 'cus_')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El customer_id de Stripe no es válido.',
                ], 422);
            }

            if (! str_starts_with($data['payment_method_id'], 'pm_')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El payment_method_id de Stripe no es válido.',
                ], 422);
            }

            $externalReference = $data['external_reference']
                ?? 'STRIPE-TOKENIZED-' . now()->format('YmdHis');

            $idempotencyKey = substr(
                (string) (
                    $data['idempotency_key']
                    ?? $request->header('X-Idempotency-Key')
                    ?? 'stag-herd-stripe-tokenized-' . Str::uuid()
                ),
                0,
                255,
            );

            $metadata = array_replace_recursive(
                $this->cleanMetadata($data['metadata'] ?? []),
                [
                    'source' => 'stag-herd-stripe-tokenized-card',
                    'external_reference' => $externalReference,
                    'stripe' => [
                        'customer' => $data['customer_id'],
                        'payment_method' => $data['payment_method_id'],
                        'off_session' => (bool) ($data['off_session'] ?? false),
                        'return_url' => $data['return_url'] ?? null,
                        'idempotency_key' => $idempotencyKey,
                    ],
                ],
            );

            $payment = StagHerd::createPayment(
                new PaymentRequestData(
                    amount: MoneyFormatter::fromDecimal($data['amount']),
                    currency: strtoupper($data['currency']),
                    method: 'tokenized_card',
                    provider: 'stripe',
                    externalReference: $externalReference,
                    payerReference: $data['payer_reference'] ?? null,
                    payerEmail: $data['payer_email'] ?? null,
                    description: $data['description']
                        ?? 'Payment with stored Stripe card',
                    returnUrl: $data['return_url'] ?? null,
                    metadata: $this->cleanMetadata($metadata),
                ),
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
            return response()->json([
                'ok' => false,
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => config('app.debug') ? $exception->getFile() : null,
                'line' => config('app.debug') ? $exception->getLine() : null,
            ], 422);
        }
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['required', 'string', 'size:3'],
                'external_reference' => ['nullable', 'string', 'max:255'],
                'payer_reference' => ['nullable', 'string', 'max:255'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],

                'idempotency_key' => ['nullable', 'string', 'max:255'],

                'metadata' => ['nullable', 'array'],

                'stripe' => ['nullable', 'array'],
                'stripe.customer' => ['nullable', 'string', 'max:255'],
                'stripe.payment_method' => ['nullable', 'string', 'max:255'],
                'stripe.capture_method' => ['nullable', 'string', 'max:50'],
                'stripe.statement_descriptor' => ['nullable', 'string', 'max:22'],
                'stripe.statement_descriptor_suffix' => ['nullable', 'string', 'max:22'],
                'stripe.setup_future_usage' => ['nullable', 'string', 'max:50'],
                'stripe.save_payment_method' => ['nullable', 'boolean'],
                'stripe.return_url' => ['nullable', 'url', 'max:500'],
                'stripe.metadata' => ['nullable', 'array'],
            ]);

            $externalReference = $data['external_reference']
                ?? 'STRIPE-' . now()->format('YmdHis');

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);
            $stripeInput = $this->cleanMetadata($data['stripe'] ?? []);
            $stripeMetadata = $this->cleanMetadata($data['stripe']['metadata'] ?? []);

            $idempotencyKey = substr(
                (string) (
                    $data['idempotency_key']
                    ?? $request->header('X-Idempotency-Key')
                    ?? 'stag-herd-stripe-intent-' . Str::uuid()
                ),
                0,
                255,
            );

            $savePaymentMethod = filter_var(
                $stripeInput['save_payment_method']
                    ?? data_get($metadata, 'save_payment_method', false),
                FILTER_VALIDATE_BOOL
            );

            $customerId = ! empty($stripeInput['customer'])
                ? trim((string) $stripeInput['customer'])
                : null;

            $payerReference = trim((string) ($data['payer_reference'] ?? ''));

            if ($savePaymentMethod && $payerReference === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'payer_reference es requerido para guardar la tarjeta.',
                ], 422);
            }

            if ($savePaymentMethod || $customerId) {
                $customerPayload = $this->buildStripeCustomerPayload(
                    payerReference: $payerReference !== '' ? $payerReference : null,
                    payerEmail: $data['payer_email'] ?? null,
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

            $intentMetadata = array_filter([
                'external_reference' => $externalReference,
                'payer_reference' => $data['payer_reference'] ?? null,
                'source' => 'stag-herd-stripe-host-ui',
                'id_order' => data_get($metadata, 'id_order'),
                'id_client' => data_get($metadata, 'id_client'),
                'offer_id' => data_get($metadata, 'offer_id'),
                'checkout_type' => data_get($metadata, 'checkout_type'),
                'action' => data_get($metadata, 'action'),
                'save_payment_method' => $savePaymentMethod ? 'true' : 'false',
            ], fn($value) => $value !== null && $value !== '');

            $intentMetadata = array_replace_recursive($intentMetadata, $stripeMetadata);

            $payload = [
                'amount' => MoneyFormatter::fromDecimal($data['amount']),
                'currency' => strtolower($data['currency']),
                'payment_method_types' => ['card'],
                'description' => $data['description'] ?? 'Payment from Stripe Card Element',
                'receipt_email' => $data['payer_email'] ?? null,
                'metadata' => $intentMetadata,
            ];

            if (! empty($customerId)) {
                $payload['customer'] = $customerId;
            }

            if (! empty($stripeInput['capture_method'])) {
                $payload['capture_method'] = $stripeInput['capture_method'];
            }

            if (! empty($stripeInput['statement_descriptor'])) {
                $payload['statement_descriptor'] = $stripeInput['statement_descriptor'];
            }

            if (! empty($stripeInput['statement_descriptor_suffix'])) {
                $payload['statement_descriptor_suffix'] = $stripeInput['statement_descriptor_suffix'];
            }

            if (! empty($stripeInput['setup_future_usage'])) {
                $payload['setup_future_usage'] = $stripeInput['setup_future_usage'];
            } elseif ($savePaymentMethod) {
                $payload['setup_future_usage'] = 'off_session';
            }

            $payload = array_filter(
                $payload,
                fn($value) => $value !== null && $value !== ''
            );

            $intent = $this->stripeGateway->createPaymentIntent(
                payload: $payload,
                idempotencyKey: $idempotencyKey,
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
            return response()->json([
                'ok' => false,
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => config('app.debug') ? $exception->getFile() : null,
                'line' => config('app.debug') ? $exception->getLine() : null,
            ], 422);
        }
    }

    public function confirmPaymentIntent(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'provider_payment_id' => ['required', 'string', 'max:255'],
                'stripe_status' => ['nullable', 'string', 'max:255'],
                'metadata' => ['nullable', 'array'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'external_reference' => ['nullable', 'string', 'max:255'],
                'payer_reference' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
            ]);

            $providerPaymentId = (string) $data['provider_payment_id'];

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

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);

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
                'stripe_status_from_client' => $data['stripe_status'] ?? null,
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
                externalReference: $data['external_reference']
                    ?? data_get($stripeResponse, 'metadata.external_reference'),
                payerReference: $data['payer_reference']
                    ?? data_get($stripeResponse, 'metadata.payer_reference')
                    ?? data_get($metadata, 'id_client'),
                payerEmail: $data['payer_email']
                    ?? data_get($stripeResponse, 'receipt_email'),
                description: $data['description']
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
            return response()->json([
                'ok' => false,
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => config('app.debug') ? $exception->getFile() : null,
                'line' => config('app.debug') ? $exception->getLine() : null,
            ], 422);
        }
    }

    public function processApplePay(Request $request): JsonResponse
    {
        return $this->processWalletPayment(
            request: $request,
            method: 'apple_pay',
            source: 'stag-herd-stripe-apple-pay',
            successMessage: 'Pago con Apple Pay procesado correctamente.',
            defaultDescription: 'Payment with Apple Pay',
        );
    }

    public function processGooglePay(Request $request): JsonResponse
    {
        return $this->processWalletPayment(
            request: $request,
            method: 'google_pay',
            source: 'stag-herd-stripe-google-pay',
            successMessage: 'Pago con Google Pay procesado correctamente.',
            defaultDescription: 'Payment with Google Pay',
        );
    }

    /**
     * @param 'apple_pay'|'google_pay' $method
     */
    private function processWalletPayment(
        Request $request,
        string $method,
        string $source,
        string $successMessage,
        string $defaultDescription,
    ): JsonResponse {
        try {
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['required', 'string', 'size:3'],
                'external_reference' => ['nullable', 'string', 'max:255'],
                'payer_reference' => ['nullable', 'string', 'max:255'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
                'customer_id' => ['nullable', 'string', 'max:255'],
                'payment_method_id' => ['required', 'string', 'max:255'],
                'return_url' => ['nullable', 'url', 'max:500'],
                'idempotency_key' => ['nullable', 'string', 'max:255'],
                'metadata' => ['nullable', 'array'],
            ]);

            $customerId = isset($data['customer_id'])
                ? trim((string) $data['customer_id'])
                : null;

            if ($customerId !== null && $customerId !== '' && ! str_starts_with($customerId, 'cus_')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El customer_id de Stripe no es válido.',
                ], 422);
            }

            if (! str_starts_with($data['payment_method_id'], 'pm_')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El payment_method_id de Stripe no es válido.',
                ], 422);
            }

            $externalReference = $data['external_reference']
                ?? strtoupper($method) . '-' . now()->format('YmdHis');

            $idempotencyKey = substr(
                (string) (
                    $data['idempotency_key']
                    ?? $request->header('X-Idempotency-Key')
                    ?? 'stag-herd-stripe-' . $method . '-' . Str::uuid()
                ),
                0,
                255,
            );

            $metadata = array_replace_recursive(
                $this->cleanMetadata($data['metadata'] ?? []),
                [
                    'source' => $source,
                    'external_reference' => $externalReference,
                    'wallet_type' => $method,
                    'stripe' => array_filter([
                        'customer' => $customerId,
                        'payment_method' => $data['payment_method_id'],
                        'confirm' => true,
                        'return_url' => $data['return_url'] ?? null,
                        'idempotency_key' => $idempotencyKey,
                        'metadata' => [
                            'wallet_type' => $method,
                            'source' => $source,
                        ],
                    ], fn($value) => $value !== null && $value !== ''),
                ],
            );

            $payment = StagHerd::createPayment(
                new PaymentRequestData(
                    amount: MoneyFormatter::fromDecimal($data['amount']),
                    currency: strtoupper($data['currency']),
                    method: $method,
                    provider: 'stripe',
                    externalReference: $externalReference,
                    payerReference: $data['payer_reference'] ?? null,
                    payerEmail: $data['payer_email'] ?? null,
                    description: $data['description'] ?? $defaultDescription,
                    returnUrl: $data['return_url'] ?? null,
                    metadata: $this->cleanMetadata($metadata),
                ),
            );

            $paymentArray = $payment->toArray();

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
                'status' => data_get($paymentArray, 'status'),
                'provider_status' => data_get($paymentArray, 'provider_status'),
                'next_action' => data_get($paymentArray, 'next_action'),
                'payment' => $paymentArray,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => config('app.debug') ? $exception->getFile() : null,
                'line' => config('app.debug') ? $exception->getLine() : null,
            ], 422);
        }
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
