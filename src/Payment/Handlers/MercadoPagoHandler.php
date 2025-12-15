<?php

/**
 * Mercado Pago Payment Handler.
 *
 * Manages interactions with Mercado Pago API via MercadoPagoAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use App\Classes\Clients\ClientNotifications;
use Equidna\StagHerd\Adapters\MercadoPagoAdapter;
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
 * Handles Mercado Pago specific payment logic.
 */
class MercadoPagoHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = \Equidna\StagHerd\Enums\PaymentMethod::MERCADOPAGO->value;

    public const CFDI_PAYMENT_FORM = '04';

    private MercadoPagoAdapter $mercadopago_adapter;

    /**
     * Creates a new MercadoPagoHandler instance.
     *
     * @param float             $amount       Payment amount.
     * @param PayableOrder|null $order        Order context.
     * @param stdClass|null     $method_data  Method specific data.
     */
    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?stdClass $method_data = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data
        );
        $this->mercadopago_adapter = new MercadoPagoAdapter();
    }

    /**
     * Requests payment from Mercado Pago.
     *
     * @return stdClass
     */
    /**
     * Requests payment from Mercado Pago.
     *
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    public function requestPayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        // Don't call parent

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
                $payment_details = $this->mercadopago_adapter->getPaymentDetails((string) $methodId);
                $payment_status = $payment_details->status ?? null;
            } else {
                throw new BadRequestException('No payment method id available');
            }
        } catch (Exception $e) {
            $payment_details = $this->mercadopago_adapter->requestPayment(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : '')
            );

            $link = $payment_details->init_point ?? null;
            $methodId = $payment_details->id ?? null;
            $payment_status = $payment_details->status ?? null;

            if ($link && $this->order) {
                ClientNotifications::externalPaymentLink(
                    order: $this->order,
                    link: $link
                );
            }
        }

        $resultStatus = match ($payment_status) {
            'pending', 'approved' => 'PENDING',
            default => 'DECLINED', // Assuming other states are declined or not yet supported for immediate happy path
        };

        if ($resultStatus == 'DECLINED') {
            return \Equidna\StagHerd\Data\PaymentResult::declined(
                reason: 'MercadoPago status: ' . ($payment_status ?? 'Unknown')
            );
        }

        return \Equidna\StagHerd\Data\PaymentResult::success(
            result: 'PENDING',
            method_id: (string) $methodId,
            link: $link
        );
    }

    /**
     * Validates payment result.
     *
     * @param  object $paymentModel
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    protected function validatePayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $mercadopago_result = $this->mercadopago_adapter->getPaymentDetails((string) $paymentModel->method_id);

            $amountValue = $mercadopago_result->transaction_amount ?? null;

            if ($amountValue != $paymentModel->amount) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = $mercadopago_result->status ?? null;

            if ($status == 'approved') {
                return \Equidna\StagHerd\Data\PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentModel->method_id
                );
            }

            return \Equidna\StagHerd\Data\PaymentResult::pending(
                method_id: (string) $paymentModel->method_id,
                reason: 'MercadoPago Status: ' . $status
            );
        } catch (Exception $e) {
            return \Equidna\StagHerd\Data\PaymentResult::declined($e->getMessage());
        }
    }

    /**
     * Cancels payment (refunds).
     *
     * @param  object $paymentModel
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    public function cancelPayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $this->mercadopago_adapter->getRefund(
                $paymentModel->method_id,
                $paymentModel->amount
            );

            return \Equidna\StagHerd\Data\PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    /**
     * Verifies webhook signature.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyWebhook(Request $request): array
    {
        $secret = (string) config('stag-herd.mercadopago.secret');

        return WebhookVerifier::verifyMercadoPagoSignature($request, $secret);
    }

    /**
     * Processes validated webhook event.
     *
     * @param  Request $request
     * @return void
     */
    public static function processWebhook(Request $request): void
    {
        $data = $request->json()->all();
        $methodId = data_get($data, 'data.id') ?? data_get($data, 'id');

        if ($methodId) {
            $payment = Payment::fromMethodID('MERCADOPAGO', (string) $methodId);
            $payment->approvePayment();
        }
    }
}
