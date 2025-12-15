<?php

/**
 * Payment audit logging utilities.
 *
 * Provides structured logging for payment lifecycle events and state transitions.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Support;

use Illuminate\Support\Facades\Log;

class PaymentAuditor
{
    /**
     * Logs a payment state transition.
     *
     * @param  string      $paymentMethodId  Unique payment identifier.
     * @param  string      $fromStatus       Previous status.
     * @param  string      $toStatus         New status.
     * @param  string      $reason           Transition reason or event type.
     * @param  array<string,mixed> $context  Additional context data.
     * @return void
     */
    public static function logTransition(
        string $paymentMethodId,
        string $fromStatus,
        string $toStatus,
        string $reason,
        array $context = []
    ): void {
        Log::channel(config('stag-herd.audit_log_channel', 'stack'))->info(
            'Payment state transition',
            [
                'payment_method_id' => $paymentMethodId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'timestamp' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                ...$context,
            ]
        );
    }

    /**
     * Logs a webhook verification attempt.
     *
     * @param  string $provider  Payment provider name.
     * @param  bool   $success   Whether verification succeeded.
     * @param  string $reason    Failure reason if unsuccessful.
     * @param  array<string,mixed> $context   Additional context.
     * @return void
     */
    public static function logWebhookVerification(
        string $provider,
        bool $success,
        string $reason = '',
        array $context = []
    ): void {
        $level = $success ? 'info' : 'warning';

        Log::channel(config('stag-herd.audit_log_channel', 'stack'))->{$level}(
            'Webhook verification attempt',
            [
                'provider' => $provider,
                'success' => $success,
                'reason' => $reason,
                'timestamp' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
                'headers' => request()->headers->all(),
                ...$context,
            ]
        );
    }

    /**
     * Logs a payment request initiation.
     *
     * @param  string $paymentMethodId  Payment identifier.
     * @param  string $provider         Payment provider.
     * @param  float  $amount           Payment amount.
     * @param  array<string,mixed> $context          Additional context.
     * @return void
     */
    public static function logPaymentRequest(
        string $paymentMethodId,
        string $provider,
        float $amount,
        array $context = []
    ): void {
        Log::channel(config('stag-herd.audit_log_channel', 'stack'))->info(
            'Payment request initiated',
            [
                'payment_method_id' => $paymentMethodId,
                'provider' => $provider,
                'amount' => $amount,
                'timestamp' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
                ...$context,
            ]
        );
    }

    /**
     * Logs a payment failure.
     *
     * @param  string      $paymentMethodId  Payment identifier.
     * @param  string      $provider         Payment provider.
     * @param  string      $errorMessage     Error description.
     * @param  array<string,mixed> $context          Additional context.
     * @return void
     */
    public static function logPaymentFailure(
        string $paymentMethodId,
        string $provider,
        string $errorMessage,
        array $context = []
    ): void {
        Log::channel(config('stag-herd.audit_log_channel', 'stack'))->error(
            'Payment failed',
            [
                'payment_method_id' => $paymentMethodId,
                'provider' => $provider,
                'error' => $errorMessage,
                'timestamp' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
                ...$context,
            ]
        );
    }

    /**
     * Logs a suspicious activity detection.
     *
     * @param  string $eventType   Type of suspicious activity.
     * @param  string $description Details of the suspicious event.
     * @param  array<string,mixed> $context     Additional context.
     * @return void
     */
    public static function logSuspiciousActivity(
        string $eventType,
        string $description,
        array $context = []
    ): void {
        Log::channel(config('stag-herd.audit_log_channel', 'stack'))->warning(
            'Suspicious payment activity detected',
            [
                'event_type' => $eventType,
                'description' => $description,
                'timestamp' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                ...$context,
            ]
        );
    }
}
