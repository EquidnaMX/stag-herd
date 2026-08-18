<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Http\Requests\Payments\MercadoPago\CreateCheckoutProRequest;
use Equidna\StagHerd\Http\Requests\Payments\MercadoPago\ProcessBrickRequest;
use Equidna\StagHerd\Http\Requests\Payments\MercadoPago\ProcessTokenizedCardRequest;
use Equidna\StagHerd\Http\Requests\Payments\MercadoPago\RegisterPaymentMethodRequest;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Services\MercadoPagoPaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class MercadoPagoController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPaymentMethodService $mercadoPagoPaymentMethods,
        private readonly PaymentService $payments,
    ) {
    }

    public function processBrick(ProcessBrickRequest $request): JsonResponse
    {
        try {
            if (!$request->token()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Mercado Pago Brick did not send a token.',
                    'received' => $request->validated(),
                ], 422);
            }

            if (!$request->paymentMethodId()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Mercado Pago Brick did not send payment_method_id.',
                    'received' => $request->validated(),
                ], 422);
            }

            $payment = $this->payments->createPayment(
                $request->toPaymentRequestData(),
            );

            return response()->json([
                'ok' => true,
                'message' => 'Payment created correctly.',
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

    public function createCheckoutPro(CreateCheckoutProRequest $request): JsonResponse
    {
        try {
            $payment = $this->payments->createPayment(
                $request->toPaymentRequestData(),
            );

            $checkoutUrl = data_get($payment->toArray(), 'next_action.url')
                ?? data_get($payment->toArray(), 'link');

            return response()->json([
                'ok' => true,
                'message' => 'Checkout Pro created correctly.',
                'payment' => $payment->toArray(),
                'checkout_url' => $checkoutUrl,
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

    public function processTokenizedCard(ProcessTokenizedCardRequest $request): JsonResponse
    {
        try {
            $payment = $this->payments->createPayment(
                $request->toPaymentRequestData(),
            );

            return response()->json([
                'ok' => true,
                'message' => 'Stored Mercado Pago card payment processed correctly.',
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

    public function registerPaymentMethod(RegisterPaymentMethodRequest $request): JsonResponse
    {
        try {
            $paymentMethod = $this->mercadoPagoPaymentMethods->register(
                ownerReference: $request->ownerReference(),
                customerId: $request->customerId(),
                cardId: $request->cardId(),
                card: $request->card(),
                credentialContext: $request->credentialContext(),
            );

            if ($paymentMethod === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Mercado Pago payment method could not be registered.',
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Mercado Pago payment method registered correctly.',
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
