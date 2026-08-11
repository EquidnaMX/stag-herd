<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Application\Actions\DeactivatePaymentMethod;
use Equidna\StagHerd\Application\Actions\ListPaymentMethods;
use Equidna\StagHerd\Application\Actions\SetDefaultPaymentMethod;
use Equidna\StagHerd\Http\Requests\Payments\PaymentMethods\DeactivatePaymentMethodRequest;
use Equidna\StagHerd\Http\Requests\Payments\PaymentMethods\ListPaymentMethodsRequest;
use Equidna\StagHerd\Http\Requests\Payments\PaymentMethods\SetDefaultPaymentMethodRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class PaymentMethodController extends Controller
{
    public function index(
        ListPaymentMethodsRequest $request,
        ListPaymentMethods $action,
    ): JsonResponse {
        try {
            $paymentMethods = $action->handle($request->toData());

            return response()->json([
                'ok' => true,
                'payment_methods' => array_map(
                    static fn($paymentMethod) => $paymentMethod->toArray(),
                    $paymentMethods,
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function setDefault(
        SetDefaultPaymentMethodRequest $request,
        SetDefaultPaymentMethod $action,
    ): JsonResponse {
        try {
            $paymentMethod = $action->handle($request->toData());

            return response()->json([
                'ok' => true,
                'message' => 'Payment method marked as default correctly.',
                'payment_method' => $paymentMethod->toArray(),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function deactivate(
        DeactivatePaymentMethodRequest $request,
        DeactivatePaymentMethod $action,
    ): JsonResponse {
        try {
            $paymentMethod = $action->handle($request->toData());

            return response()->json([
                'ok' => true,
                'message' => 'Payment method deactivated correctly.',
                'payment_method' => $paymentMethod->toArray(),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function errorResponse(Throwable $exception): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'type' => class_basename($exception),
            'message' => $exception->getMessage(),
            'file' => config('app.debug') ? $exception->getFile() : null,
            'line' => config('app.debug') ? $exception->getLine() : null,
        ], 422);
    }
}
