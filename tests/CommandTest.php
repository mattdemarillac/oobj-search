<?php
/**
 * OOBJ_SEARCH_Command: `wp oobj-search explain|index|coverage`. WP-CLI is a stand-in (tests/stubs/wp-cli.php) that
 * records what is printed.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class CommandTest extends OOBJ_SEARCH_TestCase {

	public function set_up() {
		parent::set_up();
		require_once __DIR__ . '/stubs/wp-cli.php';
		WP_CLI::$messages = array();
	}

	private function command(): OOBJ_SEARCH_Command {
		return new OOBJ_SEARCH_Command( new OOBJ_SEARCH_Query_Parser(), oobj_search( OOBJ_SEARCH_Search::class ), oobj_search( OOBJ_SEARCH_Facts::class ) );
	}

	/**
	 * Everything printed, as one string.
	 */
	private function printed(): string {
		return implode( "\n", array_column( WP_CLI::$messages, 1 ) );
	}

	public function test_explain_says_what_a_query_means_and_finds() {
		$this->product( 'Titanium Ring', 6 );
		$this->product( 'Titanium Bar', 15 );

		$this->command()->explain( array( 'Titanium under $10' ) );

		$printed = $this->printed();
		$this->assertStringContainsString( 'Query: titanium under $10', $printed );
		$this->assertMatchesRegularExpression( '/material\s+Titanium\s+from "titanium"/', $printed );
		$this->assertStringContainsString( 'Words searched for: (none)', $printed );
		$this->assertStringContainsString( 'Finds 1 products.', $printed );
	}

	public function test_explain_says_what_was_left_out() {
		$this->product( 'Titanium Ring', 15 );

		$this->command()->explain( array( 'titanium spiral' ) );

		$this->assertStringContainsString( 'Words searched for: spiral', $this->printed() );
		$this->assertStringContainsString( 'Nothing matched all of it; left out: keywords', $this->printed() );
	}

	public function test_explain_a_plain_search_and_a_product_number() {
		$this->command()->explain( array( 'spiral' ) );
		$this->assertStringContainsString( 'Nothing understood: a plain search.', $this->printed() );

		WP_CLI::$messages = array();
		$this->command()->explain( array( '966' ) );
		$this->assertStringContainsString( 'A product number: goes to the product with SKU or ID 966.', $this->printed() );
	}

	public function test_examples_says_what_each_finds() {
		$this->product( 'Titanium Nose Ring', 6, array( 'nose' ) );

		$this->command()->examples();

		$this->assertMatchesRegularExpression( '/titanium nose ring under \$10\s+1 products/', $this->printed() );
		$this->assertMatchesRegularExpression( '/16g septum clicker\s+1 products \(leaving out [a-z, ]+\)/', $this->printed() );
	}

	public function test_index_works_out_every_products_facts() {
		$product = $this->product( 'Titanium Ring', 6 );
		wp_delete_object_term_relationships( $product->get_id(), OOBJ_SEARCH_Facts::TAXONOMY );
		delete_option( OOBJ_SEARCH_Facts::VERSION_OPTION );

		$this->command()->index();

		$this->assertContains( 'material-titanium', wp_get_object_terms( $product->get_id(), OOBJ_SEARCH_Facts::TAXONOMY, array( 'fields' => 'slugs' ) ) );
		$this->assertSame( OOBJ_SEARCH_Facts::version(), get_option( OOBJ_SEARCH_Facts::VERSION_OPTION ) );
		$this->assertStringContainsString( 'Facts worked out for', $this->printed() );
	}

	public function test_index_does_full_batches_in_turn() {
		for ( $i = 0; $i < OOBJ_SEARCH_Facts::BATCH; $i++ ) {
			self::factory()->post->create( array( 'post_type' => 'product' ) );
		}

		$this->command()->index();

		$this->assertStringContainsString( OOBJ_SEARCH_Facts::BATCH . ' products', $this->printed() );
	}

	public function test_coverage() {
		$this->product( 'Titanium Ring', 6 );

		$this->command()->coverage();

		$this->assertMatchesRegularExpression( '/material\s+1 products \(100%\)/', $this->printed() );
		$this->assertMatchesRegularExpression( '/\d+ facts kept \(\d+ kinds\) for 1 products\./', $this->printed() );
	}

	public function test_coverage_with_no_products() {
		$this->command()->coverage();

		$this->assertMatchesRegularExpression( '/material\s+0 products \(0%\)/', $this->printed() );
	}
}
