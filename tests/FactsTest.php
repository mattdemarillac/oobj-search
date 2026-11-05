<?php
/**
 * OOBJ_SEARCH_Facts: product facts kept as hidden terms, worked out when a product or variation is saved, and for
 * every product when the way they're worked out changes.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class FactsTest extends OOBJ_SEARCH_TestCase {

	private function facts(): OOBJ_SEARCH_Facts {
		return oobj_search( OOBJ_SEARCH_Facts::class );
	}

	/**
	 * A product's fact slugs, sorted.
	 *
	 * @param int $product_id The product.
	 * @return string[]
	 */
	private function slugs( int $product_id ): array {
		$slugs = wp_get_object_terms( $product_id, OOBJ_SEARCH_Facts::TAXONOMY, array( 'fields' => 'slugs' ) );
		sort( $slugs );
		return $slugs;
	}

	public function test_it_is_registered_and_hidden() {
		$facts    = $this->facts();
		$taxonomy = get_taxonomy( OOBJ_SEARCH_Facts::TAXONOMY );

		$this->assertFalse( $taxonomy->public );
		$this->assertFalse( $taxonomy->show_ui );
		$this->assertFalse( $taxonomy->rewrite );
		$this->assertSame( array( 'product' ), $taxonomy->object_type );
		$this->assertSame( 10, has_action( 'woocommerce_update_product', array( $facts, 'index' ) ) );
		$this->assertSame( 10, has_action( 'woocommerce_update_product_variation', array( $facts, 'index_parent' ) ) );
		$this->assertSame( 10, has_action( OOBJ_SEARCH_Facts::BATCH_HOOK, array( $facts, 'index_batch' ) ) );
	}

	public function test_slugs() {
		$this->assertSame( 'material-titanium', OOBJ_SEARCH_Facts::slug( 'material', 'titanium' ) );
		$this->assertSame( 'gauge-1-2', OOBJ_SEARCH_Facts::slug( 'gauge', '1.2' ) );
		$this->assertSame( 'colour-rose-gold', OOBJ_SEARCH_Facts::slug( 'colour', 'rose-gold' ) );
	}

	public function test_a_saved_product_gets_its_facts() {
		$product = $this->product( 'Titanium Threadless Flat Back Labret', 12 );

		$slugs = $this->slugs( $product->get_id() );

		$this->assertContains( 'material-titanium', $slugs );
		$this->assertContains( 'fitting-threadless', $slugs );
		$this->assertSame( 'material: titanium', get_term_by( 'slug', 'material-titanium', OOBJ_SEARCH_Facts::TAXONOMY )->name );
	}

	public function test_facts_follow_the_product() {
		$product = $this->product( 'Titanium Labret', 12 );
		$product->set_name( 'Surgical Steel Labret' );
		$product->save();

		$slugs = $this->slugs( $product->get_id() );

		$this->assertNotContains( 'material-titanium', $slugs );
		$this->assertContains( 'material-surgical-steel', $slugs );
	}

	public function test_a_variable_products_facts_include_its_variations() {
		$product = $this->variable_product(
			'Plain Ring',
			array(
				'Black, 16G' => 8,
				'Gold, 16G'  => 9,
			)
		);

		$slugs = $this->slugs( $product->get_id() );

		$this->assertContains( 'colour-black', $slugs );
		$this->assertContains( 'colour-gold', $slugs );
		$this->assertContains( 'gauge-1-2', $slugs );
	}

	public function test_saving_a_variation_works_out_its_products_facts_again() {
		$product = $this->variable_product( 'Plain Ring', array( 'Black' => 8 ) );
		wp_delete_object_term_relationships( $product->get_id(), OOBJ_SEARCH_Facts::TAXONOMY );

		$variation = wc_get_product( $product->get_children()[0] );
		$variation->set_regular_price( '7' );
		$variation->save();

		$this->assertContains( 'colour-black', $this->slugs( $product->get_id() ) );
	}

	public function test_a_variation_or_missing_product_has_no_facts_of_its_own() {
		$product = $this->variable_product( 'Plain Ring', array( 'Black' => 8 ) );

		$this->assertSame( array(), $this->facts()->index( $product->get_children()[0] ) );
		$this->assertSame( array(), $this->facts()->index( 999999 ) );
		$this->facts()->index_parent( 999999 );
	}

	public function test_product_data_reads_categories_attributes_and_tags() {
		$product = $this->variable_product( 'Plain Ring', array( 'Black' => 8 ), array( 'navel/belly-rings' ) );
		wp_set_object_terms( $product->get_id(), array( 'Spooky' ), 'product_tag' );
		$product->set_description( '<p>Material: <b>Titanium</b></p>' );
		$product->save();

		$data = OOBJ_SEARCH_Facts::product_data( wc_get_product( $product->get_id() ) );

		$this->assertSame( 'Plain Ring', $data['name'] );
		$this->assertSame( array( 'navel/belly-rings' ), $data['categories'] );
		$this->assertSame( array( 'Colour / size' => array( 'Black' ) ), $data['attributes'] );
		$this->assertSame( array( 'Spooky' ), $data['tags'] );
		$this->assertSame( 'Material: Titanium', $data['description'] );
	}

	public function test_product_data_reads_global_attributes_by_their_terms() {
		$attribute_id = wc_create_attribute(
			array(
				'name' => 'Colours',
				'slug' => 'colours',
			)
		);
		register_taxonomy( 'pa_colours', 'product' );
		$gold      = wp_insert_term( 'Gold', 'pa_colours' );
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( $attribute_id );
		$attribute->set_name( 'pa_colours' );
		$attribute->set_options( array( $gold['term_id'] ) );
		$product = $this->product( 'Plain Ring', 8 );
		$product->set_attributes( array( $attribute ) );
		$product->save();

		$data = OOBJ_SEARCH_Facts::product_data( wc_get_product( $product->get_id() ) );

		$this->assertSame( array( 'pa_colours' => array( 'Gold' ) ), $data['attributes'] );
		$this->assertContains( 'colour-gold', $this->slugs( $product->get_id() ) );
	}

	public function test_a_new_way_of_working_out_facts_redoes_every_product_in_batches() {
		as_unschedule_all_actions( OOBJ_SEARCH_Facts::BATCH_HOOK );
		update_option( OOBJ_SEARCH_Facts::VERSION_OPTION, 'old' );

		$this->facts()->maybe_rebuild();

		$this->assertSame( OOBJ_SEARCH_Facts::version(), get_option( OOBJ_SEARCH_Facts::VERSION_OPTION ) );
		$this->assertNotFalse( as_next_scheduled_action( OOBJ_SEARCH_Facts::BATCH_HOOK, array( 0 ) ) );

		as_unschedule_all_actions( OOBJ_SEARCH_Facts::BATCH_HOOK );
		$this->facts()->maybe_rebuild();
		$this->assertFalse( as_next_scheduled_action( OOBJ_SEARCH_Facts::BATCH_HOOK ), 'Not again for the same version' );
	}

	public function test_a_batch_does_its_products() {
		$first  = $this->product( 'Titanium Ring', 12 );
		$second = $this->product( 'Titanium Bar', 12 );
		wp_delete_object_term_relationships( $first->get_id(), OOBJ_SEARCH_Facts::TAXONOMY );
		wp_delete_object_term_relationships( $second->get_id(), OOBJ_SEARCH_Facts::TAXONOMY );
		as_unschedule_all_actions( OOBJ_SEARCH_Facts::BATCH_HOOK );

		$this->assertSame( array( $first->get_id() ), $this->facts()->product_ids( $first->get_id() - 1, 1 ) );

		$this->assertSame( 2, $this->facts()->index_batch( $first->get_id() - 1 ) );
		$this->assertContains( 'material-titanium', $this->slugs( $first->get_id() ) );
		$this->assertContains( 'material-titanium', $this->slugs( $second->get_id() ) );
		$this->assertFalse( as_next_scheduled_action( OOBJ_SEARCH_Facts::BATCH_HOOK ), 'Fewer than a batch: the last' );
	}

	public function test_the_last_batch_forgets_the_catalogue_and_says_so() {
		as_unschedule_all_actions( OOBJ_SEARCH_Facts::BATCH_HOOK );
		set_transient( OOBJ_SEARCH_Catalogue::CACHE_KEY, array( 'stale' ) );
		$fired = did_action( 'oobj_search_indexed' );

		$this->facts()->index_batch( PHP_INT_MAX - 1 );

		$this->assertFalse( get_transient( OOBJ_SEARCH_Catalogue::CACHE_KEY ) );
		$this->assertSame( $fired + 1, did_action( 'oobj_search_indexed' ) );
		$this->assertFalse( OOBJ_SEARCH_Facts::indexing() );
	}

	public function test_a_full_batch_queues_the_next() {
		$ids = array();
		for ( $i = 0; $i < OOBJ_SEARCH_Facts::BATCH; $i++ ) {
			$ids[] = self::factory()->post->create( array( 'post_type' => 'product' ) );
		}
		as_unschedule_all_actions( OOBJ_SEARCH_Facts::BATCH_HOOK );

		$this->assertSame( OOBJ_SEARCH_Facts::BATCH, $this->facts()->index_batch( $ids[0] - 1 ) );

		$this->assertNotFalse( as_next_scheduled_action( OOBJ_SEARCH_Facts::BATCH_HOOK, array( end( $ids ) ) ) );
		$this->assertTrue( OOBJ_SEARCH_Facts::indexing() );
	}

	public function test_coverage_counts_products_with_each_facet() {
		$this->product( 'Titanium Ring', 12 );
		$this->product( 'Plain Ring', 12 );

		$coverage = $this->facts()->coverage();

		$this->assertSame( 2, $coverage['products'] );
		$this->assertSame( 1, $coverage['facets']['material'] );
		$this->assertGreaterThanOrEqual( 1, $coverage['relationships'] );
		$this->assertGreaterThanOrEqual( 1, $coverage['terms'] );
		$this->assertSame( OOBJ_SEARCH_Vocabulary::FACT_FACETS, array_keys( $coverage['facets'] ) );
	}
}
