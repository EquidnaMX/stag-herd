<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Facades\StagHerd;
use Equidna\StagHerd\Http\Requests\Payments\MercadoPago\ProcessBrickRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class MercadoPagoController extends Controller
{
    public function processBrick(ProcessBrickRequest $request): JsonResponse
    {
        try {
            if (! $request->token()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El Brick no envió token de Mercado Pago.',
                    'received' => $request->validated(),
                ], 422);
            }

            if (! $request->paymentMethodId()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El Brick no envió payment_method_id.',
                    'received' => $request->validated(),
                ], 422);
            }

            $payment = StagHerd::createPayment(
                $request->toPaymentRequestData(),
            );

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
}
