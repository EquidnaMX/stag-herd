<?php

/**
 * Mercado Pago Payment Handler.
 *
 * Manages interactions with Mercado Pago API via MercadoPagoAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Equidna\StagHerd\Contracts\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentMethod;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Exception;
use Illuminate\Http\Request;
use Throwable;

/**
 * Handles Mercado Pago specific payment logic.
 */
class MercadoPagoHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = PaymentMethod::MERCADOPAGO->value;

    public const CFDI_PAYMENT_FORM = '04';

    private MercadoPagoGateway $mercadopago_adapter;

    /**
     * Creates a new MercadoPagoHandler instance.
     *
     * @param float             $amount      Payment amount.
     * @param PayableOrder|null $order       Order context.
     * @param PaymentData|null  $method_data Method specific data.
     */
    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?PaymentData $method_data = null,
        ?MercadoPagoGateway $mercadopago_adapter = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data,
        );
        $this->mercadopago_adapter = $mercadopago_adapter ?? app(MercadoPagoGateway::class);
    }

    /**
     * Requests payment from Mercado Pago.
     *
     * @return PaymentResult
     */
    public function requestPayment(): PaymentResult
    {
        $paymentId = $this->getMethodData('payment_method_id');
        $orderId = $this->getMethodData('order_id');

        $payment_details = null;
        $payment_status = null;
        $payment_status_detail = null;
        $reason = null;

        try {
            if ($orderId) {
                $payment_details = $this->mercadopago_adapter->getOrderDetails((string) $orderId);

                $payment_status = $payment_details->status ?? null;
                $payment_status_detail = $payment_details->status_detail ?? null;

                if ($payment_status_detail === 'accredited') {
                    return PaymentResult::success(
                        result: 'APPROVED',
                        method_id: (string) $paymentId,
                    );
                }

                return PaymentResult::pending(
                    method_id: (string) $paymentId,
                    reason: 'MercadoPago status: ' . ($payment_status_detail ?? $payment_status ?? 'pending'),
                );
            }

            throw new BadRequestException('Missing Mercado Pago order_id');
        } catch (Exception $e) {
            $reason = $e->getMessage();
        }

        return PaymentResult::declined(
            reason: $reason ?: 'MercadoPago status: Unknown',
        );
    }

    /**
     * Validates payment result.
     *
     * @param object $paymentModel
     *
     * @return PaymentResult
     */
    protected function validatePayment(object $paymentModel): PaymentResult
    {
        try {
            $methodData = json_decode($paymentModel->method_data ?? '{}');

            $orderId = $methodData->order_id ?? null;
            $paymentId = $methodData->payment_method_id ?? $paymentModel->method_id;

            if (!$orderId) {
                throw new InvalidPaymentMethodException('Missing Mercado Pago order_id');
            }

            $mercadopago_result = $this->mercadopago_adapter->getOrderDetails((string) $orderId);

            $amountValue = $mercadopago_result->total_paid_amount
                ?? $mercadopago_result->total_amount
                ?? null;

            if ((float) $amountValue != (float) $paymentModel->amount) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = $mercadopago_result->status ?? null;
            $statusDetail = $mercadopago_result->status_detail ?? null;

            if ($statusDetail === 'accredited') {
                return PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentId,
                );
            }

            return PaymentResult::pending(
                method_id: (string) $paymentId,
                reason: 'MercadoPago Status: ' . ($statusDetail ?? $status ?? 'unknown'),
            );
        } catch (Exception $e) {
            return PaymentResult::declined($e->getMessage());
        }
    }

    /**
     * Cancels payment (refunds).
     *
     * @param object $paymentModel
     *
     * @return PaymentResult
     */
    public function cancelPayment(object $paymentModel): PaymentResult
    {
        try {
            $paymentDetails = $this->mercadopago_adapter->getPaymentDetails((string) $paymentModel->method_id);
            $status = strtolower($paymentDetails->status ?? '');

            if ($status === 'approved') {
                $this->mercadopago_adapter->getRefund(
                    $paymentModel->method_id,
                    $paymentModel->amount,
                );
            }

            return PaymentResult::canceled('Pago cancelado con éxito');
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    /**
     * Verifies webhook signature.
     *
     * @param Request $request
     *
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyWebhook(Request $request): array
    {
        $secret = (string) config('stag-herd.mercadopago.webhook_secret');

        return WebhookVerifier::verifyMercadoPagoSignature($request, $secret);
    }

    /**
     * Processes validated webhook event.
     *
     * @param Request $request
     *
     * @return void
     */
    public static function processWebhook(Request $request): void
    {
        $data = $request->json()->all();
        $methodId = data_get($data, 'data.id') ?? data_get($data, 'id');
        $type = (string) data_get($data, 'type', '');
        $action = (string) data_get($data, 'action', '');

        if (!$methodId) {
            return;
        }

        $payment = self::resolvePayment([$methodId]);
        if (is_null($payment)) {
            return;
        }

        if (str_contains($type, 'chargeback') || str_contains($action, 'chargeback')) {
            $payment->applyResult(PaymentResult::chargeback('Mercado Pago chargeback'));

            return;
        }

        if (str_contains($action, 'refund')) {
            $payment->applyResult(PaymentResult::refunded('Mercado Pago refund'));

            return;
        }

        $payment->approvePayment();
    }

    public function approvePayment(object $paymentModel): PaymentResult
    {
        return parent::approvePayment($paymentModel);
    }

    /**
     * Attempts to resolve a payment using a list of candidate method IDs.
     *
     * @param array<int, mixed> $candidates
     *
     * @return Payment|null
     */
    private static function resolvePayment(array $candidates): ?Payment
    {
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            try {
                return Payment::fromMethodID('MERCADOPAGO', $candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
