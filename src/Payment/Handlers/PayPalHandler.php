<?php

/**
 * PayPal Payment Handler.
 *
 * Manages interactions with PayPal API via PayPalAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use App\Classes\Clients\ClientNotifications;
use Equidna\StagHerd\Adapters\PayPalAdapter;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Exception;
use Illuminate\Http\Request;
use stdClass;

/**
 * Handles PayPal specific payment logic.
 */
class PayPalHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = \Equidna\StagHerd\Enums\PaymentMethod::PAYPAL->value;

    public const CFDI_PAYMENT_FORM = '04';

    /**
     * Creates a new PayPalHandler instance.
     *
     * @param float              $amount          Payment amount.
     * @param PayableOrder|null  $order           Order context.
     * @param stdClass|null      $method_data     Extra data.
     * @param PayPalAdapter|null $paypal_adapter  Injected adapter.
     */
    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?stdClass $method_data = null,
        private ?PayPalAdapter $paypal_adapter = null
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data
        );
        $this->paypal_adapter ??= new PayPalAdapter();
    }

    /**
     * Requests payment from PayPal.
     *
     * @return stdClass
     */
    /**
     * Requests payment from PayPal.
     *
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    public function requestPayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        // Don't call parent::requestPayment() as it returns a fresh pending result.
        // We will build our own result.

        $methodId = null;
        if (
            is_object($this->method_data)
            && property_exists($this->method_data, 'payment_method_id')
        ) {
            $methodId = $this->method_data->payment_method_id;
        }

        $link = null;
        $payment_status = null;
        $reason = null;

        try {
            if ($methodId) {
                $payment_details = $this->paypal_adapter->getOrderDetails((string) $methodId);
                $payment_status = $payment_details->status ?? null;
            } else {
                // If no methodId, create new payment
                if (!$methodId) {
                    // Force exception to trigger catch block for new payment creation
                    goto create_payment;
                }
            }
        } catch (Exception $e) {
            create_payment:
            $payment_details = $this->paypal_adapter->requestPayment(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : '')
            );

            $link = (property_exists($payment_details, 'links') && isset($payment_details->links[1]->href))
                ? $payment_details->links[1]->href
                : null;

            $methodId = $payment_details->id ?? null;

            $payment_status = ($payment_details->status ?? null) == 'PAYER_ACTION_REQUIRED'
                ? 'PENDING'
                : ($payment_details->status ?? null);

            // TODO: Abstract ClientNotifications via Event or Interface
            if ($link && $this->order) {
                ClientNotifications::externalPaymentLink(order: $this->order, link: $link);
            }
        }

        $resultStatus = match ($payment_status) {
            'PENDING', 'COMPLETED', 'APPROVED' => 'PENDING',
            default => 'DECLINED',
        };

        if ($resultStatus == 'DECLINED') {
            return \Equidna\StagHerd\Data\PaymentResult::declined(
                reason: 'PayPal status: ' . ($payment_status ?? 'Unknown')
            );
        }

        return \Equidna\StagHerd\Data\PaymentResult::success(
            result: 'PENDING',
            method_id: (string) $methodId,
            link: $link
        );
    }

    /**
     * Validates payment against PayPal API.
     *
     * @param  object $paymentModel
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    protected function validatePayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
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
            $paypal_result = $this->paypal_adapter->getOrderDetails((string) $paymentModel->method_id);

            $amountValue = null;
            if (
                property_exists($paypal_result, 'purchase_units')
                && isset($paypal_result->purchase_units[0]->amount->value)
            ) {
                $amountValue = $paypal_result->purchase_units[0]->amount->value;
            }

            // Allow loose comparison for float
            if (abs((float) $amountValue - $paymentModel->amount) > 0.01) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = $paypal_result->status ?? null;

            if ($status == 'COMPLETED' || $status == 'APPROVED') {
                return \Equidna\StagHerd\Data\PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentModel->method_id
                );
            }

            return \Equidna\StagHerd\Data\PaymentResult::pending(
                method_id: (string) $paymentModel->method_id,
                reason: 'PayPal Status: ' . $status
            );
        } catch (Exception $e) {
            return \Equidna\StagHerd\Data\PaymentResult::declined(
                reason: $e->getMessage()
            );
        }
    }

    /**
     * Cancels the payment (refunds).
     *
     * @param  object $paymentModel
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    public function cancelPayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $this->paypal_adapter->getRefund($paymentModel->method_id, $paymentModel->amount);

            return \Equidna\StagHerd\Data\PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    /**
     * Verifies PayPal webhook signature.
     *
     * @param  Request $request
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
            $clientSecret
        );
    }

    /**
     * Processes verified PayPal webhook event.
     *
     * @param  Request $request
     * @return void
     */
    public static function processWebhook(Request $request): void
    {
        $data = json_decode($request->getContent(), true);
        $dispatch = false;
        $methodId = '';

        switch ($data['event_type'] ?? '') {
            case 'CHECKOUT.ORDER.APPROVED':
                $methodId = (string) ($data['resource']['purchase_units'][0]['payments']['captures'][0]['id'] ?? '');

                if (!$methodId) {
                    $methodId = (string) ($data['resource']['id'] ?? '');
                }

                $dispatch = $methodId !== '';

                break;
            case 'PAYMENT.CAPTURE.COMPLETED':
                $methodId = (string) ($data['resource']['id'] ?? '');
                $dispatch = $methodId !== '';

                break;
        }

        if ($dispatch) {
            $payment = Payment::fromMethodID('PAYPAL', (string) $methodId);
            $payment->approvePayment();
        }
    }
}
