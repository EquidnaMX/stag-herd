<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\PaymentDisplayRepository;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\RefundRequestData;
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

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentDisplayRepository $payments,
        private readonly ProviderRegistry $providers,
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
            'source' => 'stag-herd-default-ui',
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
            : strtoupper($provider) . '-DEMO-' . now()->format('YmdHis');

        if ($provider === 'paypal') {
            $returnUrl = $returnUrl ?: route('stag-herd.payments.index');
            $cancelUrl = $cancelUrl ?: route('stag-herd.payments.index');
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
                    : 'Pago creado desde Stag Herd UI',
                returnUrl: ! empty($returnUrl) ? $returnUrl : null,
                cancelUrl: ! empty($cancelUrl) ? $cancelUrl : null,
                metadata: $metadata,
            ));

            $model = $payment->id
                ? $this->payments->findForDisplay($payment->id)
                : null;

            return redirect()
                ->route('stag-herd.payments.index')
                ->with('stag_herd_result', [
                    'action' => 'create',
                    'payment' => $payment->toArray(),
                    'checkout_url' => $model ? $this->resolveCheckoutUrl($model) : null,
                    'raw_payload' => $model->raw_payload ?? [],
                ]);
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }

    public function show(int|string $payment): RedirectResponse
    {
        $model = $this->payments->findForDisplay($payment);

        if (! $model) {
            return $this->redirectWithError(
                new \RuntimeException("No se encontró el pago {$payment}.")
            );
        }

        return redirect()
            ->route('stag-herd.payments.index')
            ->with('stag_herd_selected_payment', [
                'payment' => $this->displayPaymentToArray($model),
                'checkout_url' => $this->resolveCheckoutUrl($model),
            ]);
    }

    public function lookup(int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (! $model) {
                throw new \RuntimeException("No se encontró el pago {$payment}.");
            }

            $updated = StagHerd::lookupPayment(new PaymentLookupData(
                provider: $model->provider,
                paymentId: (string) $model->id,
            ));

            return $this->redirectWithResult('lookup', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }

    public function cancel(Request $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (! $model) {
                throw new \RuntimeException("No se encontró el pago {$payment}.");
            }

            $updated = StagHerd::cancelPayment(new PaymentCancellationData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                reason: $request->string('reason')->toString()
                    ?: 'Cancelado desde Stag Herd UI',
            ));

            return $this->redirectWithResult('cancel', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }

    public function refund(Request $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (! $model) {
                throw new \RuntimeException("No se encontró el pago {$payment}.");
            }

            $amount = $request->input('amount');

            $updated = StagHerd::refundPayment(new RefundRequestData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                amount: $amount !== null && $amount !== ''
                    ? MoneyFormatter::fromDecimal($amount)
                    : null,
                reason: $request->string('reason')->toString()
                    ?: 'Reembolso desde Stag Herd UI',
            ));

            return $this->redirectWithResult('refund', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }

    public function sync(int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (! $model) {
                throw new \RuntimeException("No se encontró el pago {$payment}.");
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
                description: 'Pago sincronizado desde registro local',
                metadata: array_merge($model->metadata ?? [], [
                    'source' => 'stag-herd-local-sync-ui',
                ]),
            );

            $updated = StagHerd::syncPayment($lookup, $fallbackRequest);

            return $this->redirectWithResult('sync', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
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

                'metadata' => ['nullable', 'array'],

                'paypal' => ['nullable', 'array'],
                'paypal.intent' => ['nullable', 'string'],
                'paypal.brand_name' => ['nullable', 'string'],
                'paypal.landing_page' => ['nullable', 'string'],
                'paypal.user_action' => ['nullable', 'string'],
                'paypal.shipping_preference' => ['nullable', 'string'],
                'paypal.invoice_id' => ['nullable', 'string'],
            ]);

            $externalReference = $data['external_reference']
                ?? 'PAYPAL-' . now()->format('YmdHis');

            $amount = MoneyFormatter::fromDecimal($data['amount']);
            $currency = strtoupper($data['currency']);

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);

            $paypalPayload = [
                'intent' => strtoupper((string) data_get($data, 'paypal.intent', 'CAPTURE')),

                'purchase_units' => [
                    array_filter([
                        'reference_id' => $externalReference,
                        'description' => $data['description'] ?? 'Pago desde PayPal Buttons',
                        'custom_id' => data_get($metadata, 'id_client'),
                        'invoice_id' => data_get($data, 'paypal.invoice_id'),

                        'amount' => [
                            'currency_code' => $currency,
                            'value' => MoneyFormatter::toDecimal($amount),
                        ],
                    ], fn($value) => $value !== null && $value !== ''),
                ],

                'application_context' => array_filter([
                    'return_url' => route('stag-herd.payments.index'),
                    'cancel_url' => route('stag-herd.payments.index'),
                    'brand_name' => data_get($data, 'paypal.brand_name', config('app.name')),
                    'landing_page' => data_get($data, 'paypal.landing_page', 'LOGIN'),
                    'user_action' => data_get($data, 'paypal.user_action', 'PAY_NOW'),
                    'shipping_preference' => data_get($data, 'paypal.shipping_preference', 'NO_SHIPPING'),
                ], fn($value) => $value !== null && $value !== ''),
            ];

            $response = app(PayPalGateway::class)->createOrder(
                payload: $paypalPayload,
                idempotencyKey: 'stag-herd-paypal-order-' . $externalReference,
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
                'message' => 'Orden PayPal creada correctamente. Todavía NO se guardó Payment local.',
                'provider_order_id' => $providerOrderId,
                'paypal_order' => $response,
                'checkout_context' => [
                    'amount' => $data['amount'],
                    'currency' => $currency,
                    'external_reference' => $externalReference,
                    'payer_email' => $data['payer_email'] ?? 'cliente@test.com',
                    'description' => $data['description'] ?? 'Pago desde PayPal Buttons',
                    'metadata' => $metadata,
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
                'metadata' => ['nullable', 'array'],
            ]);

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);

            $metadata = array_replace_recursive($metadata, [
                'source' => 'stag-herd-paypal-buttons-ui-after-capture',
                'paypal_order_id' => $data['provider_order_id'],
                'idempotency_key' => 'stag-herd-paypal-capture-' . $data['provider_order_id'],
            ]);

            $metadata = $this->cleanMetadata($metadata);

            $payment = StagHerd::createPayment(new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: $this->resolveFirstEnabledMethodForProvider('paypal'),
                provider: 'paypal',
                providerOrderId: $data['provider_order_id'],
                externalReference: $data['external_reference'] ?? $data['provider_order_id'],
                payerReference: data_get($metadata, 'id_client'),
                payerEmail: $data['payer_email'] ?? 'cliente@test.com',
                description: $data['description'] ?? 'Pago PayPal capturado',
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
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => config('app.debug') ? $exception->getFile() : null,
                'line' => config('app.debug') ? $exception->getLine() : null,
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
                'source' => 'stag-herd-brick-ui',
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
                description: $data['description'] ?? 'Pago desde Mercado Pago Brick',
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

            return redirect()
                ->route('stag-herd.payments.index')
                ->with('stag_herd_provider_result', [
                    'action' => 'provider_lookup',
                    'provider' => $provider,
                    'search_type' => $searchType,
                    'search_value' => $searchValue,
                    'response' => $response,
                ]);
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
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
                'source' => 'stag-herd-provider-sync-ui',
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
                    : 'Pago sincronizado desde provider',
                metadata: $metadata,
            );

            $payment = StagHerd::syncPayment($lookup, $fallbackRequest);

            return $this->redirectWithResult('provider_sync', $payment->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }

    private function redirectWithResult(string $action, array $payment): RedirectResponse
    {
        return redirect()
            ->route('stag-herd.payments.index')
            ->with('stag_herd_result', [
                'action' => $action,
                'payment' => $payment,
            ]);
    }

    private function redirectWithError(Throwable $exception): RedirectResponse
    {
        return redirect()
            ->route('stag-herd.payments.index')
            ->with('stag_herd_error', [
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
            ]);
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
}
