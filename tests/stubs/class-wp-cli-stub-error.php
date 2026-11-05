<?php
/**
 * Class WP_CLI_Stub_Error.
 *
 * @package oob
 */

declare( strict_types=1 );

/**
 * Thrown by the stand-in WP_CLI::error(), where WP-CLI's exits.
 */
class WP_CLI_Stub_Error extends Exception {}
