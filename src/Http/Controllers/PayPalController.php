<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Http\Requests\Payments\PayPal\CreateOrderRequest;
use Equidna\StagHerd\Http\Requests\Payments\PayPal\CaptureOrderRequest;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Support\ProviderRegistry;
use Equidna\StagHerd\Facades\StagHerd;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class PayPalController extends Controller
{
    public function __construct(
        private readonly PayPalGateway $payPalGateway,
        private readonly ProviderRegistry $providers,
    ) {}

    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        try {
            $response = $this->payPalGateway->createOrder(
                payload: $request->payload(),
                idempotencyKey: $request->idempotencyKey(),
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
                'checkout_context' => $request->checkoutContext(),
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

    public function captureOrder(CaptureOrderRequest $request): JsonResponse
    {
        try {
            $payment = StagHerd::createPayment(
                $request->toPaymentRequestData(
                    $this->resolveFirstEnabledMethodForProvider('paypal'),
                ),
            );

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
}
