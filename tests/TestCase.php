<?php

namespace Equidna\StagHerd\Tests;

use Equidna\StagHerd\StagHerdServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            StagHerdServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('stag-herd.audit_log_channel', 'stack');
        $app['config']->set('stag-herd.cleanup.enabled', false);
    }
}
