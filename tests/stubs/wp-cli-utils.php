<?php
/**
 * WP-CLI's WP_CLI\Utils functions the theme uses, for the stand-in (tests/stubs/wp-cli.php).
 *
 * @package oob
 */

declare( strict_types=1 );

namespace WP_CLI\Utils;

/**
 * A flag's value, as WP-CLI's.
 *
 * @param array  $assoc_args The options.
 * @param string $flag       The flag.
 * @param mixed  $fallback   Without it.
 * @return mixed
 */
function get_flag_value( $assoc_args, $flag, $fallback = null ) {
	return $assoc_args[ $flag ] ?? $fallback;
}
