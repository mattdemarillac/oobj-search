<?php
/**
 * OOBJ_SEARCH_Product_Facts over real products (fixtures/products.json: a sample across the shop's categories,
 * November 2026, descriptions trimmed to their first sentence and labelled lines).
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class ProductFactsFixtureTest extends TestCase {

	/**
	 * Facts checked by hand against each product's name, variations and description (not copied from the output).
	 */
	const EXPECTED = array(
		16325 => array(
			'material' => array( 'silicone' ),
			'gauge'    => array( '8', '10', '12', '14' ),
		),
		// "Material: Acrylic | Silicone": the silicone is the O-rings.
		16762 => array(
			'material' => array( 'acrylic' ),
			'colour'   => array( 'black' ),
			'gauge'    => array( '1.6', '2', '2.5', '3', '4', '5', '6', '8', '10' ),
		),
		17577 => array(
			'material' => array( 'bioplast' ),
			'gauge'    => array( '1.2', '1.6' ),
			'size'     => array( '8', '10' ),
		),
		17994 => array(
			'material' => array( 'silicone' ),
			'colour'   => array( 'black' ),
			'gauge'    => array( '3', '4', '5', '6', '8', '10', '12' ),
		),
		// The 8mm crystal ball and 15mm tower aren't sizes; the 10mm bar is.
		18416 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'blue', 'pink', 'white' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10' ),
		),
		18697 => array(
			'material' => array( 'gold-plated', 'surgical-steel' ),
			'colour'   => array( 'clear', 'gold' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10' ),
		),
		// "Gold Titanium Plated 316L Surgical Stainless Steel": steel; "Light Rose" is a pink.
		18732 => array(
			'material' => array( 'gold-plated', 'surgical-steel' ),
			'colour'   => array( 'aqua', 'clear', 'gold', 'pink' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10' ),
		),
		18940 => array(
			'material' => array( 'acrylic' ),
			'colour'   => array( 'black', 'pink' ),
			'gauge'    => array( '8', '10', '12', '14', '16', '18', '20', '22' ),
		),
		19079 => array(
			'material' => array( 'acrylic', 'surgical-steel' ),
			'colour'   => array( 'black', 'blue', 'clear', 'green', 'pink', 'purple', 'red' ),
			'gauge'    => array( '1.2', '1.6' ),
			'size'     => array( '8', '10' ),
		),
		19671 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'clear' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10' ),
		),
		19958 => array(
			'material' => array( 'surgical-steel' ),
			'gauge'    => array( '3', '4', '5', '6', '8', '10', '11', '12', '14', '16', '19' ),
		),
		20848 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'blue', 'green', 'purple', 'rainbow' ),
			'gauge'    => array( '1', '1.2', '1.6' ),
			'size'     => array( '8', '10', '12' ),
		),
		21249 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'clear' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6' ),
		),
		// "Titanium Anodised Colours" over 316L steel.
		21567 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'blue', 'gold', 'rainbow', 'silver' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6' ),
		),
		21965 => array(
			'material' => array( 'gold-plated', 'surgical-steel' ),
			'fitting'  => array( 'clicker' ),
			'colour'   => array( 'gold', 'rose-gold', 'silver' ),
			'gauge'    => array( '1.2', '1.6' ),
			'size'     => array( '8' ),
		),
		22385 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'pink' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '12', '16' ),
		),
		// "Certified implant grade" steel, not titanium.
		22707 => array(
			'material' => array( 'surgical-steel' ),
			'gauge'    => array( '1.2', '1.6' ),
			'size'     => array( '8', '10', '12', '14' ),
		),
		22949 => array(
			'material' => array( 'acrylic', 'surgical-steel' ),
			'colour'   => array( 'black', 'blue', 'purple' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '12', '14', '16', '18', '19', '22' ),
		),
		// Its crystals are only in the description and "Materials" (no colour said there).
		23486 => array(
			'material' => array( 'surgical-steel' ),
			'theme'    => array( 'animal' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10' ),
		),
		23894 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'ab', 'aqua', 'clear', 'gold', 'rose-gold', 'silver' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6' ),
		),
		// A dermal top sold on its own: its 3mm and 4mm are the sizes.
		24173 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'ab', 'black', 'blue', 'clear', 'green', 'pink', 'purple', 'red' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '3', '4' ),
		),
		24514 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'aqua', 'clear', 'gold', 'green', 'pink', 'purple', 'red', 'rose-gold' ),
			'theme'    => array( 'celestial' ),
			'gauge'    => array( '0.8', '1' ),
			'size'     => array( '6.5' ),
		),
		24838 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'blue', 'gold', 'green', 'purple', 'rainbow', 'rose-gold' ),
			'gauge'    => array( '0.8', '1', '1.2' ),
			'size'     => array( '8', '10' ),
		),
		25188 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'gold', 'rainbow', 'rose-gold' ),
			'gauge'    => array( '1' ),
			'size'     => array( '6' ),
		),
		25535 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'blue', 'gold', 'green', 'purple', 'rainbow' ),
			'gauge'    => array( '1.2' ),
		),
		25948 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'gold', 'rose-gold', 'silver' ),
			'gauge'    => array( '0.8' ),
			'size'     => array( '3', '4', '5', '6' ),
		),
		26290 => array(
			'material' => array( 'gold-plated', 'stone', 'surgical-steel' ),
			'colour'   => array( 'purple', 'rose-gold' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '8', '10', '11' ),
		),
		26389 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'blue', 'pink', 'purple', 'white' ),
			'theme'    => array( 'celestial' ),
			'gauge'    => array( '0.8' ),
		),
		26705 => array(
			'material' => array( 'gold-plated' ),
			'colour'   => array( 'gold', 'rose-gold', 'silver' ),
			'theme'    => array( 'heart' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '10' ),
		),
		26946 => array(
			'material' => array( 'gold-plated', 'surgical-steel' ),
			'colour'   => array( 'gold' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6' ),
		),
		16378 => array(
			'material' => array( 'acrylic', 'surgical-steel' ),
			'colour'   => array( 'aqua', 'purple' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '12', '14', '16' ),
		),
		// "Rainbow Titanium over Stainless Steel", from the first sentence.
		17458 => array(
			'material' => array( 'surgical-steel' ),
			'fitting'  => array( 'internally-threaded' ),
			'colour'   => array( 'rainbow' ),
			'gauge'    => array( '2', '3', '4', '5', '6', '8', '10', '12', '14', '16', '18', '20' ),
		),
		// "Black Titanium Plated": a finish, the material isn't said.
		17639 => array(
			'colour' => array( 'ab', 'black', 'blue', 'clear', 'pink', 'red' ),
			'gauge'  => array( '1.2' ),
			'size'   => array( '8' ),
		),
		18319 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'gold' ),
			'gauge'    => array( '8', '10', '12', '14' ),
		),
		18504 => array( 'colour' => array( 'brown' ) ),
		// The evil eye isn't spooky; its sizes are in cm.
		18832 => array(),
		18992 => array(
			'material' => array( 'acrylic' ),
			'fitting'  => array( 'internally-threaded' ),
			'colour'   => array( 'black' ),
			'theme'    => array( 'heart' ),
			'gauge'    => array( '8', '10', '12', '14' ),
		),
		19162 => array(
			'material' => array( 'bioplast' ),
			'fitting'  => array( 'threadless' ),
			'colour'   => array( 'aqua', 'black', 'clear', 'pink' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10', '12' ),
		),
		19724 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'white' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10' ),
		),
		20346 => array(
			'material' => array( 'acrylic' ),
			'theme'    => array( 'animal' ),
			'gauge'    => array( '8', '10', '12', '14', '16', '18', '20', '22' ),
		),
		// Inside and whole widths aren't the bar.
		21004 => array(
			'material' => array( 'surgical-steel' ),
			'fitting'  => array( 'clicker', 'hinged' ),
			'colour'   => array( 'black', 'gold', 'silver' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6.5' ),
		),
		21436 => array(
			'material' => array( 'surgical-steel' ),
			'fitting'  => array( 'clicker', 'hinged' ),
			'colour'   => array( 'ab', 'aqua', 'clear', 'pink' ),
			'theme'    => array( 'floral' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '7', '8' ),
		),
		21672 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'aqua', 'clear', 'pink' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '12' ),
		),
		22198 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'clear' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6' ),
		),
		22434 => array(
			'material' => array( 'acrylic' ),
			'colour'   => array( 'black', 'blue', 'purple', 'red', 'white' ),
			'gauge'    => array( '3', '4', '5' ),
		),
		// "IP Rose Gold": gold ion plating.
		22828 => array(
			'material' => array( 'gold-plated', 'surgical-steel' ),
			'colour'   => array( 'rose-gold' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6', '8' ),
		),
		22975 => array(
			'material' => array( 'gold-plated', 'surgical-steel' ),
			'colour'   => array( 'clear', 'rose-gold' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10' ),
		),
		// "Full Length 25mm" is the dangle's.
		23667 => array(
			'material' => array( 'surgical-steel' ),
			// Hematite (light grey) is grey.
			'colour'   => array( 'clear', 'gold', 'grey', 'rose-gold', 'silver' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '10' ),
		),
		24043 => array(
			'material' => array( 'surgical-steel' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '12', '14', '16' ),
		),
		// "Colourful Titanium IP" is a finish, the material isn't said.
		24211 => array(
			'fitting' => array( 'internally-threaded' ),
			'colour'  => array( 'black', 'blue', 'clear', 'gold', 'green', 'purple' ),
			'gauge'   => array( '1.6' ),
			'size'    => array( '4' ),
		),
		24762 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'blue', 'clear', 'pink' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '38' ),
		),
		24968 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'blue', 'gold', 'opal', 'silver' ),
			'theme'    => array( 'floral' ),
			'gauge'    => array( '0.8' ),
			'size'     => array( '7' ),
		),
		25333 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'clear' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6' ),
		),
		25703 => array(
			'material' => array( 'surgical-steel' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '12' ),
		),
		26182 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'blue', 'gold', 'purple', 'rainbow', 'rose-gold' ),
			'gauge'    => array( '0.8', '1' ),
			'size'     => array( '10' ),
		),
		26367 => array(
			'material' => array( 'sterling-silver' ),
			'colour'   => array( 'ab', 'clear', 'green', 'pink', 'purple', 'silver' ),
			'gauge'    => array( '0.8' ),
		),
		// A clip-on's 11mm hole isn't a length.
		26551 => array(
			'colour' => array( 'aqua', 'blue', 'pink' ),
		),
		26839 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'ab', 'aqua', 'black', 'blue', 'clear', 'green', 'pink', 'purple' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6' ),
		),
		27072 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'black', 'gold', 'rainbow', 'silver' ),
			'gauge'    => array( '1.2' ),
			'size'     => array( '6' ),
		),
		// "Rhodium Plated Alloy" shield: silver.
		27624 => array(
			'material' => array( 'surgical-steel' ),
			'colour'   => array( 'clear', 'silver' ),
			'gauge'    => array( '1.6' ),
			'size'     => array( '12' ),
		),
		27816 => array(
			'fitting' => array( 'clicker' ),
			'colour'  => array( 'clear', 'rose-gold' ),
		),
	);

	/**
	 * Share of the fixture's products with each facet, as measured in November 2026: a rule that stops working
	 * drops below.
	 */
	const COVERAGE = array(
		'material' => 0.85,
		'fitting'  => 0.25,
		'colour'   => 0.77,
		'theme'    => 0.25,
		'gauge'    => 0.87,
		'size'     => 0.76,
	);

	/**
	 * The fixture's products by ID.
	 *
	 * @var array<int, array>|null
	 */
	private static $products = null;

	/**
	 * The fixture's products by ID.
	 *
	 * @return array<int, array>
	 */
	private static function products(): array {
		if ( null === self::$products ) {
			$products       = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/products.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file, no WordPress.
			self::$products = array_column( $products, null, 'id' );
		}
		return self::$products;
	}

	/**
	 * @dataProvider expected
	 */
	public function test_expected_facts( int $id, array $facts ) {
		$product = self::products()[ $id ];
		$this->assertSame( $facts, OOBJ_SEARCH_Product_Facts::facts( $product ), $product['name'] );
	}

	public static function expected(): array {
		$cases = array();
		foreach ( self::EXPECTED as $id => $facts ) {
			$cases[ $id . ' ' . self::products()[ $id ]['name'] ] = array( $id, $facts );
		}
		return $cases;
	}

	public function test_coverage() {
		$products = self::products();
		$counts   = array_fill_keys( OOBJ_SEARCH_Vocabulary::FACT_FACETS, 0 );
		foreach ( $products as $product ) {
			foreach ( array_keys( OOBJ_SEARCH_Product_Facts::facts( $product ) ) as $facet ) {
				++$counts[ $facet ];
			}
		}
		foreach ( self::COVERAGE as $facet => $share ) {
			$this->assertGreaterThanOrEqual( $share, $counts[ $facet ] / count( $products ), $facet );
		}
	}

	public function test_facts_stay_few() {
		$total = 0;
		$most  = 0;
		foreach ( self::products() as $product ) {
			$count  = array_sum( array_map( 'count', OOBJ_SEARCH_Product_Facts::facts( $product ) ) );
			$total += $count;
			$most   = max( $most, $count );
		}
		$this->assertLessThan( 9, $total / count( self::products() ), 'average' );
		$this->assertLessThanOrEqual( 40, $most, 'most' );
	}

	public function test_every_value_is_the_vocabularys() {
		foreach ( self::products() as $product ) {
			foreach ( OOBJ_SEARCH_Product_Facts::facts( $product ) as $facet => $values ) {
				$this->assertContains( $facet, OOBJ_SEARCH_Vocabulary::FACT_FACETS );
				foreach ( $values as $value ) {
					if ( in_array( $facet, array( 'gauge', 'size' ), true ) ) {
						$this->assertSame( OOBJ_SEARCH_Vocabulary::mm( $value ), $value, $product['name'] );
					} else {
						$this->assertArrayHasKey( $value, OOBJ_SEARCH_Vocabulary::CONCEPTS[ $facet ], $product['name'] );
					}
				}
			}
		}
	}
}
