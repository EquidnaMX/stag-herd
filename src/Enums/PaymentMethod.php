<?php

/**
 * Payment Methods Enum.
 *
 * Defines supported payment providers.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Enums
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Enums;

enum PaymentMethod: string
{
    case PAYPAL = 'PAYPAL';
    case STRIPE = 'STRIPE';
    case MERCADOPAGO = 'MERCADOPAGO';
    case OPENPAY = 'OPENPAY';
    case CONEKTA = 'CONEKTA';
    case KUESKIPAY = 'KUESKIPAY';
    case CLIP = 'CLIP';
    case GOOGLEPAY = 'GOOGLEPAY';
}
