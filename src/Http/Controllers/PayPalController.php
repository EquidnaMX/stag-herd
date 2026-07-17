<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Facades\StagHerd;
use Equidna\StagHerd\Support\MoneyFormatter;
use Equidna\StagHerd\Support\ProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PayPalController extends Controller
{
    public function __construct(
        private readonly PayPalGateway $payPalGateway,
        private readonly ProviderRegistry $providers,
    ) {}
    public function createOrder(Request $request): JsonResponse
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

            $response = $this->payPalGateway->createOrder(
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

    public function captureOrder(Request $request): JsonResponse
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
}
