<?php

namespace Equidna\StagHerd\Application;

use Carbon\CarbonImmutable;
use Equidna\StagHerd\Contracts\Gateways\ClosesMercadoPagoPreference;
use Equidna\StagHerd\Contracts\Gateways\ExpiresStripeCheckout;
use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Data\CheckoutClosureData;
use Equidna\StagHerd\Data\CheckoutClosureResult;
use Equidna\StagHerd\Support\CredentialContextManager;

/** Closes unpaid checkout attempts; never captures, refunds or deletes a provider payment. */
final readonly class CheckoutClosureService
{
    public function __construct(
        private CredentialContextManager $credentials,
        private StripeGateway $stripe,
        private PayPalGateway $paypal,
        private MercadoPagoGateway $mercadoPago,
    ) {}

    public function close(CheckoutClosureData $request): CheckoutClosureResult
    {
        if ($request->checkoutId === '' || $request->externalReference === ''
            || !preg_match('/^[a-zA-Z0-9_-]+$/D', $request->checkoutId)) {
            return new CheckoutClosureResult(false, 'invalid_reference');
        }

        return $this->credentials->run($request->provider, $request->credentialContext, fn (): CheckoutClosureResult => match ($request->provider) {
            'stripe' => $this->stripe($request),
            'paypal' => $this->paypal($request),
            'mercado_pago' => $this->mercadoPago($request),
            default => new CheckoutClosureResult(false, 'unsupported_provider'),
        });
    }

    private function stripe(CheckoutClosureData $request): CheckoutClosureResult
    {
        $session = $this->stripe->getCheckoutSession($request->checkoutId);
        if (($session['id'] ?? null) !== $request->checkoutId
            || ($session['client_reference_id'] ?? null) !== $request->externalReference
            || ($session['mode'] ?? null) !== 'payment') {
            return new CheckoutClosureResult(false, 'reference_mismatch');
        }
        if (($session['status'] ?? null) === 'open' && $this->stripe instanceof ExpiresStripeCheckout) {
            $session = $this->stripe->expireCheckoutSession($request->checkoutId);
        }
        // A complete async checkout may still settle; it must never be treated as canceled.
        $closed = ($session['id'] ?? null) === $request->checkoutId
            && ($session['status'] ?? null) === 'expired' && ($session['payment_status'] ?? null) === 'unpaid';

        return new CheckoutClosureResult($closed, $closed ? 'expired' : 'payment_not_closed');
    }

    private function paypal(CheckoutClosureData $request): CheckoutClosureResult
    {
        $order = $this->paypal->getOrder($request->checkoutId);
        $units = $order['purchase_units'] ?? [];
        if (($order['id'] ?? null) !== $request->checkoutId || count($units) !== 1
            || ($units[0]['reference_id'] ?? null) !== $request->externalReference
            || ($order['intent'] ?? null) !== 'CAPTURE') {
            return new CheckoutClosureResult(false, 'reference_mismatch');
        }
        if (!empty($units[0]['payments']['captures']) || !empty($units[0]['payments']['authorizations'])) {
            return new CheckoutClosureResult(false, 'payment_not_closed');
        }
        if (($order['processing_instruction'] ?? 'NO_INSTRUCTION') !== 'NO_INSTRUCTION') {
            return new CheckoutClosureResult(false, 'provider_can_capture_automatically');
        }
        if (($order['status'] ?? null) === 'VOIDED') {
            return new CheckoutClosureResult(true, 'voided');
        }
        // Orders v2 has no cancel endpoint. The merchant must fence all capture paths
        // under its own durable purchase lock before accepting this result.
        $closed = $request->consumerCaptureBlocked
            && in_array($order['status'] ?? null, ['CREATED', 'SAVED', 'APPROVED', 'PAYER_ACTION_REQUIRED'], true);

        return new CheckoutClosureResult($closed, $closed ? 'capture_blocked_by_consumer' : 'payment_not_closed', $closed);
    }

    private function mercadoPago(CheckoutClosureData $request): CheckoutClosureResult
    {
        if (!$this->mercadoPago instanceof ClosesMercadoPagoPreference) {
            return new CheckoutClosureResult(false, 'unsupported_gateway');
        }
        $preference = $this->mercadoPago->getPreference($request->checkoutId);
        if (($preference['id'] ?? null) !== $request->checkoutId
            || ($preference['external_reference'] ?? null) !== $request->externalReference) {
            return new CheckoutClosureResult(false, 'reference_mismatch');
        }
        $end = CarbonImmutable::now()->subSecond();
        $this->mercadoPago->updatePreference($request->checkoutId, [
            'expires' => true,
            'expiration_date_from' => $end->subYear()->toIso8601String(),
            'expiration_date_to' => $end->toIso8601String(),
        ]);
        $verified = $this->mercadoPago->getPreference($request->checkoutId);
        if (($verified['expires'] ?? false) !== true || empty($verified['expiration_date_to'])
            || !CarbonImmutable::parse($verified['expiration_date_to'])->isPast()) {
            return new CheckoutClosureResult(false, 'expiration_not_verified');
        }
        // Expiring a preference prevents new checkouts, but does NOT cancel existing
        // payments (including offline/processing payments). Fail closed on any nonterminal
        // payment; do not silently refund an approved payment or claim a search is complete.
        $offset = 0;
        do {
            $page = $this->mercadoPago->searchPayments(['external_reference' => $request->externalReference, 'offset' => $offset, 'limit' => 100]);
            if (!isset($page['results'], $page['paging']['total']) || !is_array($page['results'])) {
                return new CheckoutClosureResult(false, 'payment_search_incomplete');
            }
            foreach ($page['results'] as $payment) {
                if (($payment['external_reference'] ?? null) !== $request->externalReference || empty($payment['id'])) {
                    return new CheckoutClosureResult(false, 'reference_mismatch');
                }
                // Read current state before cancellation; never refund a payment that won the race.
                $payment = $this->mercadoPago->getPayment((string) $payment['id']);
                if (($payment['external_reference'] ?? null) !== $request->externalReference) {
                    return new CheckoutClosureResult(false, 'reference_mismatch');
                }
                if (in_array($payment['status'] ?? null, ['pending', 'in_process', 'authorized'], true)) {
                    $payment = $this->mercadoPago->cancelPayment((string) $payment['id']);
                }
                $knownSettled = ($payment['status'] ?? null) === 'approved'
                    && $request->settledPaymentId !== null && (string) $payment['id'] === $request->settledPaymentId;
                if (!$knownSettled && !in_array($payment['status'] ?? null, ['cancelled', 'rejected', 'refunded', 'charged_back'], true)) {
                    return new CheckoutClosureResult(false, 'payment_not_closed');
                }
            }
            $offset += count($page['results']);
            if ($offset < $page['paging']['total'] && $page['results'] === []) {
                return new CheckoutClosureResult(false, 'payment_search_incomplete');
            }
        } while ($offset < $page['paging']['total']);

        return new CheckoutClosureResult(true, 'expired');
    }
}
