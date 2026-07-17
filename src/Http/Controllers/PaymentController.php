<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Contracts\PaymentDisplayRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Facades\StagHerd;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentDisplayRepository $payments) {}
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
