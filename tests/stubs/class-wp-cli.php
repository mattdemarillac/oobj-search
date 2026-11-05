<?php
/**
 * Class WP_CLI, the stand-in (tests/stubs/wp-cli.php).
 *
 * @package oob
 */

declare( strict_types=1 );

/**
 * Records the commands added and what is printed.
 */
class WP_CLI {

	/**
	 * Added commands, by name.
	 *
	 * @var array<string, object>
	 */
	public static $commands = array();

	/**
	 * Messages, as array{type, message}.
	 *
	 * @var array<int, array{string, string}>
	 */
	public static $messages = array();

	public static function add_command( $name, $command ) {
		self::$commands[ $name ] = $command;
	}

	public static function log( $message ) {
		self::$messages[] = array( 'log', $message );
	}

	public static function line( $message = '' ) {
		self::$messages[] = array( 'line', $message );
	}

	public static function success( $message ) {
		self::$messages[] = array( 'success', $message );
	}

	public static function warning( $message ) {
		self::$messages[] = array( 'warning', $message );
	}

	public static function error( $message ) {
		self::$messages[] = array( 'error', $message );
		throw new WP_CLI_Stub_Error( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a test's exception, never output.
	}
}
