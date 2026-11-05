<?php
/**
 * Class OOBJ_SEARCH_Examples.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Example searches in the shop's product search form, which show what the search understands before anyone types:
 * between them a material, a gauge, a fitting, colours ("black or gold"), a theme, cheapest first, a stretching size,
 * a price limit and a price range, each with a placement or type. Hidden markup inside every product search form
 * (the Search block set to products); the theme shows it while the form has focus and picks which to show.
 *
 * Chosen from what shoppers search, each checked against the catalogue (November 2026: every one finds 28 to 98
 * products; `wp oobj-search examples` counts them again).
 */
class OOBJ_SEARCH_Examples implements OOBJ_SEARCH_Module {

	const EXAMPLES = array(
		'titanium nose ring under $10',
		'16g septum clicker',
		'threadless rose gold earrings',
		'cheap black tongue bars',
		'silicone tunnels 10mm',
		'heart nipple bars',
		'black or gold septum clicker',
		'celestial earrings $5-$15',
	);

	/**
	 * Builds the links.
	 *
	 * @var OOBJ_SEARCH_Notice
	 */
	private $notice;

	public function __construct( OOBJ_SEARCH_Notice $notice ) {
		$this->notice = $notice;
	}

	public function register(): void {
		add_filter( 'render_block_core/search', array( $this, 'add' ), 10, 2 );
	}

	/**
	 * Adds the examples inside a product search form.
	 *
	 * @param string               $html  The Search block.
	 * @param array<string, mixed> $block The block, with its attributes.
	 */
	public function add( $html, $block ): string {
		$html = (string) $html;
		if ( 'product' !== ( $block['attrs']['query']['post_type'] ?? '' ) || ! str_contains( $html, '</form>' ) ) {
			return $html;
		}
		// By position, not preg_replace(): "$10" in an example would read as a reference to a match.
		return substr_replace( $html, $this->html(), (int) strrpos( $html, '</form>' ), 0 );
	}

	/**
	 * The examples, hidden until the theme shows them.
	 */
	public function html(): string {
		$items = '';
		foreach ( self::EXAMPLES as $example ) {
			$items .= sprintf( '<li><a class="oobj-search-examples__example" href="%1$s">%2$s</a></li>', esc_url( $this->notice->search_url( $example ) ), esc_html( $example ) );
		}
		return sprintf(
			'<div class="oobj-search-examples" hidden><p class="oobj-search-examples__label">%1$s</p><ul class="oobj-search-examples__list">%2$s</ul></div>',
			esc_html__( 'Try searching for', 'oobj-search' ),
			$items
		);
	}
}
