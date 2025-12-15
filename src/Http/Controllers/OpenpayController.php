<?php

/**
 * Webhook controller for Openpay payment events.
 *
 * Handles Openpay webhook confirmations and signature verification.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class OpenpayController extends Controller
{
    /**
     * Proxy to unified WebhookController.
     */
    public function handle(Request $request): JsonResponse
    {
        // Delegate to unified controller preserving legacy route
        return app(WebhookController::class)->handle($request, 'openpay');
    }
}
