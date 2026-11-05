<?php
/**
 * A stand-in for WP-CLI, for tests that run the theme's WP-CLI code. Only for tests in their own process
 * (runInSeparateProcess): it defines WP_CLI for the rest of the process.
 *
 * @package oob
 */

declare( strict_types=1 );

define( 'WP_CLI', true );

require_once __DIR__ . '/class-wp-cli-stub-error.php';
require_once __DIR__ . '/class-wp-cli.php';
require_once __DIR__ . '/wp-cli-utils.php';
