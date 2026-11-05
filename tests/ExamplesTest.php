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
		$this->assertMatchesRegularExpression( '#<div class="oobj-search-examples" hidden>.*</div></form>#s', $product );
		$this->assertStringContainsString( '>titanium nose ring under $10</a>', $product, 'Dollar amounts kept' );

		$posts = do_blocks( '<!-- wp:search {"label":"Search"} /-->' );
		$this->assertStringNotContainsString( 'oobj-search-examples', $posts );
		$this->assertSame( 'no form', $this->examples()->add( 'no form', array( 'attrs' => array( 'query' => array( 'post_type' => 'product' ) ) ) ) );
	}

	public function test_each_links_to_its_search() {
		$html = $this->examples()->html();

		$this->assertStringContainsString( '<p class="oobj-search-examples__label">Try searching for</p>', $html );
		foreach ( OOBJ_SEARCH_Examples::EXAMPLES as $example ) {
			$this->assertStringContainsString( '>' . esc_html( $example ) . '</a>', $html );
			$this->assertStringContainsString( esc_url( oobj_search( OOBJ_SEARCH_Notice::class )->search_url( $example ) ), $html );
		}
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
