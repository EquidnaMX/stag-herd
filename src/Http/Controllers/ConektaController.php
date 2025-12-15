<?php

/**
 * Webhook controller for Conekta payment events.
 *
 * Handles Conekta (OXXO) webhook confirmations and signature verification.
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

class ConektaController extends Controller
{
    /**
     * Proxy to unified WebhookController.
     */
    public function handle(Request $request): JsonResponse
    {
        return app(WebhookController::class)->handle($request, 'conekta');
    }
}
