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
use Equidna\StagHerd\Http\Requests\Payments\CancelPaymentRequest;
use Equidna\StagHerd\Http\Requests\Payments\ProviderLookupPaymentRequest;
use Equidna\StagHerd\Http\Requests\Payments\ProviderSyncPaymentRequest;
use Equidna\StagHerd\Http\Requests\Payments\RefundPaymentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use Throwable;

class PaymentOperationController extends Controller
{
    public function __construct(private readonly PaymentDisplayRepository $payments)
    {
    }

    public function lookup(Request $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (!$model) {
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

    public function cancel(CancelPaymentRequest $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (!$model) {
                throw new RuntimeException("No se encontró el pago {$payment}.");
            }

            $updated = StagHerd::cancelPayment(new PaymentCancellationData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                reason: $request->cancelReason(),
            ));

            return $this->redirectWithResult($request, 'cancel', $updated->toArray());
        } catch (Throwable $exception) {
            return $this->redirectWithError($request, $exception);
        }
    }

    public function refund(RefundPaymentRequest $request, int|string $payment): RedirectResponse
    {
        try {
            $model = $this->payments->findForDisplay($payment);

            if (!$model) {
                throw new RuntimeException("No se encontró el pago {$payment}.");
            }

            $updated = StagHerd::refundPayment(new RefundRequestData(
                provider: $model->provider,
                paymentId: (string) $model->id,
                amount: $request->refundAmount(),
                reason: $request->refundReason(),
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

            if (!$model) {
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

    public function providerLookup(ProviderLookupPaymentRequest $request): RedirectResponse
    {
        $provider = $request->provider();
        $searchType = $request->searchType();
        $searchValue = $request->searchValue();

        try {
            $response = match ($provider) {
                'mercado_pago' => match ($searchType) {
                    'provider_payment_id' => app(MercadoPagoGateway::class)->getPayment($searchValue),
                    'provider_order_id' => app(MercadoPagoGateway::class)->searchPayments([
                        'order.id' => $searchValue,
                    ]),
                    default => throw new \InvalidArgumentException(
                        "Tipo de búsqueda no soportado: {$searchType}"
                    ),
                },

                'paypal' => match ($searchType) {
                    'provider_payment_id' => app(PayPalGateway::class)->getCapture($searchValue),
                    'provider_order_id' => app(PayPalGateway::class)->getOrder($searchValue),
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

    public function providerSync(ProviderSyncPaymentRequest $request): RedirectResponse
    {
        try {
            $payment = StagHerd::syncPayment(
                $request->lookupData(),
                $request->fallbackRequestData(),
            );

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
}
