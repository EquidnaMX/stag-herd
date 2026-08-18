<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Http\Requests\Payments\PayPal\CaptureOrderRequest;
use Equidna\StagHerd\Http\Requests\Payments\PayPal\CreateOrderRequest;
use Equidna\StagHerd\Http\Requests\Payments\PayPal\ProcessTokenizedCardRequest;
use Equidna\StagHerd\Http\Requests\Payments\PayPal\RegisterPaymentMethodRequest;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\Services\PayPalPaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class PayPalController extends Controller
{
    public function __construct(
        private readonly PayPalGateway $payPalGateway,
        private readonly PayPalPaymentMethodService $payPalPaymentMethods,
        private readonly PaymentService $payments,
    ) {
    }

    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        try {
            $response = $this->payPalGateway->createOrder(
                payload: $request->payload(),
                idempotencyKey: $request->idempotencyKey(),
            );

            $providerOrderId = data_get($response, 'id');

            if (!$providerOrderId) {
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
            $payment = $this->payments->createPayment(
                $request->toPaymentRequestData('paypal'),
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

    public function processTokenizedCard(ProcessTokenizedCardRequest $request): JsonResponse
    {
        try {
            $payment = $this->payments->createPayment(
                $request->toPaymentRequestData(),
            );

            return response()->json([
                'ok' => true,
                'message' => 'Pago con tarjeta PayPal guardada procesado correctamente.',
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

    public function registerPaymentMethod(RegisterPaymentMethodRequest $request): JsonResponse
    {
        try {
            $paymentMethod = $this->payPalPaymentMethods->register(
                ownerReference: $request->ownerReference(),
                paymentTokenId: $request->paymentTokenId(),
                paymentToken: $request->paymentToken(),
                credentialContext: $request->credentialContext(),
            );

            if ($paymentMethod === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'PayPal payment method could not be registered.',
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => 'PayPal payment method registered correctly.',
                'payment_method' => $paymentMethod->toArray(),
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
}
