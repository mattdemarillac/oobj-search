<?php
/**
 * OOBJ_SEARCH_Examples: example searches inside the shop's product search form that, between them, show what the
 * search understands.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class ExamplesTest extends OOBJ_SEARCH_TestCase {

	private function examples(): OOBJ_SEARCH_Examples {
		return oobj_search( OOBJ_SEARCH_Examples::class );
	}

	public function test_they_go_inside_product_search_forms_only() {
		$this->assertSame( 10, has_filter( 'render_block_core/search', array( $this->examples(), 'add' ) ) );

		$product = do_blocks( '<!-- wp:search {"label":"Search","query":{"post_type":"product"}} /-->' );
		$this->assertMatchesRegularExpression( '#<div class="oobj-search-panel" hidden>.*</div></form>#s', $product );
		$this->assertStringContainsString( '>titanium nose ring under $10</a>', $product, 'Dollar amounts kept' );

		$posts = do_blocks( '<!-- wp:search {"label":"Search"} /-->' );
		$this->assertStringNotContainsString( 'oobj-search-panel', $posts );
		$this->assertSame( 'no form', $this->examples()->add( 'no form', array( 'attrs' => array( 'query' => array( 'post_type' => 'product' ) ) ) ) );
	}

	public function test_each_links_to_its_search_seasons_first_with_their_dates() {
		$html = $this->examples()->html();

		$this->assertStringContainsString( '<p class="oobj-search-panel__heading">Try</p>', $html );
		foreach ( OOBJ_SEARCH_Examples::EXAMPLES as $example ) {
			$this->assertStringContainsString( '>' . esc_html( $example ) . '</a>', $html );
			$this->assertStringContainsString( esc_url( oobj_search( OOBJ_SEARCH_Notice::class )->search_url( $example ) ), $html );
		}
		$this->assertMatchesRegularExpression( '#<ul class="oobj-search-panel__examples"><li data-from="09-15" data-to="10-31"><a [^>]*>spooky earrings under \$10</a></li><li data-from="11-01" data-to="12-25">#', $html );
	}

	public function test_search_by_offers_what_the_shop_has_most_searched_first() {
		delete_transient( OOBJ_SEARCH_Catalogue::CACHE_KEY );
		for ( $i = 0; $i < OOBJ_SEARCH_Examples::MIN_PRODUCTS; $i++ ) {
			$this->product( "Titanium Threadless Opal Rose Gold Skull 16G Ring $i", 9 );
			$this->product( "Acrylic 14G Plug $i", 9, array( 'ear-cartilage/tunnels-plugs-tapers' ) );
		}
		$this->product( 'Wood 18G Spiral', 9 );

		$html = $this->examples()->html();

		$this->assertMatchesRegularExpression( '#group--material" role="group" aria-label="Search by material"><p class="oobj-search-panel__label" aria-hidden="true">Material</p><ul class="oobj-search-panel__chips"><li><button type="button" class="oobj-search-panel__chip" data-add="titanium" aria-pressed="false">Titanium</button></li><li>[^<]*<button[^>]*data-add="acrylic"#', $html, 'Most searched first' );
		$this->assertStringNotContainsString( 'data-add="wood"', $html, 'Too few products' );
		$this->assertStringContainsString( 'data-add="threadless" aria-pressed="false">Threadless</button>', $html );
		$this->assertMatchesRegularExpression( '#data-add="16g"[^>]*>16g</button></li><li><button[^>]*data-add="14g"#', $html, 'Gauges thinnest first' );
		$this->assertStringNotContainsString( 'data-add="8mm"', $html, 'A stretching size in mm is no gauge button' );
		$this->assertStringContainsString( '<li data-from="09-15" data-to="10-31"><button type="button" class="oobj-search-panel__chip" data-add="spooky"', $html, 'Seasonal: dated' );
		$this->assertStringContainsString( 'data-add="under $10" aria-pressed="false">Under $10</button>', $html );
		$this->assertStringContainsString( 'data-add="cheapest" aria-pressed="false">Cheapest first</button>', $html );
	}

	public function test_button_labels_are_short() {
		$this->assertSame( 'Threadless', OOBJ_SEARCH_Examples::chip_label( 'fitting', 'threadless' ) );
		$this->assertSame( 'Bioplast', OOBJ_SEARCH_Examples::chip_label( 'material', 'bioplast' ) );
		$this->assertSame( 'AB', OOBJ_SEARCH_Examples::chip_label( 'colour', 'ab' ) );
		$this->assertSame( '16g', OOBJ_SEARCH_Examples::chip_label( 'gauge', '1.2' ) );
	}

	public function test_with_nothing_indexed_only_prices_are_offered() {
		delete_transient( OOBJ_SEARCH_Catalogue::CACHE_KEY );

		$html = $this->examples()->html();

		$this->assertStringNotContainsString( 'group--material', $html );
		$this->assertStringContainsString( 'group--price', $html );
	}

	public function test_between_them_they_show_everything_the_search_understands() {
		$parser = new OOBJ_SEARCH_Query_Parser();
		$kinds  = array();
		foreach ( OOBJ_SEARCH_Examples::EXAMPLES as $example ) {
			$query = $parser->parse( $example );
			$this->assertGreaterThanOrEqual( 2, count( $query->parts ), "$example combines things" );
			$this->assertSame( '', $query->keywords, "$example is understood whole" );
			$kinds = array_merge( $kinds, array_column( $query->parts, 'kind' ) );
			if ( null !== $query->min_price && null !== $query->max_price ) {
				$kinds[] = 'price range';
			}
			if ( count( $query->facets['colour'] ?? array() ) > 1 ) {
				$kinds[] = 'alternatives';
			}
		}
		foreach ( array( 'category', 'material', 'fitting', 'colour', 'theme', 'gauge', 'price', 'sort', 'price range', 'alternatives' ) as $kind ) {
			$this->assertContains( $kind, $kinds );
		}
	}
}
