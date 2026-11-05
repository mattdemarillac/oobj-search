<?php
/**
 * Class OOBJ_SEARCH_Examples.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * The search panel, inside every product search form (the Search block set to products), hidden, for the theme to
 * show while the form is in use. It shows what the search understands before anyone types:
 * - example searches (EXAMPLES, and each season's from OOBJ_SEARCH_Vocabulary::SEASONS, marked with their dates so a
 *   cached page still shows them in season), which between them use everything the search understands;
 * - "Search by": the materials, fittings, gauges (with their mm), sizes in mm, colours and themes the shop has (OOBJ_SEARCH_Catalogue: at least
 *   MIN_PRODUCTS products, most searched first), and price limits, as buttons that add their words to the search, so
 *   shoppers build "titanium threadless 16g" themselves and learn what the search knows.
 *
 * The examples were chosen from what shoppers search, each checked against the catalogue (November 2026: 28 to 98
 * products each; `wp oobj-search examples` counts them again).
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
	 * The "Search by" groups: facet => heading. Price is added after them.
	 */
	const GROUPS = array(
		'material' => 'Material',
		'fitting'  => 'Fitting',
		'gauge'    => 'Gauge',
		'size'     => 'Size',
		'colour'   => 'Colour',
		'theme'    => 'Theme',
	);

	/**
	 * A value the shop has fewer products for isn't offered.
	 */
	const MIN_PRODUCTS = 10;

	/**
	 * At most this many values a group.
	 */
	const PER_GROUP = 10;

	/**
	 * Price buttons: words added => label.
	 */
	const PRICES = array(
		'under $5'  => 'Under $5',
		'under $10' => 'Under $10',
		'under $20' => 'Under $20',
		'cheapest'  => 'Cheapest first',
	);

	/**
	 * Builds the links.
	 *
	 * @var OOBJ_SEARCH_Notice
	 */
	private $notice;

	/**
	 * What the shop has.
	 *
	 * @var OOBJ_SEARCH_Catalogue
	 */
	private $catalogue;

	public function __construct( OOBJ_SEARCH_Notice $notice, OOBJ_SEARCH_Catalogue $catalogue ) {
		$this->notice    = $notice;
		$this->catalogue = $catalogue;
	}

	public function register(): void {
		add_filter( 'render_block_core/search', array( $this, 'add' ), 10, 2 );
	}

	/**
	 * Adds the panel inside a product search form.
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
	 * The panel, hidden until the theme shows it.
	 */
	public function html(): string {
		return sprintf(
			'<div class="oobj-search-panel" hidden><div class="oobj-search-panel__section"><p class="oobj-search-panel__heading">%1$s</p><ul class="oobj-search-panel__examples">%2$s</ul></div><div class="oobj-search-panel__section"><p class="oobj-search-panel__heading">%3$s</p>%4$s</div></div>',
			esc_html__( 'Try', 'oobj-search' ),
			$this->examples(),
			esc_html__( 'Search by', 'oobj-search' ),
			$this->groups()
		);
	}

	/**
	 * The example searches: each season's first, marked with its dates, then EXAMPLES.
	 */
	private function examples(): string {
		$items = '';
		foreach ( OOBJ_SEARCH_Vocabulary::SEASONS as $season ) {
			foreach ( $season['examples'] as $example ) {
				$items .= $this->example( $example, sprintf( ' data-from="%s" data-to="%s"', esc_attr( $season['from'] ), esc_attr( $season['to'] ) ) );
			}
		}
		foreach ( self::EXAMPLES as $example ) {
			$items .= $this->example( $example, '' );
		}
		return $items;
	}

	/**
	 * One example search.
	 *
	 * @param string $example    The search.
	 * @param string $attributes More attributes for its item, escaped.
	 */
	private function example( string $example, string $attributes ): string {
		return sprintf( '<li%1$s><a class="oobj-search-panel__example" href="%2$s">%3$s</a></li>', $attributes, esc_url( $this->notice->search_url( $example ) ), esc_html( $example ) );
	}

	/**
	 * The "Search by" groups, then prices.
	 */
	private function groups(): string {
		$counts = $this->catalogue->counts()['facts'];
		$html   = '';
		foreach ( self::GROUPS as $facet => $heading ) {
			$chips = '';
			foreach ( $this->values( $facet, $counts[ $facet ] ?? array() ) as $value ) {
				$chips .= $this->chip( OOBJ_SEARCH_Vocabulary::phrase( $facet, $value ), self::chip_label( $facet, $value ), self::chip_detail( $facet, $value ), $this->season_attributes( $facet, $value ) );
			}
			$html .= '' === $chips ? '' : self::group( $facet, $heading, $chips );
		}
		$chips = '';
		foreach ( self::PRICES as $words => $label ) {
			$chips .= $this->chip( $words, $label, '', '' );
		}
		return $html . self::group( 'price', 'Price', $chips );
	}

	/**
	 * The values of a facet to offer: the shop has MIN_PRODUCTS of them; gauges thinnest first, the rest most searched
	 * first, then most products; at most PER_GROUP.
	 *
	 * @param string             $facet  A facet.
	 * @param array<string, int> $counts Its values' products.
	 * @return string[]
	 */
	private function values( string $facet, array $counts ): array {
		$values = array_map( 'strval', array_keys( array_filter( $counts, fn( $count ) => $count >= self::MIN_PRODUCTS ) ) );
		if ( 'gauge' === $facet ) {
			// Only gauges with a number: a stretching size in mm ("12mm") reads back as a length.
			$values = array_values( array_intersect( $values, OOBJ_SEARCH_Vocabulary::GAUGES ) );
		}
		if ( 'gauge' === $facet || 'size' === $facet ) {
			// The ones the shop has most of, smallest first.
			return self::smallest_first( $values, $counts );
		}
		$demand = OOBJ_SEARCH_Vocabulary::DEMAND[ $facet ] ?? array();
		usort( $values, fn( $a, $b ) => array( $demand[ $b ] ?? 0, $counts[ $b ] ) <=> array( $demand[ $a ] ?? 0, $counts[ $a ] ) );
		return array_slice( $values, 0, self::PER_GROUP );
	}

	/**
	 * The PER_GROUP values with the most products, smallest first (gauges, sizes).
	 *
	 * @param string[]           $values Values in mm.
	 * @param array<string, int> $counts Their products.
	 * @return string[]
	 */
	public static function smallest_first( array $values, array $counts ): array {
		usort( $values, fn( $a, $b ) => $counts[ $b ] <=> $counts[ $a ] );
		$values = array_slice( $values, 0, self::PER_GROUP );
		usort( $values, fn( $a, $b ) => (float) $a <=> (float) $b );
		return $values;
	}

	/**
	 * A gauge's thickness to show on its button ("1.2mm"), or ''.
	 *
	 * @param string $facet A facet.
	 * @param string $value Its value.
	 */
	public static function chip_detail( string $facet, string $value ): string {
		return 'gauge' === $facet ? $value . 'mm' : '';
	}

	/**
	 * A value's button label, short: its label without an explanation or a second name ("Threadless", not "Threadless
	 * (push pin)"; "Bioplast", not "Bioplast and bioflex"), a gauge's number ("16g"), a size in mm ("8mm").
	 *
	 * @param string $facet A facet.
	 * @param string $value Its value.
	 */
	public static function chip_label( string $facet, string $value ): string {
		if ( 'gauge' === $facet || 'size' === $facet ) {
			return OOBJ_SEARCH_Vocabulary::phrase( $facet, $value );
		}
		$label = (string) preg_replace( '/\s*\(.*\)$/', '', OOBJ_SEARCH_Vocabulary::label( $facet, $value ) );
		return trim( explode( ' and ', $label )[0] );
	}

	/**
	 * A seasonal value's dates, as attributes, or ''.
	 *
	 * @param string $facet A facet.
	 * @param string $value Its value.
	 */
	private function season_attributes( string $facet, string $value ): string {
		foreach ( OOBJ_SEARCH_Vocabulary::SEASONS as $season ) {
			if ( $season['facet'] === $facet && $season['value'] === $value ) {
				return sprintf( ' data-from="%s" data-to="%s"', esc_attr( $season['from'] ), esc_attr( $season['to'] ) );
			}
		}
		return '';
	}

	/**
	 * A group of buttons.
	 *
	 * @param string $name    Its name (a facet, or price).
	 * @param string $heading Its heading.
	 * @param string $chips   Its buttons.
	 */
	public static function group( string $name, string $heading, string $chips ): string {
		return sprintf(
			'<div class="oobj-search-panel__group oobj-search-panel__group--%1$s" role="group" aria-label="%2$s"><p class="oobj-search-panel__label" aria-hidden="true">%3$s</p><ul class="oobj-search-panel__chips">%4$s</ul></div>',
			esc_attr( $name ),
			/* translators: %s: a kind of thing to search by, e.g. "Material". */
			esc_attr( sprintf( __( 'Search by %s', 'oobj-search' ), strtolower( $heading ) ) ),
			esc_html( $heading ),
			$chips
		);
	}

	/**
	 * A button that adds words to the search.
	 *
	 * @param string $words      The words it adds.
	 * @param string $label      Its label.
	 * @param string $detail     A second, quieter part of its label ("1.2mm"), or ''.
	 * @param string $attributes More attributes for its item, escaped.
	 */
	private function chip( string $words, string $label, string $detail, string $attributes ): string {
		return sprintf( '<li%1$s><button type="button" class="oobj-search-panel__chip" data-add="%2$s" aria-pressed="false">%3$s</button></li>', $attributes, esc_attr( $words ), self::chip_text( $label, $detail ) );
	}

	/**
	 * A button's label, with its detail ("16g <span>1.2mm</span>"), escaped.
	 *
	 * @param string $label  The label.
	 * @param string $detail Its detail, or ''.
	 */
	public static function chip_text( string $label, string $detail ): string {
		return esc_html( $label ) . ( '' === $detail ? '' : ' <span class="oobj-search-panel__detail">' . esc_html( $detail ) . '</span>' );
	}
}
