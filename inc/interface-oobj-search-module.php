<?php
/**
 * Interface OOBJ_SEARCH_Module.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * A part of the plugin that hooks into WordPress.
 */
interface OOBJ_SEARCH_Module {

	/**
	 * Add the module's hooks.
	 */
	public function register(): void;
}
