<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Sets up the Composer autoloader and loads lightweight WordPress/Polylang
 * function stubs so the unit tests can exercise the plugin logic without
 * a full WordPress installation.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/tests/WpStubs.php';
