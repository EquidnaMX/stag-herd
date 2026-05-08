<?php

/**
 * PayPal Payment Handler.
 *
 * Manages interactions with PayPal API via PayPalAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Contracts\PayPalGateway;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentMethod;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Exception;
use Illuminate\Http\Request;
use Throwable;

/**
 * Handles PayPal specific payment logic.
 */
class PayPalHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = PaymentMethod::PAYPAL->value;

    public const CFDI_PAYMENT_FORM = '04';

    private PayPalGateway $paypal_adapter;

    /**
     * Creates a new PayPalHandler instance.
     *
     * @param float              $amount         Payment amount.
     * @param PayableOrder|null  $order          Order context.
     * @param PaymentData|null   $method_data    Extra data.
     * @param PayPalGateway|null $paypal_adapter Injected gateway.
     */
    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?PaymentData $method_data = null,
        ?PayPalGateway $paypal_adapter = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data,
        );
        $this->paypal_adapter = $paypal_adapter ?? app(PayPalGateway::class);
    }

    /**
     * Requests payment from PayPal.
     *
     * @return PaymentResult
     */
    public function requestPayment(): PaymentResult
    {
        // Don't call parent::requestPayment() as it returns a fresh pending result.
        // We will build our own result.
        $link = null;
        $payment_status = null;
        $reason = null;
        $methodId = $this->getMethodData('paypal_order_id')
            ?? $this->getMethodData('payment_method_id')
            ?? $this->getMethodData('refund_id');

        try {
            $orderId = $methodId;
            if (!$orderId) {
                throw new BadRequestException('Missing PayPal order id');
            }

            $payment_details = $this->paypal_adapter->getOrderDetails((string) $orderId);
            $payment_status = $payment_details->status ?? null;
        } catch (Exception $e) {
            $returnUrl = $this->getMethodData('return_url')
                ?? config('stag-herd.paypal.return_url')
                ?? route('stag-herd.paypal.return');
            $cancelUrl = $this->getMethodData('cancel_url')
                ?? config('stag-herd.paypal.cancel_url')
                ?? route('stag-herd.paypal.cancel');

            $payment_details = $this->paypal_adapter->requestPayment(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : ''),
                $returnUrl,
                $cancelUrl,
            );

            $link = self::findApprovalLink($payment_details);

            $methodId = $payment_details->id ?? null;

            $payment_status = ($payment_details->status ?? null) == 'PAYER_ACTION_REQUIRED'
                ? 'PENDING'
                : ($payment_details->status ?? null);
        }

        $resultStatus = match ($payment_status) {
            'PENDING', 'COMPLETED', 'APPROVED' => 'PENDING',
            default => 'DECLINED',
        };

        if ($resultStatus == 'DECLINED') {
            return PaymentResult::declined(
                reason: 'PayPal status: ' . ($payment_status ?? 'Unknown'),
            );
        }

        return PaymentResult::pending(
            method_id: (string) $methodId,
            link: $link,
            reason: 'PayPal status: ' . ($payment_status ?? 'PENDING'),
        );
    }

    /**
     * Validates payment against PayPal API.
     *
     * @param object $paymentModel
     *
     * @return PaymentResult
     */
    protected function validatePayment(object $paymentModel): PaymentResult
    {
        // Parent validation basic checks
        // We can't call parent::validatePayment() easily because it returns a Result object now,
        // and if it throws, we stop.
        // We should run the checks ourselves or assume parent checks were done if we call parent.
        // But parent returns a "Always PENDING" result.
        // Let's just do specific validation here.

        // Re-implement basic checks or trust caller?
        // Let's stick to our logic.

        try {
            $data = json_decode($paymentModel->method_data, true);
            $orderId = is_array($data)
                ? ($data['paypal_order_id'] ?? $data['payment_method_id'] ?? $data['refund_id'] ?? $paymentModel->method_id)
                : $paymentModel->method_id;
            $paypal_result = $this->paypal_adapter->getOrderDetails((string) $orderId);

            $amountValue = null;
            if (
                property_exists($paypal_result, 'purchase_units')
                && isset($paypal_result->purchase_units[0]['amount']['value'])
            ) {
                $amountValue = $paypal_result->purchase_units[0]['amount']['value'];
            }

            // Allow loose comparison for float
            if (abs((float) $amountValue - $paymentModel->amount) > 0.01) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $orderStatus = $paypal_result->status ?? null;

            $captureStatus = data_get(
                $paypal_result,
                'purchase_units.0.payments.captures.0.status',
            );

            $captureId = data_get(
                $paypal_result,
                'purchase_units.0.payments.captures.0.id',
            );

            if (
                in_array($orderStatus, ['COMPLETED', 'APPROVED'], true)
                && $captureStatus === 'COMPLETED'
            ) {
                if (!is_null($captureId)) {
                    $payload = is_array($data) ? $data : [];
                    $payload['capture_id'] = $captureId;
                    $payload['capture_status'] = $captureStatus;
                    $payload['paypal_order_status'] = $orderStatus;

                    $paymentModel->method_data = json_encode($payload);
                }

                return PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentModel->method_id,
                );
            }

            return PaymentResult::pending(
                method_id: (string) $paymentModel->method_id,
                reason: "PayPal Order Status: {$orderStatus}, Capture Status: {$captureStatus}",
            );
        } catch (Exception $e) {
            return PaymentResult::declined(
                reason: $e->getMessage(),
            );
        }
    }

    /**
     * Cancels the payment (refunds).
     *
     * @param object $paymentModel
     *
     * @return PaymentResult
     */
    public function cancelPayment(object $paymentModel): PaymentResult
    {
        try {
            $data = json_decode($paymentModel->method_data ?? '', true);
            $captureId = is_array($data) ? ($data['capture_id'] ?? null) : null;

            if (!$captureId) {
                $orderId = is_array($data)
                    ? ($data['refund_id'] ?? $paymentModel->method_id)
                    : $paymentModel->method_id;
                $paypal_result = $this->paypal_adapter->getOrderDetails((string) $orderId);
                $captureId = data_get($paypal_result, 'purchase_units.0.payments.captures.0.id');

                if ($captureId) {
                    $payload = is_array($data) ? $data : [];
                    $payload['capture_id'] = $captureId;
                    $paymentModel->method_data = json_encode($payload);
                }
            }

            $this->paypal_adapter->getRefund((string) ($captureId ?? $paymentModel->method_id), $paymentModel->amount);

            return PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    /**
     * Verifies PayPal webhook signature.
     *
     * @param Request $request
     *
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyWebhook(Request $request): array
    {
        $webhookId = (string) config('stag-herd.paypal.webhook_id');
        $sandbox = (bool) config('stag-herd.paypal.sandbox', true);
        $clientId = (string) config('stag-herd.paypal.client_id');
        $clientSecret = (string) config('stag-herd.paypal.client_secret');

        return WebhookVerifier::verifyPayPalSignature(
            $request,
            $webhookId,
            $sandbox,
            $clientId,
            $clientSecret,
        );
    }

    /**
     * Processes verified PayPal webhook event.
     *
     * @param Request $request
     *
     * @return void
     */
    public static function processWebhook(Request $request): void
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $eventType = (string) ($data['event_type'] ?? '');
        $resource = $data['resource'] ?? [];

        $result = null;
        $candidates = [];

        switch ($eventType) {
            case 'CHECKOUT.ORDER.APPROVED':
            case 'CHECKOUT.ORDER.COMPLETED':
                $candidates = [
                    data_get($resource, 'id'),
                    data_get($resource, 'purchase_units.0.payments.captures.0.id'),
                ];
                $result = 'PENDING';

                break;
            case 'PAYMENT.CAPTURE.COMPLETED':
                $candidates = [
                    data_get($resource, 'id'),
                    data_get($resource, 'supplementary_data.related_ids.order_id'),
                ];
                $result = PaymentStatus::APPROVED->value;

                break;
            case 'PAYMENT.CAPTURE.DENIED':
                $candidates = [
                    data_get($resource, 'id'),
                ];
                $result = PaymentStatus::REJECTED->value;

                break;
            case 'PAYMENT.CAPTURE.REFUNDED':
                $candidates = [
                    data_get($resource, 'id'),
                    data_get($resource, 'supplementary_data.related_ids.order_id'),
                ];
                $result = PaymentStatus::REFUNDED->value;

                break;
            case 'CUSTOMER.DISPUTE.CREATED':
            case 'CUSTOMER.DISPUTE.UPDATED':
            case 'CUSTOMER.DISPUTE.RESOLVED':
                $candidates = [
                    data_get($resource, 'disputed_transactions.0.seller_transaction_id'),
                    data_get($resource, 'disputed_transactions.0.buyer_transaction_id'),
                    data_get($resource, 'dispute_id'),
                ];
                $result = PaymentStatus::CHARGEBACK->value;

                break;
            default:
                return;
        }

        $payment = self::resolvePayment($candidates);
        if (is_null($payment)) {
            return;
        }

        $captureId = data_get($resource, 'purchase_units.0.payments.captures.0.id')
            ?? data_get($resource, 'id');
        self::storeCaptureId($payment, $captureId);

        $paymentResult = match ($result) {
            PaymentStatus::APPROVED->value => PaymentResult::success(
                result: PaymentStatus::APPROVED->value,
                method_id: (string) $payment->getMethodId(),
            ),
            PaymentStatus::REJECTED->value => new PaymentResult(
                error: true,
                result: PaymentStatus::REJECTED->value,
                reason: 'PayPal capture denied',
            ),
            PaymentStatus::REFUNDED->value => PaymentResult::refunded(),
            PaymentStatus::CHARGEBACK->value => PaymentResult::chargeback(),
            default => PaymentResult::pending(
                method_id: (string) $payment->getMethodId(),
                reason: 'PayPal event: ' . $eventType,
            ),
        };

        $payment->applyResult($paymentResult);
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
                return Payment::fromMethodID('PAYPAL', $candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private static function storeCaptureId(Payment $payment, ?string $captureId): void
    {
        if (!$captureId) {
            return;
        }

        $model = $payment->getPaymentModel();
        $payload = json_decode($model->method_data ?? '', true);
        if (!is_array($payload)) {
            $payload = [];
        }

        if (($payload['capture_id'] ?? null) === $captureId) {
            return;
        }

        $payload['capture_id'] = $captureId;
        $model->method_data = json_encode($payload);
    }

    private static function findApprovalLink(object $order): ?string
    {
        $fallback = null;
        $links = data_get($order, 'links', []);

        if (!is_iterable($links)) {
            return null;
        }

        foreach ($links as $link) {
            $href = data_get($link, 'href');
            if (!is_string($href) || $href === '') {
                continue;
            }

            $fallback ??= $href;

            if (data_get($link, 'rel') === 'approve') {
                return $href;
            }
        }

        return $fallback;
    }
}
