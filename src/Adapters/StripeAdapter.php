<?php

/**
 * Adapter for Stripe payment API integration.
 *
 * Handles charge and refund operations for Stripe payments.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Equidna\StagHerd\Contracts\StripeGateway;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Adapter for Stripe payment API integration.
 *
 * Handles charge and refund operations for Stripe.
 */
class StripeAdapter implements StripeGateway
{
    /**
     * StripeClient instance for API operations.
     *
     * @var StripeClient
     */
    private StripeClient $stripe;

    /**
     * Creates a new StripeAdapter instance using the latest Stripe PHP SDK.
     *
     * @throws Exception When configuration is missing or invalid.
     */
    public function __construct()
    {
        $apiSecret = config('stag-herd.stripe.api_secret');
        if (!$apiSecret) {
            throw new RuntimeException('Stripe secret key is not configured.');
        }
        $this->stripe = new StripeClient($apiSecret);
    }

    /**
     * Returns charge or payment intent details as an object.
     *
     * @param string $payment_id Payment identifier.
     *
     * @throws Exception When the API call fails.
     *
     * @return object Stripe details (SDK object).
     */
    public function getPaymentDetails(string $payment_id): object
    {
        try {
            if (str_starts_with($payment_id, 'pi_')) {
                return $this->stripe->paymentIntents->retrieve($payment_id, []);
            }

            return $this->stripe->charges->retrieve($payment_id, []);
        } catch (Exception $e) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Stripe get payment details failed', [
                'payment_id' => $payment_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Returns refund details for a payment as an object.
     *
     * @param string $payment_id Payment identifier.
     *
     * @throws Exception When the API call fails.
     *
     * @return object Refund details (SDK object).
     */
    public function getRefund(string $payment_id): object
    {
        try {
            if (str_starts_with($payment_id, 'pi_')) {
                return $this->stripe->refunds->create([
                    'payment_intent' => $payment_id,
                ]);
            }

            return $this->stripe->refunds->create([
                'charge' => $payment_id,
            ]);
        } catch (Exception $e) {
            Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error('Stripe refund failed', [
                'payment_id' => $payment_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
