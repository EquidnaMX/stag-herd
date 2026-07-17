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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use Throwable;

class PaymentOperationController extends Controller
{
    public function __construct(private readonly PaymentDisplayRepository $payments) {}
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

    private function resolveExternalReference(object $payment): ?string
    {
        return data_get($payment->metadata ?? [], 'external_reference')
            ?? data_get($payment->raw_payload ?? [], 'external_reference')
            ?? data_get($payment->raw_payload ?? [], 'purchase_units.0.reference_id');
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
}
