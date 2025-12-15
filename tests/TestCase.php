<?php

/**
 * Base test case for StagHerd package tests.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests;

use Equidna\StagHerd\StagHerdServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            StagHerdServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set(
            'stag-herd.payment_model',
            \Equidna\StagHerd\Tests\Fixtures\TestPayment::class
        );

        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // $this->setUpDatabase();
    }

    protected function setUpDatabase()
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        $schema->create('payments', function ($table) {
            $table->string('id')->primary(); // Mock UUID string from fixture
            $table->string('order_id');
            $table->string('method');
            $table->string('method_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->nullable();
            $table->string('status')->default('PENDING');
            $table->string('link')->nullable();
            $table->string('result')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }
}
