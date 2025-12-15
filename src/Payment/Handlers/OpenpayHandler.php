<?php

/**
 * Payment handler for Openpay transactions.
 *
 * Manages Openpay payment requests, validations, and bank charge operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use App\Classes\Clients\ClientNotifications;
use Equidna\StagHerd\Adapters\OpenPayAdapter;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Exception;
use stdClass;

class OpenpayHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = \Equidna\StagHerd\Enums\PaymentMethod::OPENPAY->value;
    public const CFDI_PAYMENT_FORM = '03';

    public function __construct(
        float $amount,
        ?stdClass $method_data = null,
        ?PayableOrder $order = null,
        private ?OpenPayAdapter $openpay_adapter = null
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data
        );
        $this->openpay_adapter ??= new OpenPayAdapter();
    }

    public function requestPayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        // Don't call parent
        
        $methodId = null;
        $link = null;
        $result = 'PENDING';
        $reason = 'Always PENDING';

        try {
            $payment_details = $this->openpay_adapter->createBankCharge(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : ''),
                $this->order ? $this->order->getClient()->getName() : '',
                $this->order ? $this->order->getClient()->getEmail() : ''
            );

            $methodId = $payment_details->id ?? null;
            $link = $payment_details->payment_method->url ?? null;

            if ($link && $this->order) {
                ClientNotifications::externalPaymentLink(
                    order: $this->order,
                    link: $link
                );
            }
        } catch (Exception $e) {
            $result = 'DECLINED';
            $reason = $e->getMessage();
        }

        if ($result == 'DECLINED') {
            return \Equidna\StagHerd\Data\PaymentResult::declined($reason);
        }

        return \Equidna\StagHerd\Data\PaymentResult::success(
            result: 'PENDING',
            method_id: (string) $methodId,
            link: $link
        );
    }

    protected function validatePayment($paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $openpay_result = $this->openpay_adapter->getChargeDetails((string) $paymentModel->method_id);

            $amountValue = $openpay_result->amount ?? null;

            if ($amountValue != $paymentModel->amount) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = $openpay_result->status ?? null;

            if ($status == 'completed') {
                return \Equidna\StagHerd\Data\PaymentResult::success(
                     result: 'APPROVED',
                     method_id: (string) $paymentModel->method_id
                );
            }
            
            return \Equidna\StagHerd\Data\PaymentResult::pending(
                   method_id: (string) $paymentModel->method_id,
                   reason: 'Openpay Status: ' . $status
            );
        } catch (Exception $e) {
            return \Equidna\StagHerd\Data\PaymentResult::declined($e->getMessage());
        }
    }

    public function cancelPayment($paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $this->openpay_adapter->getRefund(
                $paymentModel->method_id,
                $paymentModel->amount
            );

            return \Equidna\StagHerd\Data\PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }
}
