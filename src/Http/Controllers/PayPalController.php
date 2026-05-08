<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Contracts\PayPalGateway;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Payment\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class PayPalController extends Controller
{
    /**
     * Handles the return_url redirect from PayPal.
     */
    public function return(Request $request, PayPalGateway $paypal): JsonResponse
    {
        $orderId = (string) ($request->query('token') ?? $request->query('order_id') ?? $request->query('id') ?? '');

        if ($orderId === '') {
            return response()->json([
                'message' => 'Missing PayPal order id',
                'status' => 'error',
            ], 422);
        }

        try {
            $payment = Payment::fromMethodID('PAYPAL', $orderId);
        } catch (PaymentNotFoundException) {
            return response()->json([
                'message' => 'Payment not found for PayPal order',
                'status' => 'error',
            ], 404);
        }

        try {
            $capture = $paypal->captureOrder($orderId);
        } catch (Throwable $exception) {
            try {
                $capture = $paypal->getOrderDetails($orderId);
            } catch (Throwable) {
                return response()->json([
                    'message' => 'PayPal capture failed',
                    'status' => 'error',
                ], 502);
            }

            if (data_get($capture, 'purchase_units.0.payments.captures.0.status') !== 'COMPLETED') {
                return response()->json([
                    'message' => 'PayPal capture failed',
                    'status' => 'error',
                ], 502);
            }
        }

        $captureStatus = data_get($capture, 'purchase_units.0.payments.captures.0.status');
        $captureId = data_get($capture, 'purchase_units.0.payments.captures.0.id');
        $orderStatus = data_get($capture, 'status');

        $model = $payment->getPaymentModel();
        $payload = json_decode($model->method_data ?? '', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $payload['paypal_order_id'] = $orderId;
        $payload['paypal_order_status'] = $orderStatus;
        $payload['capture_status'] = $captureStatus;
        if (is_string($captureId) && $captureId !== '') {
            $payload['capture_id'] = $captureId;
        }
        $model->method_data = json_encode($payload);

        $result = $captureStatus === 'COMPLETED'
            ? PaymentResult::success(PaymentStatus::APPROVED->value, $orderId)
            : PaymentResult::pending($orderId, reason: 'PayPal capture status: ' . ($captureStatus ?? 'unknown'));

        $payment->applyResult($result);

        return response()->json([
            'message' => 'Payment captured via PayPal.',
            'status' => strtolower($result->result),
            'order_id' => $orderId,
            'capture_id' => $captureId,
        ]);
    }

    /**
     * Handles the cancel_url redirect from PayPal.
     */
    public function cancel(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Payment cancelled by user.',
            'status' => 'canceled',
        ]);
    }
}
