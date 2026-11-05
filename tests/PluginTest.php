<?php
/**
 * OOBJ_SEARCH_Plugin: the services, activation, and uninstalling.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class PluginTest extends OOBJ_SEARCH_TestCase {

	public function test_it_boots_once_with_its_services() {
		$this->assertSame( oobj_search(), oobj_search() );
		foreach ( array( OOBJ_SEARCH_Query_Parser::class, OOBJ_SEARCH_Facts::class, OOBJ_SEARCH_Search::class, OOBJ_SEARCH_Notice::class, OOBJ_SEARCH_Updates::class ) as $class ) {
			$this->assertInstanceOf( $class, oobj_search( $class ) );
		}
		$this->assertNull( oobj_search( 'Nothing' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_under_wp_cli_it_adds_its_command() {
		require_once __DIR__ . '/stubs/wp-cli.php';

		( new OOBJ_SEARCH_Plugin() )->boot();

		$this->assertInstanceOf( OOBJ_SEARCH_Command::class, WP_CLI::$commands['oobj-search'] );
	}

	public function test_the_header() {
		$this->assertSame( 'https://github.com/mattdemarillac/oobj-search', OOBJ_SEARCH_Plugin::header( 'Update URI' ) );
		$this->assertSame( OOBJ_SEARCH_VERSION, OOBJ_SEARCH_Plugin::header( 'Version' ) );
	}

	public function test_activating_works_out_every_products_facts_again() {
		update_option( OOBJ_SEARCH_Facts::VERSION_OPTION, OOBJ_SEARCH_Facts::version() );

		OOBJ_SEARCH_Plugin::activate();

		$this->assertFalse( get_option( OOBJ_SEARCH_Facts::VERSION_OPTION ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstalling_deletes_the_facts() {
		$product = $this->product( 'Titanium Ring', 6 );
		$this->assertNotEmpty( wp_get_object_terms( $product->get_id(), OOBJ_SEARCH_Facts::TAXONOMY ) );

		define( 'WP_UNINSTALL_PLUGIN', 'oobj-search/oobj-search.php' );
		include dirname( __DIR__ ) . '/uninstall.php';
		clean_object_term_cache( $product->get_id(), 'product' );
		wp_cache_flush();

		global $wpdb;
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", OOBJ_SEARCH_Facts::TAXONOMY ) ) );
		$this->assertFalse( get_option( OOBJ_SEARCH_Facts::VERSION_OPTION ) );
	}
}
