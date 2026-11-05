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
