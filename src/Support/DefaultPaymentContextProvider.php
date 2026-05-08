<?php

/**
 * Default implementation for resolving payment executor context.
 *
 * Uses the current HTTP request when available and returns nulls in console contexts.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Support
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Support;

use Equidna\StagHerd\Contracts\PaymentContextProvider;
use Illuminate\Support\Facades\App;
use Throwable;

/**
 * Resolves executor context from the current runtime.
 */
class DefaultPaymentContextProvider implements PaymentContextProvider
{
    /**
     * {@inheritDoc}
     */
    public function getExecutorUri(): ?string
    {
        if (App::runningInConsole()) {
            return null;
        }

        try {
            return request()->fullUrl();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getExecutorType(): ?string
    {
        if (App::runningInConsole()) {
            return 'console';
        }

        return 'http';
    }
}
