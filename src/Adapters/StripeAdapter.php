<?php

/**
 * Adapter for Stripe payment API integration.
 *
 * Handles charge and refund operations for Stripe payments.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

/**
 * Adapter for Stripe payment API integration.
 *
 * Handles charge and refund operations for Stripe.
 */
class StripeAdapter
{
    /**
     * StripeClient instance for API operations.
     *
     * @var \Stripe\StripeClient
     */
    private \Stripe\StripeClient $stripe;

    /**
     * Creates a new StripeAdapter instance using the latest Stripe PHP SDK.
     *
     * @throws \Exception When configuration is missing or invalid.
     */
    public function __construct()
    {
        $apiSecret = config('stag-herd.stripe.secret'); // Changed to package config
        if (!is_string($apiSecret) || $apiSecret === '') {
            $envSecret = env('STRIPE_SECRET');
            $apiSecret = is_string($envSecret) ? $envSecret : '';
        }
        if (!$apiSecret) {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }
        $this->stripe = new \Stripe\StripeClient($apiSecret);
    }

    /**
     * Returns charge details by payment ID as an object.
     *
     * @param  string $payment_id  Payment identifier.
     * @return object              Charge details (SDK object).
     * @throws \Exception         When the API call fails.
     */
    public function getChargeDetails(string $payment_id): object
    {
        return $this->stripe->charges->retrieve($payment_id, []);
    }

    /**
     * Returns refund details for a payment as an object.
     *
     * @param  string $payment_id  Payment identifier.
     * @return object              Refund details (SDK object).
     * @throws \Exception         When the API call fails.
     */
    public function getRefund(string $payment_id): object
    {
        return $this->stripe->refunds->create([
            'charge' => $payment_id,
        ]);
    }
}
