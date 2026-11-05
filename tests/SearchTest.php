<?php
/**
 * OOBJ_SEARCH_Search: the shop's product search narrowed by what its query means, let go of in order when nothing
 * matches, and a product number going straight to its product.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class SearchTest extends OOBJ_SEARCH_TestCase {

	private function search_service(): OOBJ_SEARCH_Search {
		return oobj_search( OOBJ_SEARCH_Search::class );
	}

	public function test_it_is_registered() {
		$search = $this->search_service();

		$this->assertSame( 10, has_action( 'pre_get_posts', array( $search, 'narrow' ) ) );
		$this->assertSame( 10, has_action( 'template_redirect', array( $search, 'go_to_product' ) ) );
		$this->assertSame( 10, has_filter( 'get_search_query', array( $search, 'typed' ) ) );
		$this->assertSame( 10, has_filter( 'posts_clauses', array( $search, 'clauses' ) ) );
	}

	public function test_a_query_not_understood_is_a_plain_search() {
		$this->product( 'Spiral Twist Barbell', 9 );
		$this->product( 'Plain Ring', 9 );

		$query = $this->search( 'spiral' );

		$this->assertSame( array( 'Spiral Twist Barbell' ), $this->names( $query ) );
		$this->assertFalse( $this->search_service()->current()['query']->understood() );
		$this->assertSame( 'spiral', get_search_query( false ) );
	}

	public function test_a_category_finds_its_products_and_its_subcategories() {
		$this->product( 'Nostril Screw', 5, array( 'nose' ) );
		$this->product( 'Belly Bar', 5, array( 'navel/belly-rings' ) );
		$this->product( 'Tongue Bar', 5, array( 'tongue' ) );

		$this->assertSame( array( 'Nostril Screw' ), $this->names( $this->search( 'nose' ) ) );
		$this->assertSame( array( 'Belly Bar' ), $this->names( $this->search( 'belly' ) ), 'A subcategory of the belly category' );
	}

	public function test_facts_narrow_the_search() {
		$this->product( 'Titanium Labret', 12 );
		$this->product( 'Surgical Steel Labret', 6 );

		$this->assertSame( array( 'Titanium Labret' ), $this->names( $this->search( 'titanium' ) ) );
	}

	public function test_a_price_finds_products_with_any_variation_in_range() {
		$this->product( 'Cheap Ring', 6 );
		$this->product( 'Dear Ring', 25 );
		$this->variable_product(
			'Mixed Ring',
			array(
				'Gold'  => 8,
				'Black' => 15,
			)
		);

		$under = $this->names( $this->search( 'under $10' ) );
		sort( $under );
		$this->assertSame( array( 'Cheap Ring', 'Mixed Ring' ), $under );

		$over = $this->names( $this->search( 'over $20' ) );
		$this->assertSame( array( 'Dear Ring' ), $over );
	}

	public function test_cheap_orders_by_price_unless_the_shopper_chose_an_order() {
		$this->product( 'Titanium Ring Dear', 20 );
		$this->product( 'Titanium Ring Mid', 10 );
		$this->product( 'Titanium Ring Low', 5 );

		$this->assertSame( array( 'Titanium Ring Low', 'Titanium Ring Mid', 'Titanium Ring Dear' ), $this->names( $this->search( 'cheap titanium' ) ) );
		$this->assertSame( array( 'Titanium Ring Dear', 'Titanium Ring Mid', 'Titanium Ring Low' ), $this->names( $this->search( 'expensive titanium' ) ) );

		$chosen = $this->search( 'cheap titanium', array( 'orderby' => 'date' ) );
		$this->assertEmpty( $chosen->get( 'oobj_search_sort' ) );
	}

	public function test_leftover_words_are_still_searched_for() {
		$this->product( 'Titanium Spiral Barbell', 12 );
		$this->product( 'Titanium Plain Barbell', 12 );

		$query = $this->search( 'titanium spiral' );

		$this->assertSame( array( 'Titanium Spiral Barbell' ), $this->names( $query ) );
		$this->assertSame( array(), $this->search_service()->current()['relaxed'] );
	}

	public function test_when_nothing_matches_the_least_important_parts_are_let_go() {
		$this->product( 'Titanium Plain Barbell', 12 );

		$query   = $this->search( 'titanium spiral' );
		$current = $this->search_service()->current();

		$this->assertSame( array( 'Titanium Plain Barbell' ), $this->names( $query ) );
		$this->assertSame( array( 'keywords' ), $current['relaxed'] );
		$this->assertTrue( $current['found'] );
	}

	public function test_the_price_goes_before_the_category() {
		$this->product( 'Nostril Screw', 15, array( 'nose' ) );

		$query = $this->search( 'nose under $5' );

		$this->assertSame( array( 'Nostril Screw' ), $this->names( $query ) );
		$this->assertSame( array( 'price' ), $this->search_service()->current()['relaxed'] );
	}

	public function test_nothing_found_at_all_is_logged() {
		$logged = array();
		add_filter(
			'woocommerce_logger_log_message',
			function ( $message, $level, $context ) use ( &$logged ) {
				$logged[] = array( $context['source'] ?? '', $message );
				return null;
			},
			10,
			3
		);

		$this->search( 'titanium' );

		$this->assertFalse( $this->search_service()->current()['found'] );
		$this->assertContains( array( OOBJ_SEARCH_Search::LOG_SOURCE, 'No products for: titanium' ), $logged );
	}

	public function test_products_hidden_from_search_stay_hidden() {
		$this->product( 'Titanium Hidden', 12, array(), array( 'catalog_visibility' => 'catalog' ) );
		$this->product( 'Titanium Shown', 12 );

		$this->assertSame( array( 'Titanium Shown' ), $this->names( $this->search( 'titanium' ) ) );
	}

	public function test_the_heading_shows_what_was_typed() {
		$this->product( 'Titanium Ring', 12 );

		$this->search( 'Titanium spiral' );

		$this->assertSame( 'Titanium spiral', get_search_query( false ) );
	}

	public function test_only_the_shops_product_search_is_narrowed() {
		$this->product( 'Titanium Ring', 12 );
		$this->go_to( home_url( '/?s=titanium' ) );

		$this->assertNull( $this->search_service()->current(), 'Not a product search' );
		$this->assertFalse( OOBJ_SEARCH_Search::is_product_search( new WP_Query( array( 'post_type' => 'product' ) ) ) );
	}

	/**
	 * The search, with what go_to_product() redirects to recorded instead of ending the request.
	 *
	 * @param string|null $location Where it redirected, set when it does.
	 */
	private function redirecting( ?string &$location ): OOBJ_SEARCH_Search {
		add_filter(
			'wp_redirect',
			function ( $to ) use ( &$location ) {
				$location = $to;
				return false;
			}
		);
		return new class( new OOBJ_SEARCH_Query_Parser() ) extends OOBJ_SEARCH_Search {
			protected function stop(): void {}
		};
	}

	public function test_a_product_number_goes_to_the_product() {
		$product  = $this->product( 'Spiral Twist', 9, array(), array( 'sku' => '966' ) );
		$location = null;
		$search   = $this->redirecting( $location );

		$this->search( '966' );
		$search->go_to_product();

		$this->assertSame( get_permalink( $product->get_id() ), $location );
	}

	public function test_a_variations_number_goes_to_its_product() {
		$product   = $this->variable_product( 'Mixed Ring', array( 'Gold' => 8 ) );
		$variation = wc_get_product( $product->get_children()[0] );
		$variation->set_sku( '4019' );
		$variation->save();
		$location = null;
		$search   = $this->redirecting( $location );

		$this->search( '4019' );
		$search->go_to_product();

		$this->assertSame( get_permalink( $product->get_id() ), $location );
	}

	public function test_a_number_that_isnt_a_shown_product_stays_a_search() {
		$this->product(
			'Hidden',
			9,
			array(),
			array(
				'sku'    => '5043',
				'status' => 'draft',
			)
		);
		$location = null;
		$search   = $this->redirecting( $location );

		$this->search( '5043' );
		$search->go_to_product();
		$this->search( '7777' );
		$search->go_to_product();
		$this->search( 'titanium' );
		$search->go_to_product();
		$this->go_to( home_url( '/' ) );
		$search->go_to_product();

		$this->assertNull( $location );
	}
}
