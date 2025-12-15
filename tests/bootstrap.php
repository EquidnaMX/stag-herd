<?php

/**
 * PHPUnit bootstrap file for StagHerd package tests.
 *
 * Manually loads test infrastructure when running package tests
 * from main application context.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

// Load main app autoloader (relative to this file in tests/)
$autoloaderPath = __DIR__ . '/../../../../vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    // Fallback for different execution contexts
    $autoloaderPath = dirname(__DIR__, 4) . '/vendor/autoload.php';
}
require_once $autoloaderPath;

// Manually require test infrastructure
$testFiles = [
    __DIR__ . '/TestCase.php',
    __DIR__ . '/Fixtures/TestPayment.php',
    __DIR__ . '/Fixtures/TestOrder.php',
    __DIR__ . '/Fixtures/TestClient.php',
];

foreach ($testFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}
