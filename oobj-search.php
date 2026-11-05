<?php
/**
 * Plugin Name:       Out Of Body Search
 * Description:       Product search that understands what shoppers mean: "titanium threadless septum 16g under $10" finds titanium, threadless, 16 gauge septum jewellery up to $10, and says what it understood.
 * Version:           0.4.2
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Update URI:        https://github.com/mattdemarillac/oobj-search
 * Author:            Out Of Body Jewellery
 * Author URI:        https://outofbody.com.au/about-us/
 * License:           Use and modification permitted, no redistribution (see LICENSE)
 * License URI:       LICENSE
 * Text Domain:       oobj-search
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'OOBJ_SEARCH_FILE', __FILE__ );
define( 'OOBJ_SEARCH_VERSION', '0.4.2' );

/*
 * Classes: OOBJ_SEARCH_Foo_Bar lives in inc/class-oobj-search-foo-bar.php (interfaces in interface-oobj-search-*.php).
 * inc/query/ is plain PHP, without WordPress: what a query means and what a product is (the vocabulary, the query
 * reader, the product facts), so its tests run without WordPress (composer test:query).
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( ! str_starts_with( $class_name, 'OOBJ_SEARCH_' ) ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', $class_name ) );
		foreach ( array( 'inc', 'inc/query' ) as $folder ) {
			foreach ( array( 'class', 'interface' ) as $kind ) {
				$file = __DIR__ . "/$folder/$kind-$slug.php";
				if ( is_readable( $file ) ) {
					require $file;
					return;
				}
			}
		}
	}
);

/**
 * The plugin, or one of its services by class name.
 *
 * @template T of object
 * @param class-string<T>|null $class_name A service's class, or null for the plugin.
 * @return ($class_name is null ? OOBJ_SEARCH_Plugin : T|null)
 */
function oobj_search( ?string $class_name = null ) {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new OOBJ_SEARCH_Plugin();
		$plugin->boot();
	}
	return null === $class_name ? $plugin : $plugin->get( $class_name );
}

add_action(
	'plugins_loaded',
	function () {
		if ( function_exists( 'WC' ) ) {
			oobj_search();
		}
	}
);

register_activation_hook( __FILE__, array( 'OOBJ_SEARCH_Plugin', 'activate' ) );
