<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Facades\StagHerd;
use Equidna\StagHerd\Infrastructure\Persistence\Models\StagHerdPayment;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Throwable;

class PaymentDemoController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $payments = StagHerdPayment::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    if (ctype_digit($search)) {
                        $query->orWhere('id', $search);
                    }

                    $query
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('provider_payment_id', 'like', "%{$search}%")
                        ->orWhere('provider_order_id', 'like', "%{$search}%")
                        ->orWhere('provider_transaction_id', 'like', "%{$search}%")
                        ->orWhere('provider_refund_id', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate((int) config('stag-herd.ui.payments_per_page', 10))
            ->withQueryString();

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
        ]);

        $provider = strtolower($data['provider']);
        $method = strtolower($data['method']);

        $metadata = [
            'source' => 'stag-herd-default-ui',
        ];

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

        try {
            $payment = StagHerd::createPayment(new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: $method,
                provider: $provider,
                externalReference: $data['external_reference'] ?: 'ORDER-DEMO-' . now()->format('YmdHis'),
                payerReference: $data['payer_reference'] ?: null,
                payerEmail: $data['payer_email'] ?: null,
                description: $data['description'] ?: 'Pago creado desde Stag Herd UI',
                returnUrl: $data['return_url'] ?: null,
                cancelUrl: $data['cancel_url'] ?: null,
                metadata: $metadata,
            ));

            $model = StagHerdPayment::query()->find($payment->id);

            return redirect()
                ->route('stag-herd.payments.index')
                ->with('stag_herd_result', [
                    'action' => 'create',
                    'payment' => $payment->toArray(),
                    'checkout_url' => $model ? $this->resolveCheckoutUrl($model) : null,
                    'raw_payload' => $model?->raw_payload,
                ]);
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }

    public function show(int|string $payment): RedirectResponse
    {
        $model = StagHerdPayment::query()->findOrFail($payment);

        return redirect()
            ->route('stag-herd.payments.index')
            ->with('stag_herd_selected_payment', [
                'payment' => $model->toArray(),
                'checkout_url' => $this->resolveCheckoutUrl($model),
            ]);
    }

    public function lookup(int|string $payment): RedirectResponse
    {
        try {
            $model = StagHerdPayment::query()->findOrFail($payment);

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
            $model = StagHerdPayment::query()->findOrFail($payment);

            $updated = StagHerd::cancelPayment(new PaymentCancellationData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                reason: $request->string('reason')->toString() ?: 'Cancelado desde Stag Herd UI',
            ));

            return $this->redirectWithResult('cancel', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }

    public function refund(Request $request, int|string $payment): RedirectResponse
    {
        try {
            $model = StagHerdPayment::query()->findOrFail($payment);

            $amount = $request->input('amount');

            $updated = StagHerd::refundPayment(new RefundRequestData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                amount: $amount !== null && $amount !== ''
                    ? MoneyFormatter::fromDecimal($amount)
                    : null,
                reason: $request->string('reason')->toString() ?: 'Reembolso desde Stag Herd UI',
            ));

            return $this->redirectWithResult('refund', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }

    public function sync(int|string $payment): RedirectResponse
    {
        try {
            $model = StagHerdPayment::query()->findOrFail($payment);

            $lookup = new PaymentLookupData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                providerPaymentId: $model->provider_payment_id,
                externalReference: $model->external_reference,
                metadata: [
                    'method' => $model->method,
                    'source' => 'stag-herd-local-sync-ui',
                ],
            );

            $fallbackRequest = new PaymentRequestData(
                amount: (int) $model->amount,
                currency: $model->currency,
                method: $model->method,
                provider: $model->provider,
                externalReference: $model->external_reference,
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

                /**
                 * Forma nueva/anidada.
                 */
                'mercado_pago' => ['nullable', 'array'],
                'mercado_pago.token' => ['nullable', 'string'],
                'mercado_pago.payment_method_id' => ['nullable', 'string'],
                'mercado_pago.issuer_id' => ['nullable'],
                'mercado_pago.installments' => ['nullable'],
                'mercado_pago.payer' => ['nullable', 'array'],

                /**
                 * Forma vieja/plana.
                 */
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

            $payment = StagHerd::createPayment(new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: strtolower($data['method'] ?? 'card'),
                provider: strtolower($data['provider'] ?? 'mercado_pago'),
                externalReference: $data['external_reference'] ?? 'ORDER-BRICK-' . now()->format('YmdHis'),
                payerReference: null,
                payerEmail: $payerEmail,
                description: $data['description'] ?? 'Pago desde Mercado Pago Brick',
                metadata: [
                    'source' => 'stag-herd-brick-ui',

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
                    ], fn($value) => $value !== null && $value !== ''),

                    'raw_form_data' => $data['raw_form_data'] ?? null,
                ],
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

    private function resolveCheckoutUrl(StagHerdPayment $payment): ?string
    {
        $payload = $payment->raw_payload ?? [];

        return data_get($payload, 'point_of_interaction.transaction_data.ticket_url')
            ?? data_get($payload, 'transaction_details.external_resource_url')
            ?? data_get($payload, 'init_point')
            ?? data_get($payload, 'sandbox_init_point');
    }

    public function providerLookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'search_type' => ['required', 'string'],
            'search_value' => ['required', 'string', 'max:255'],
        ]);

        $provider = strtolower($data['provider']);
        $searchType = $data['search_type'];
        $searchValue = trim($data['search_value']);

        try {
            if ($provider !== 'mercado_pago') {
                throw new \InvalidArgumentException(
                    'Por ahora la búsqueda directa al provider solo está implementada para mercado_pago.'
                );
            }

            $gateway = app(MercadoPagoGateway::class);

            $response = match ($searchType) {
                'provider_payment_id' => $gateway->getPayment($searchValue),

                'external_reference',
                'provider_order_id' => $gateway->searchPayments([
                    'external_reference' => $searchValue,
                ]),

                default => throw new \InvalidArgumentException(
                    "Tipo de búsqueda no soportado: {$searchType}"
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
            'search_type' => ['required', 'string'],
            'search_value' => ['required', 'string', 'max:255'],

            'method' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $provider = strtolower($data['provider']);
        $searchType = $data['search_type'];
        $searchValue = trim($data['search_value']);

        try {
            $lookup = new PaymentLookupData(
                provider: $provider,
                providerPaymentId: $searchType === 'provider_payment_id' ? $searchValue : null,
                externalReference: $searchType === 'external_reference' ? $searchValue : null,
                metadata: [
                    'method' => strtolower($data['method']),
                    'source' => 'stag-herd-provider-sync-ui',
                ],
            );

            $fallbackRequest = new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: strtolower($data['method']),
                provider: $provider,
                externalReference: $data['external_reference'] ?: (
                    $searchType === 'external_reference' ? $searchValue : null
                ),
                payerReference: $data['payer_reference'] ?: null,
                payerEmail: $data['payer_email'] ?: null,
                description: $data['description'] ?: 'Pago sincronizado desde provider',
                metadata: [
                    'source' => 'stag-herd-provider-sync-ui',
                    'sync_reference_type' => $searchType,
                    'sync_reference_value' => $searchValue,
                ],
            );

            $payment = StagHerd::syncPayment($lookup, $fallbackRequest);

            return $this->redirectWithResult('provider_sync', $payment->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($exception);
        }
    }
}
