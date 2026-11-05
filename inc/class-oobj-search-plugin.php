<?php
/**
 * Class OOBJ_SEARCH_Plugin.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * The composition root: creates every service once, wires them together and registers the modules.
 */
class OOBJ_SEARCH_Plugin {

	/**
	 * Services by class name.
	 *
	 * @var array<string, object>
	 */
	private $services = array();

	public function boot(): void {
		$parser      = $this->add( new OOBJ_SEARCH_Query_Parser() );
		$facts       = $this->add( new OOBJ_SEARCH_Facts() );
		$search      = $this->add( new OOBJ_SEARCH_Search( $parser ) );
		$catalogue   = $this->add( new OOBJ_SEARCH_Catalogue() );
		$suggestions = $this->add( new OOBJ_SEARCH_Suggestions( $parser, $catalogue ) );
		$notice      = $this->add( new OOBJ_SEARCH_Notice( $search, $suggestions ) );
		$this->add( new OOBJ_SEARCH_Examples( $notice, $catalogue ) );
		$this->add(
			new OOBJ_SEARCH_Updates(
				new OOBJ_SEARCH_GitHub_Releases( OOBJ_SEARCH_GitHub_Releases::repo_from_url( self::header( 'Update URI' ) ), dirname( plugin_basename( OOBJ_SEARCH_FILE ) ), OOBJ_SEARCH_Updates::token() ),
				plugin_basename( OOBJ_SEARCH_FILE ),
				wp_get_environment_type()
			)
		);
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'oobj-search', new OOBJ_SEARCH_Command( $parser, $search, $facts ) );
		}
	}

	/**
	 * A header of the plugin's file, e.g. "Update URI".
	 *
	 * @param string $name The header.
	 */
	public static function header( string $name ): string {
		return (string) get_file_data( OOBJ_SEARCH_FILE, array( 'value' => $name ) )['value'];
	}

	/**
	 * Activation: work out every product's facts, in the background.
	 */
	public static function activate(): void {
		// WordPress doesn't activate it without WooCommerce (Requires Plugins); the tests load WooCommerce.
		// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit needs the marker exactly.
		// @codeCoverageIgnoreStart
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		// @codeCoverageIgnoreEnd
		// phpcs:enable Squiz.Commenting.InlineComment.InvalidEndChar
		delete_option( OOBJ_SEARCH_Facts::VERSION_OPTION );
	}

	/**
	 * Keep a service, registering its hooks if it's a module.
	 *
	 * @param object $service The service.
	 * @return object The same service.
	 */
	private function add( object $service ): object {
		if ( $service instanceof OOBJ_SEARCH_Module ) {
			$service->register();
		}
		$this->services[ get_class( $service ) ] = $service;
		return $service;
	}

	/**
	 * A service by class name.
	 *
	 * @param string $class_name The class.
	 */
	public function get( string $class_name ): ?object {
		return $this->services[ $class_name ] ?? null;
	}
}
