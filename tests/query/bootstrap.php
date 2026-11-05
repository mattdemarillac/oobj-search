<?php
/**
 * The plain-PHP tests' bootstrap: inc/query/ without WordPress, and nothing else, so a WordPress function used there
 * fails these tests.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

spl_autoload_register(
	function ( $class_name ) {
		$file = dirname( __DIR__, 2 ) . '/inc/query/class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
