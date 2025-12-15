<?php

/**
 * Payment Manager Service.
 *
 * Orchestrates payment creation, retrieval, and handler delegation.
 * Decouples the Payment model from business logic.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment;

use Carbon\Carbon;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Exceptions\DuplicatePaymentMethodIdException;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Payment\Handlers\PaymentHandler;

/**
 * Service class for managing payment lifecycles.
 */
class PaymentManager
{
    /**
     * Creates a new PaymentManager instance.
     *
     * @param PaymentRepository $repository  The repository for payment persistence.
     */
    public function __construct(
        protected PaymentRepository $repository
    ) {
        //
    }

    /**
     * Initiates a new payment request.
     *
     * validating the method and checking for duplicate transactions if required.
     *
     * @param  float                           $amount       The amount to charge.
     * @param  string                          $method       The payment method key (e.g., 'PAYPAL').
     * @param  PayableOrder                    $order        The order being paid.
     * @param  mixed                           $method_data  Additional method-specific data.
     * @return Payment                                       The created payment instance.
     * @throws DuplicatePaymentMethodIdException             If the payment method ID already exists.
     * @throws PaymentDeclinedException                      If the payment is declined by the handler.
     * @throws InvalidPaymentMethodException                 If the method is invalid.
     */
    public function request(
        float $amount,
        string $method,
        PayableOrder $order,
        mixed $method_data = null
    ): Payment {
        // Coerce to DTO
        $data = PaymentData::fromMixed($method_data);

        $handler = $this->getHandler(
            method: $method,
            amount: $amount,
            order: $order,
            method_data: $data
        );

        if (
            $data->payment_method_id
            && !$handler::ALLOW_DUPLICATED_METHOD_ID
        ) {
            $duplicated_payment = $this->repository->findByMethodId($method, $data->payment_method_id);

            if (!is_null($duplicated_payment)) {
                throw new DuplicatePaymentMethodIdException('Method id is duplicated');
            }
        }

        $result = $handler->requestPayment();

        if ($result->result == 'DECLINED') {
            throw new PaymentDeclinedException('Payment declined ' . $result->reason);
        }

        $payment = $this->repository->create([
            'id_order' => $order->getID(),
            'id_client' => $order->getClient()->getID(),
            'method' => $method,
            'method_id' => $result->method_id,
            'method_data' => json_encode($method_data) ?: '{}',
            'amount' => $amount,
            'link' => $result->link,
            'email' => $order->getClient()->getEmail(),
            'dt_registration' => $handler->getEffectiveDate(),
            'status' => $result->result,
        ]);

        if ($result->result == PaymentStatus::APPROVED->value) {
            if (method_exists($payment, 'setAttribute') && property_exists($payment, 'dt_executed')) {
                $payment->dt_executed = Carbon::now();
                $this->repository->save($payment);
            }
        }

        return new Payment($payment, $this->repository);
    }

    /**
     * Retrieves a payment by its ID.
     *
     * @param  int|string $id_payment    The payment identifier.
     * @return Payment                   The payment instance.
     * @throws PaymentNotFoundException  If the payment is not found.
     */
    public function fromID(int|string $id_payment): Payment
    {
        $payment = $this->repository->find($id_payment);

        if (is_null($payment)) {
            throw new PaymentNotFoundException('Payment not found ' . $id_payment);
        }

        return new Payment($payment, $this->repository);
    }

    /**
     * Retrieves a payment by its method ID.
     *
     * @param  string $method            The payment method key.
     * @param  string $method_id         The provider's payment ID.
     * @return Payment                   The payment instance.
     * @throws PaymentNotFoundException  If the payment is not found.
     */
    public function fromMethodID(string $method, string $method_id): Payment
    {
        $payment = $this->repository->findByMethodId($method, $method_id);

        if (is_null($payment)) {
            throw new PaymentNotFoundException('Payment not found');
        }

        return new Payment($payment, $this->repository);
    }

    /**
     * Instantiates the handler for a given payment method.
     *
     * @param  string                         $method       The payment method key.
     * @param  float                          $amount       The amount to process.
     * @param  PayableOrder                   $order        The order context.
     * @param  PaymentData                    $method_data  Method specific data.
     * @return PaymentHandler                               The configured handler instance.
     * @throws InvalidPaymentMethodException                If the method is not configured.
     */
    public function getHandler(string $method, float $amount, PayableOrder $order, PaymentData $method_data): PaymentHandler
    {
        $handlerClass = $this->getHandlerClass($method);

        // Convert DTO to stdClass/object for compatibility with existing handlers
        $legacyData = (object) (array) $method_data;

        if ($handlerClass === PaymentHandler::class) {
            return new class ($amount, $order, $legacyData) extends PaymentHandler {
                public function __construct(float $amount, ?PayableOrder $order = null, ?object $method_data = null)
                {
                    parent::__construct($amount, $order, $method_data);
                }
            };
        }

        // Use service container to allow dependency injection in handlers
        return app()->make($handlerClass, [
            'amount' => $amount,
            'order' => $order,
            'method_data' => $legacyData,
        ]);
    }

    /**
     * Gets the handler class name for a given method.
     *
     * @param  string $method                 The payment method key.
     * @return string                         The fully qualified class name of the handler.
     * @throws InvalidPaymentMethodException  If the method is not configured.
     */
    public function getHandlerClass(string $method): string
    {
        // Use global config helper, as this is a package
        $methods = config('stag-herd.methods', []);

        if (!is_array($methods)) {
            throw new \RuntimeException('Invalid configuration: stag-herd.methods must be an array');
        }

        if (!array_key_exists($method, $methods)) {
            throw new InvalidPaymentMethodException('Invalid payment method: ' . $method);
        }

        $config = $methods[$method];

        if (!is_array($config) || !isset($config['handler']) || !is_string($config['handler'])) {
            throw new \RuntimeException("Invalid handler configuration for method {$method}");
        }

        return $config['handler'];
    }
}
