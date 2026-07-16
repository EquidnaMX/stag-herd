<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeResultMapper;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\PaymentDisplayRepository;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Exceptions\ProviderCommunicationException;
use Equidna\StagHerd\Facades\StagHerd;
use Equidna\StagHerd\Support\MoneyFormatter;
use Equidna\StagHerd\Support\ProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

//TODO: Separate this controller into multiple controllers for each provider (Stripe, PayPal, Mercado Pago, etc.)
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentDisplayRepository $payments,
        private readonly ProviderRegistry $providers,
        private readonly StripeGateway $stripeGateway,
        private readonly PaymentRepository $paymentRepository,
        private readonly StripeResultMapper $stripeResultMapper,
    ) {
        //
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $payments = $this->payments->paginateForDisplay(
            search: $search,
            perPage: (int) config('stag-herd.ui.payments_limit', 10),
        );

        return view('stag-herd::payments.index', [
            'payments' => $payments,
            'search' => $search,
            'selectedPayment' => $request->session()->pull('stag_herd_selected_payment'),
            'result' => $request->session()->pull('stag_herd_result'),
            'providerResult' => $request->session()->pull('stag_herd_provider_result'),
            'error' => $request->session()->pull('stag_herd_error'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'method' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],

            'metadata' => ['nullable', 'array'],

            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'return_url' => ['nullable', 'url', 'max:500'],
            'cancel_url' => ['nullable', 'url', 'max:500'],

            'cash_status' => ['nullable', 'string', 'max:255'],

            'mercado_pago_token' => ['nullable', 'string', 'max:500'],
            'mercado_pago_payment_method_id' => ['nullable', 'string', 'max:255'],
            'mercado_pago_issuer_id' => ['nullable', 'string', 'max:255'],
            'mercado_pago_installments' => ['nullable', 'integer', 'min:1'],

            'paypal_brand_name' => ['nullable', 'string', 'max:127'],
            'paypal_landing_page' => ['nullable', 'string', 'max:50'],
            'paypal_user_action' => ['nullable', 'string', 'max:50'],
            'paypal_shipping_preference' => ['nullable', 'string', 'max:50'],
            'paypal_invoice_id' => ['nullable', 'string', 'max:127'],
        ]);

        $provider = strtolower($data['provider']);
        $method = strtolower($data['method']);

        $externalReference = $data['external_reference'] ?? null;
        $payerReference = $data['payer_reference'] ?? null;
        $payerEmail = $data['payer_email'] ?? null;
        $description = $data['description'] ?? null;
        $returnUrl = $data['return_url'] ?? null;
        $cancelUrl = $data['cancel_url'] ?? null;

        $metadata = $this->cleanMetadata($data['metadata'] ?? []);
        $metadata = array_replace_recursive($metadata, [
            'source' => 'stag-herd-host-ui',
        ]);

        if (! empty($externalReference)) {
            $metadata['external_reference'] = $externalReference;
        }

        if ($provider === 'cash') {
            $metadata['cash_status'] = $data['cash_status'] ?? 'approved';
        }

        if ($provider === 'mercado_pago') {
            $metadata['mercado_pago'] = array_filter([
                'token' => $data['mercado_pago_token'] ?? null,
                'payment_method_id' => $data['mercado_pago_payment_method_id'] ?? null,
                'issuer_id' => $data['mercado_pago_issuer_id'] ?? null,
                'installments' => isset($data['mercado_pago_installments'])
                    ? (int) $data['mercado_pago_installments']
                    : null,
            ], fn($value) => $value !== null && $value !== '');
        }

        if ($provider === 'paypal') {
            $metadata['paypal'] = array_filter([
                'intent' => 'CAPTURE',
                'brand_name' => $data['paypal_brand_name'] ?? config('app.name'),
                'landing_page' => $data['paypal_landing_page'] ?? 'LOGIN',
                'user_action' => $data['paypal_user_action'] ?? 'PAY_NOW',
                'shipping_preference' => $data['paypal_shipping_preference'] ?? 'NO_SHIPPING',
                'invoice_id' => $data['paypal_invoice_id'] ?? null,
            ], fn($value) => $value !== null && $value !== '');
        }

        $resolvedExternalReference = ! empty($externalReference)
            ? $externalReference
            : strtoupper($provider) . '-' . now()->format('YmdHis');

        if ($provider === 'paypal') {
            $returnUrl = $returnUrl ?: $this->resolveHostUrl($request);
            $cancelUrl = $cancelUrl ?: $this->resolveHostUrl($request);
        }

        try {
            $payment = StagHerd::createPayment(new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: $method,
                provider: $provider,
                externalReference: $resolvedExternalReference,
                payerReference: ! empty($payerReference) ? $payerReference : null,
                payerEmail: ! empty($payerEmail) ? $payerEmail : null,
                description: ! empty($description)
                    ? $description
                    : 'Payment created from host UI',
                returnUrl: ! empty($returnUrl) ? $returnUrl : null,
                cancelUrl: ! empty($cancelUrl) ? $cancelUrl : null,
                metadata: $metadata,
            ));

            $model = $payment->id
                ? $this->payments->findForDisplay($payment->id)
                : null;

            return $this->redirectToHost($request)
                ->with('stag_herd_result', [
                    'action' => 'create',
                    'payment' => $payment->toArray(),
                    'checkout_url' => $model ? $this->resolveCheckoutUrl($model) : null,
                    'raw_payload' => $model->raw_payload ?? [],
                ]);
        } catch (Throwable $exception) {
            return $this->redirectWithError($request, $exception);
        }
    }

    public function show(Request $request, int|string $payment): RedirectResponse
    {
        $model = $this->payments->findForDisplay($payment);

        if (! $model) {
            return $this->redirectWithError(
                $request,
                new RuntimeException("No se encontró el pago {$payment}.")
            );
        }

        return $this->redirectToHost($request)
            ->with('stag_herd_selected_payment', [
                'payment' => $this->displayPaymentToArray($model),
                'checkout_url' => $this->resolveCheckoutUrl($model),
            ]);
    }

    public function lookup(Request $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (! $model) {
                throw new RuntimeException("No se encontró el pago {$payment}.");
            }

            $updated = StagHerd::lookupPayment(new PaymentLookupData(
                provider: $model->provider,
                paymentId: (string) $model->id,
            ));

            return $this->redirectWithResult($request, 'lookup', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($request, $exception);
        }
    }

    public function cancel(Request $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (! $model) {
                throw new RuntimeException("No se encontró el pago {$payment}.");
            }

            $updated = StagHerd::cancelPayment(new PaymentCancellationData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                reason: $request->string('reason')->toString()
                    ?: 'Cancelled from host UI',
            ));

            return $this->redirectWithResult($request, 'cancel', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($request, $exception);
        }
    }

    public function refund(Request $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (! $model) {
                throw new RuntimeException("No se encontró el pago {$payment}.");
            }

            $amount = $request->input('amount');

            $updated = StagHerd::refundPayment(new RefundRequestData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                amount: $amount !== null && $amount !== ''
                    ? MoneyFormatter::fromDecimal($amount)
                    : null,
                reason: $request->string('reason')->toString()
                    ?: 'Refund from host UI',
            ));

            return $this->redirectWithResult($request, 'refund', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($request, $exception);
        }
    }

    public function sync(Request $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (! $model) {
                throw new RuntimeException("No se encontró el pago {$payment}.");
            }

            $lookup = new PaymentLookupData(
                provider: $model->provider,
                paymentId: (string) $model->id,
            );

            $fallbackRequest = new PaymentRequestData(
                amount: (int) $model->amount,
                currency: $model->currency,
                method: $model->method,
                provider: $model->provider,
                externalReference: $this->resolveExternalReference($model),
                payerReference: $model->payer_reference,
                payerEmail: $model->payer_email,
                description: 'Payment synced from local record',
                metadata: array_merge($model->metadata ?? [], [
                    'source' => 'stag-herd-host-sync-ui',
                ]),
            );

            $updated = StagHerd::syncPayment($lookup, $fallbackRequest);

            return $this->redirectWithResult($request, 'sync', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($request, $exception);
        }
    }

    public function processPayPalCreate(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['required', 'string', 'size:3'],
                'external_reference' => ['nullable', 'string', 'max:255'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
                'return_url' => ['nullable', 'url', 'max:500'],
                'cancel_url' => ['nullable', 'url', 'max:500'],

                'idempotency_key' => ['nullable', 'string', 'max:64'],

                'metadata' => ['nullable', 'array'],

                'paypal' => ['nullable', 'array'],
                'paypal.intent' => ['nullable', 'string'],
                'paypal.brand_name' => ['nullable', 'string'],
                'paypal.landing_page' => ['nullable', 'string'],
                'paypal.user_action' => ['nullable', 'string'],
                'paypal.shipping_preference' => ['nullable', 'string'],
                'paypal.invoice_id' => ['nullable', 'string'],
                'paypal.return_url' => ['nullable', 'url', 'max:500'],
                'paypal.cancel_url' => ['nullable', 'url', 'max:500'],
            ]);

            $externalReference = $data['external_reference']
                ?? 'PAYPAL-' . now()->format('YmdHis');

            $amount = MoneyFormatter::fromDecimal($data['amount']);
            $currency = strtoupper($data['currency']);

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);
            $idempotencyKey = substr(
                (string) (
                    $data['idempotency_key']
                    ?? $request->header('X-Idempotency-Key')
                    ?? 'stag-herd-paypal-order-' . Str::uuid()
                ),
                0,
                64,
            );

            $invoiceId = data_get($data, 'paypal.invoice_id');
            $purchaseUnit = array_filter([
                'reference_id' => $externalReference,
                'description' => $data['description'] ?? 'Payment from PayPal Buttons',
                'custom_id' => data_get($metadata, 'id_client'),
                'invoice_id' => $invoiceId,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => MoneyFormatter::toDecimal($amount),
                ],
            ], fn($value) => $value !== null && $value !== '');

            $returnUrl = $data['return_url']
                ?? data_get($data, 'paypal.return_url')
                ?? $this->resolveHostUrl($request);

            $cancelUrl = $data['cancel_url']
                ?? data_get($data, 'paypal.cancel_url')
                ?? $this->resolveHostUrl($request);

            $paypalPayload = [
                'intent' => strtoupper((string) data_get($data, 'paypal.intent', 'CAPTURE')),
                'purchase_units' => [
                    $purchaseUnit,
                ],
                'application_context' => array_filter([
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'brand_name' => data_get($data, 'paypal.brand_name', config('app.name')),
                    'landing_page' => data_get($data, 'paypal.landing_page', 'LOGIN'),
                    'user_action' => data_get($data, 'paypal.user_action', 'PAY_NOW'),
                    'shipping_preference' => data_get($data, 'paypal.shipping_preference', 'NO_SHIPPING'),
                ], fn($value) => $value !== null && $value !== ''),
            ];

            $response = app(PayPalGateway::class)->createOrder(
                payload: $paypalPayload,
                idempotencyKey: $idempotencyKey,
            );

            $providerOrderId = data_get($response, 'id');

            if (! $providerOrderId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'PayPal no regresó order id.',
                    'response' => $response,
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Orden PayPal creada correctamente. Todavía no se guardó el Payment local.',
                'provider_order_id' => $providerOrderId,
                'paypal_order' => $response,
                'checkout_context' => [
                    'amount' => $data['amount'],
                    'currency' => $currency,
                    'external_reference' => $externalReference,
                    'payer_email' => $data['payer_email'] ?? 'cliente@test.com',
                    'description' => $data['description'] ?? 'Payment from PayPal Buttons',
                    'metadata' => array_replace_recursive($metadata, [
                        'paypal_create_idempotency_key' => $idempotencyKey,
                    ]),
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

    public function processPayPalCapture(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'provider_order_id' => ['required', 'string', 'max:255'],

                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['required', 'string', 'size:3'],
                'external_reference' => ['nullable', 'string', 'max:255'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],

                'idempotency_key' => ['nullable', 'string', 'max:64'],

                'metadata' => ['nullable', 'array'],
            ]);

            $providerOrderId = $data['provider_order_id'];
            $captureIdempotencyKey = substr(
                (string) (
                    $data['idempotency_key']
                    ?? $request->header('X-Idempotency-Key')
                    ?? 'stag-herd-paypal-capture-' . $providerOrderId
                ),
                0,
                64,
            );

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);

            $metadata = array_replace_recursive($metadata, [
                'source' => 'stag-herd-paypal-host-ui-after-capture',
                'paypal_order_id' => $providerOrderId,
                'idempotency_key' => $captureIdempotencyKey,
            ]);

            $metadata = $this->cleanMetadata($metadata);

            $payment = StagHerd::createPayment(new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: $this->resolveFirstEnabledMethodForProvider('paypal'),
                provider: 'paypal',
                providerOrderId: $providerOrderId,
                externalReference: $data['external_reference'] ?? $providerOrderId,
                payerReference: data_get($metadata, 'id_client'),
                payerEmail: $data['payer_email'] ?? 'cliente@test.com',
                description: $data['description'] ?? 'Captured PayPal payment',
                metadata: $metadata,
            ));

            return response()->json([
                'ok' => true,
                'message' => 'Orden PayPal capturada y Payment local creado correctamente.',
                'payment' => $payment->toArray(),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'No ha sido posible procesar el pago',
                'errors' => [
                    $exception->getMessage(),
                ],
            ], 422);
        }
    }

    public function processBrick(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'provider' => ['nullable', 'string'],
                'method' => ['nullable', 'string'],

                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['required', 'string', 'size:3'],
                'external_reference' => ['nullable', 'string', 'max:255'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],

                'idempotency_key' => ['nullable', 'string', 'max:64'],
                'device_id' => ['nullable', 'string', 'max:255'],

                'metadata' => ['nullable', 'array'],

                'mercado_pago' => ['nullable', 'array'],
                'mercado_pago.token' => ['nullable', 'string'],
                'mercado_pago.payment_method_id' => ['nullable', 'string'],
                'mercado_pago.issuer_id' => ['nullable'],
                'mercado_pago.installments' => ['nullable'],
                'mercado_pago.payer' => ['nullable', 'array'],
                'mercado_pago.idempotency_key' => ['nullable', 'string', 'max:64'],
                'mercado_pago.device_id' => ['nullable', 'string', 'max:255'],

                'token' => ['nullable', 'string'],
                'payment_method_id' => ['nullable', 'string'],
                'issuer_id' => ['nullable'],
                'installments' => ['nullable'],
                'payer' => ['nullable', 'array'],

                'raw_form_data' => ['nullable', 'array'],
            ]);

            $mercadoPagoData = $data['mercado_pago'] ?? [];

            $token = data_get($mercadoPagoData, 'token')
                ?? ($data['token'] ?? null);

            $paymentMethodId = data_get($mercadoPagoData, 'payment_method_id')
                ?? ($data['payment_method_id'] ?? null);

            $issuerId = data_get($mercadoPagoData, 'issuer_id')
                ?? ($data['issuer_id'] ?? null);

            $installments = data_get($mercadoPagoData, 'installments')
                ?? ($data['installments'] ?? 1);

            $payerFromMercadoPago = data_get($mercadoPagoData, 'payer', []);
            $payerFromRoot = $data['payer'] ?? [];

            $payerEmail = $data['payer_email']
                ?? data_get($payerFromMercadoPago, 'email')
                ?? data_get($payerFromRoot, 'email')
                ?? data_get($data, 'raw_form_data.payer.email')
                ?? 'cliente@test.com';

            if (! $token) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El Brick no envió token de Mercado Pago.',
                    'received' => $data,
                ], 422);
            }

            if (! $paymentMethodId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El Brick no envió payment_method_id.',
                    'received' => $data,
                ], 422);
            }

            $externalReference = $data['external_reference']
                ?? 'BRICK-' . now()->format('YmdHis');

            $idempotencyKey = substr(
                (string) (
                    data_get($mercadoPagoData, 'idempotency_key')
                    ?? $data['idempotency_key']
                    ?? $request->header('X-Idempotency-Key')
                    ?? Str::uuid()
                ),
                0,
                64,
            );

            $deviceId = data_get($mercadoPagoData, 'device_id')
                ?? $data['device_id']
                ?? null;

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);

            $metadata = array_replace_recursive($metadata, [
                'source' => 'stag-herd-brick-host-ui',
                'external_reference' => $externalReference,
                'mercado_pago' => array_filter([
                    'token' => $token,
                    'payment_method_id' => $paymentMethodId,
                    'issuer_id' => $issuerId,
                    'installments' => (int) $installments,
                    'payer' => array_merge(
                        is_array($payerFromMercadoPago) ? $payerFromMercadoPago : [],
                        is_array($payerFromRoot) ? $payerFromRoot : [],
                        ['email' => $payerEmail],
                    ),
                    'idempotency_key' => $idempotencyKey,
                    'device_id' => $deviceId,
                ], fn($value) => $value !== null && $value !== ''),
                'raw_form_data' => $data['raw_form_data'] ?? null,
            ]);

            $metadata = $this->cleanMetadata($metadata);

            $payment = StagHerd::createPayment(new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: strtolower($data['method'] ?? 'card'),
                provider: strtolower($data['provider'] ?? 'mercado_pago'),
                externalReference: $externalReference,
                payerReference: null,
                payerEmail: $payerEmail,
                description: $data['description'] ?? 'Payment from Mercado Pago Brick',
                metadata: $metadata,
            ));

            return response()->json([
                'ok' => true,
                'message' => 'Pago creado correctamente.',
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

    public function processStripeSetupIntent(Request $request): JsonResponse
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

            $payerReference = trim($data['payer_reference']);
            $customerId = isset($data['customer_id'])
                ? trim((string) $data['customer_id'])
                : null;

            if (! $customerId) {
                $customerIdempotencyKey = substr(
                    'stag-herd-stripe-customer-' . hash('sha256', $payerReference),
                    0,
                    255,
                );

                $customer = $this->stripeGateway->createCustomer(
                    payload: array_filter(
                        [
                            'email' => $data['payer_email'] ?? null,
                            'name' => $data['payer_name'] ?? null,
                            'metadata' => array_filter(
                                [
                                    'payer_reference' => $payerReference,
                                    'source' => 'stag-herd-stripe-setup',
                                ],
                                fn($value) => $value !== null && $value !== '',
                            ),
                        ],
                        fn($value) => $value !== null && $value !== '' && $value !== [],
                    ),
                    idempotencyKey: $customerIdempotencyKey,
                );

                $customerId = data_get($customer, 'id');

                if (! $customerId) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Stripe no regresó customer_id.',
                        'customer' => $customer,
                    ], 422);
                }
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

    public function processStripeSetupComplete(Request $request): JsonResponse
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

            $paymentMethod = $this->stripeGateway->getPaymentMethod(
                (string) $paymentMethodId,
            );

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

    public function processStripeTokenizedCard(Request $request): JsonResponse
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

    public function processStripeIntent(Request $request): JsonResponse
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

    public function processStripeConfirm(Request $request): JsonResponse
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

            if (is_string($paymentMethodId) && str_starts_with($paymentMethodId, 'pm_')) {
                $paymentMethod = $this->stripeGateway->getPaymentMethod($paymentMethodId);
            }

            $metadata = array_replace_recursive($metadata, [
                'source' => 'stag-herd-stripe-host-ui-confirm',
                'stripe_payment_intent_id' => $providerPaymentId,
                'stripe_status_from_client' => $data['stripe_status'] ?? null,
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => $paymentMethodId,
                'card' => array_filter([
                    'brand' => data_get($paymentMethod, 'card.brand'),
                    'last4' => data_get($paymentMethod, 'card.last4'),
                    'exp_month' => data_get($paymentMethod, 'card.exp_month'),
                    'exp_year' => data_get($paymentMethod, 'card.exp_year'),
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

    public function providerLookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'search_type' => ['required', 'string', 'in:provider_payment_id,provider_order_id'],
            'search_value' => ['required', 'string', 'max:255'],
        ]);

        $provider = strtolower($data['provider']);
        $searchType = $data['search_type'];
        $searchValue = trim($data['search_value']);

        try {
            $response = match ($provider) {
                'mercado_pago' => match ($searchType) {
                    'provider_payment_id' => app(MercadoPagoGateway::class)
                        ->getPayment($searchValue),

                    'provider_order_id' => app(MercadoPagoGateway::class)
                        ->searchPayments([
                            'order.id' => $searchValue,
                        ]),

                    default => throw new \InvalidArgumentException(
                        "Tipo de búsqueda no soportado: {$searchType}"
                    ),
                },

                'paypal' => match ($searchType) {
                    'provider_payment_id' => app(PayPalGateway::class)
                        ->getCapture($searchValue),

                    'provider_order_id' => app(PayPalGateway::class)
                        ->getOrder($searchValue),

                    default => throw new \InvalidArgumentException(
                        "Tipo de búsqueda no soportado: {$searchType}"
                    ),
                },

                default => throw new \InvalidArgumentException(
                    "Provider no soportado para búsqueda directa: {$provider}"
                ),
            };

            return $this->redirectToHost($request)
                ->with('stag_herd_provider_result', [
                    'action' => 'provider_lookup',
                    'provider' => $provider,
                    'search_type' => $searchType,
                    'search_value' => $searchValue,
                    'response' => $response,
                ]);
        } catch (Throwable $exception) {
            return $this->redirectWithError($request, $exception);
        }
    }

    public function providerSync(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'search_type' => ['required', 'string', 'in:provider_payment_id,provider_order_id'],
            'search_value' => ['required', 'string', 'max:255'],

            'method' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],

            'metadata' => ['nullable', 'array'],

            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $provider = strtolower($data['provider']);
        $searchType = $data['search_type'];
        $searchValue = trim($data['search_value']);

        $externalReference = ! empty($data['external_reference'])
            ? $data['external_reference']
            : null;

        try {
            $lookup = new PaymentLookupData(
                provider: $provider,
                providerPaymentId: $searchType === 'provider_payment_id' ? $searchValue : null,
                providerOrderId: $searchType === 'provider_order_id' ? $searchValue : null,
            );

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);

            $metadata = array_replace_recursive($metadata, [
                'source' => 'stag-herd-provider-sync-host-ui',
                'sync_reference_type' => $searchType,
                'sync_reference_value' => $searchValue,
            ]);

            if ($externalReference) {
                $metadata['external_reference'] = $externalReference;
            }

            $metadata = $this->cleanMetadata($metadata);

            $fallbackRequest = new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: strtolower($data['method']),
                provider: $provider,
                externalReference: $externalReference,
                payerReference: ! empty($data['payer_reference'])
                    ? $data['payer_reference']
                    : null,
                payerEmail: ! empty($data['payer_email'])
                    ? $data['payer_email']
                    : null,
                description: ! empty($data['description'])
                    ? $data['description']
                    : 'Payment synced from provider',
                metadata: $metadata,
            );

            $payment = StagHerd::syncPayment($lookup, $fallbackRequest);

            return $this->redirectWithResult($request, 'provider_sync', $payment->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($request, $exception);
        }
    }

    private function redirectWithResult(Request $request, string $action, array $payment): RedirectResponse
    {
        return $this->redirectToHost($request)
            ->with('stag_herd_result', [
                'action' => $action,
                'payment' => $payment,
            ]);
    }

    private function redirectWithError(Request $request, Throwable $exception): RedirectResponse
    {
        return $this->redirectToHost($request)
            ->with('stag_herd_error', [
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
            ]);
    }

    private function redirectToHost(Request $request): RedirectResponse
    {
        return redirect()->to($this->resolveHostUrl($request));
    }

    private function resolveHostUrl(Request $request): string
    {
        $explicit = $request->input('return_url')
            ?? $request->input('cancel_url')
            ?? data_get($request->input('paypal'), 'return_url')
            ?? data_get($request->input('paypal'), 'cancel_url');

        if (is_string($explicit) && filter_var($explicit, FILTER_VALIDATE_URL)) {
            return $explicit;
        }

        $referer = $request->headers->get('referer');

        if (is_string($referer) && filter_var($referer, FILTER_VALIDATE_URL)) {
            return $referer;
        }

        $origin = $request->headers->get('origin');

        if (is_string($origin) && filter_var($origin, FILTER_VALIDATE_URL)) {
            return $origin;
        }

        return rtrim((string) config('app.url', '/'), '/');
    }

    private function resolveCheckoutUrl(object $payment): ?string
    {
        $payload = $payment->raw_payload ?? [];

        $mercadoPagoUrl = data_get($payload, 'point_of_interaction.transaction_data.ticket_url')
            ?? data_get($payload, 'transaction_details.external_resource_url')
            ?? data_get($payload, 'init_point')
            ?? data_get($payload, 'sandbox_init_point');

        if ($mercadoPagoUrl) {
            return $mercadoPagoUrl;
        }

        $paypalApproveUrl = $this->resolvePaypalLink($payload, 'approve');

        if ($paypalApproveUrl) {
            return $paypalApproveUrl;
        }

        $nextActionUrl = data_get($payment->next_action ?? [], 'url');

        if ($nextActionUrl) {
            return $nextActionUrl;
        }

        return $payment->link ?? null;
    }

    private function resolvePaypalLink(array $payload, string $rel): ?string
    {
        $links = data_get($payload, 'links', []);

        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (($link['rel'] ?? null) === $rel && ! empty($link['href'])) {
                return (string) $link['href'];
            }
        }

        return null;
    }

    private function resolveExternalReference(object $payment): ?string
    {
        return data_get($payment->metadata ?? [], 'external_reference')
            ?? data_get($payment->raw_payload ?? [], 'external_reference')
            ?? data_get($payment->raw_payload ?? [], 'purchase_units.0.reference_id');
    }

    private function resolveFirstEnabledMethodForProvider(string $provider): string
    {
        $methods = $this->providers->methodsForProvider($provider);

        if ($methods !== []) {
            return $methods[0];
        }

        throw new RuntimeException(
            sprintf('Payment provider [%s] has no enabled methods configured.', strtolower($provider))
        );
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

    private function displayPaymentToArray(object $payment): array
    {
        if (method_exists($payment, 'toArray')) {
            return $payment->toArray();
        }

        return (array) $payment;
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

            return data_get($customer, 'id', $customerId);
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
