<?php
/**
 * OOBJ_SEARCH_Notice: what the search understood, as links to search again without each part, and what the results
 * leave out when nothing matched all of it.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class NoticeTest extends OOBJ_SEARCH_TestCase {

	private function notice(): OOBJ_SEARCH_Notice {
		return oobj_search( OOBJ_SEARCH_Notice::class );
	}

	public function test_it_goes_in_front_of_the_results() {
		$this->assertSame( 99, has_filter( 'render_block_woocommerce/product-collection', array( $this->notice(), 'before_results' ) ) );
	}

	public function test_nothing_without_a_product_search() {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $this->notice()->html() );
		$this->assertSame( '<div>results</div>', $this->notice()->before_results( '<div>results</div>' ) );
	}

	public function test_a_plain_search_with_few_results_has_nothing_to_say() {
		$this->product( 'Spiral Twist', 6 );
		$this->search( 'spiral' );

		$this->assertSame( '', $this->notice()->html() );
	}

	public function test_a_plain_search_gets_searches_to_try() {
		foreach ( array( 'Titanium Spiral', 'Titanium Spiral Two', 'Titanium Spiral Three', 'Steel Spiral', 'Steel Spiral Two', 'Steel Spiral Three', 'Steel Spiral Four' ) as $i => $name ) {
			$this->product( $name, 4 + 4 * $i );
		}
		$this->search( 'spiral' );

		$html = $this->notice()->html();

		$this->assertStringNotContainsString( 'Showing:', $html );
		$this->assertStringContainsString( '<p class="oobj-search-notice__label oobj-search-notice__label--try">Try:</p>', $html );
		$this->assertStringContainsString( 'oobj-search-notice__suggestion--material" href="' . esc_url( $this->notice()->search_url( 'titanium spiral' ) ) . '">titanium spiral <span class="oobj-search-notice__count">3 products</span>', $html );
	}

	public function test_search_by_offers_what_narrows_the_results() {
		foreach ( array( 'Titanium Threadless 16G Spiral', 'Titanium Threadless 16G 8mm Spiral', 'Titanium Threadless 16G 8mm Spiral Two', 'Titanium 14G 6mm Spiral', 'Titanium 14G 6mm Spiral Two', 'Titanium 14G 6mm Spiral Three', 'Steel Spiral' ) as $i => $name ) {
			$this->product( $name, 4 + 4 * $i );
		}
		$this->search( 'spiral' );

		$html = $this->notice()->html();

		$this->assertStringContainsString( '<details class="oobj-search-notice__by"><summary class="oobj-search-notice__toggle"><span class="oobj-search-notice__toggle-label">Search by</span> <span class="oobj-search-notice__toggle-hint">material, fitting, gauge, price</span></summary>', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $this->notice()->search_url( 'titanium spiral' ) ) . '" aria-label="Titanium, 6 products">Titanium</a>', $html, 'A value most of them have' );
		$this->assertStringContainsString( 'aria-label="Threadless, 3 products">Threadless</a>', $html );
		$this->assertMatchesRegularExpression( '#>16g <span class="oobj-search-panel__detail">1.2mm</span></a></li><li><a [^>]*>14g <span class="oobj-search-panel__detail">1.6mm</span></a>#', $html, 'Gauges thinnest first, with mm' );
		$this->assertStringNotContainsString( 'Steel</a>', $html, 'Too few to offer' );
		$this->assertStringContainsString( 'href="' . esc_url( $this->notice()->search_url( 'spiral under $15' ) ) . '" aria-label="Under $15, 3 products">Under $15</a>', $html );
		$this->assertStringNotContainsString( '>Under $5</a>', $html, 'Too few under $5' );
		$this->assertStringContainsString( 'aria-label="Under $20, 5 products"', $html );
	}

	public function test_search_by_shows_what_the_search_has_as_on() {
		foreach ( array( 'Titanium Threadless Spiral', 'Titanium Threadless Spiral Two', 'Titanium Threadless Spiral Three', 'Titanium Spiral', 'Titanium Spiral Two', 'Titanium Spiral Three' ) as $name ) {
			$this->product( $name, 6 );
		}
		$this->search( 'titanium spiral under $10' );

		$html = $this->notice()->html();

		$this->assertStringContainsString( '<a class="oobj-search-panel__chip oobj-search-panel__chip--on" href="' . esc_url( $this->notice()->search_url( 'spiral under $10' ) ) . '" aria-label="Remove Titanium from the search">Titanium</a>', $html );
		$this->assertStringContainsString( 'oobj-search-panel__chip--on" href="' . esc_url( $this->notice()->search_url( 'titanium spiral' ) ) . '" aria-label="Remove Under $10 from the search">Under $10</a>', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $this->notice()->search_url( 'threadless titanium spiral under $10' ) ) . '" aria-label="Threadless, 3 products">', $html, 'Worded as the suggestions are' );
	}

	public function test_nothing_when_nothing_was_found() {
		$this->search( 'titanium' );

		$this->assertSame( '', $this->notice()->html() );
	}

	public function test_each_part_links_to_the_search_without_it() {
		$this->product( 'Titanium Ring', 6, array( 'nose' ) );
		$this->search( 'titanium nose under $10' );

		$html = $this->notice()->html();

		$this->assertStringContainsString( 'role="status"', $html );
		$this->assertStringContainsString( '>Titanium <span aria-hidden="true">×</span></a>', $html );
		$this->assertStringContainsString( 'aria-label="Search again without Titanium"', $html );
		$this->assertStringContainsString( esc_url( $this->notice()->search_url( 'nose under $10' ) ), $html );
		$this->assertStringContainsString( esc_url( $this->notice()->search_url( 'titanium under $10' ) ), $html );
		$this->assertStringNotContainsString( 'leave out', $html );
		$this->assertStringStartsWith( '<div class="oobj-search-notice"', $this->notice()->before_results( '<div>results</div>' ) );
	}

	public function test_what_was_left_out_is_said() {
		$this->product( 'Titanium Plain Ring', 15 );
		$this->search( 'titanium spiral under $5' );

		$html = $this->notice()->html();

		$this->assertStringContainsString( 'oobj-search-notice__part--left-out', $html );
		$this->assertStringContainsString( 'Nothing matched all of that, so these results leave out: Under $5, “spiral”.', $html );
	}

	public function test_a_theme_can_place_it_itself() {
		$this->product( 'Titanium Ring', 6 );
		$this->search( 'titanium' );
		add_filter( 'oobj_search_show_notice', '__return_false' );

		$this->assertSame( '<div>results</div>', $this->notice()->before_results( '<div>results</div>' ) );
		$this->assertNotSame( '', $this->notice()->html() );
	}

	public function test_links_keep_the_chosen_order() {
		$url = $this->notice()->search_url( 'titanium ring' );
		$this->assertSame( home_url( '/?s=titanium%20ring&post_type=product' ), $url );

		$_GET['orderby'] = 'price';
		$this->assertSame( home_url( '/?s=titanium&post_type=product&orderby=price' ), $this->notice()->search_url( 'titanium' ) );
	}
}
