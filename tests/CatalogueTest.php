<?php
/**
 * OOBJ_SEARCH_Catalogue: how many published products have each fact value, cached.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class CatalogueTest extends OOBJ_SEARCH_TestCase {

	public function test_counts_published_products_by_fact() {
		delete_transient( OOBJ_SEARCH_Catalogue::CACHE_KEY );
		$this->product( 'Titanium Ring', 6 );
		$this->product( 'Titanium Bar', 6 );
		$this->product( 'Titanium Draft', 6, array(), array( 'status' => 'draft' ) );

		$counts = ( new OOBJ_SEARCH_Catalogue() )->counts();

		$this->assertSame( 2, $counts['total'] );
		$this->assertSame( 2, $counts['facts']['material']['titanium'] );
	}

	public function test_counts_are_kept_until_facts_change() {
		delete_transient( OOBJ_SEARCH_Catalogue::CACHE_KEY );
		$catalogue = new OOBJ_SEARCH_Catalogue();
		$this->product( 'Titanium Ring', 6 );
		$first = $catalogue->counts();

		$this->product( 'Titanium Bar', 6 );
		$this->assertSame( $first, $catalogue->counts(), 'Kept' );

		set_transient(
			OOBJ_SEARCH_Catalogue::CACHE_KEY,
			array(
				'version' => 'old',
				'counts'  => $first,
			)
		);
		$this->assertSame( 2, $catalogue->counts()['facts']['material']['titanium'], 'Worked out again for new facts' );
	}

	public function test_fact_names_by_facet() {
		$this->assertSame(
			array(
				'material' => array( 'titanium' => 3 ),
				'gauge'    => array( '1.2' => 2 ),
			),
			OOBJ_SEARCH_Catalogue::by_facet(
				array(
					(object) array(
						'name'     => 'material: titanium',
						'products' => '3',
					),
					(object) array(
						'name'     => 'gauge: 1.2',
						'products' => 2,
					),
				)
			)
		);
	}
}
