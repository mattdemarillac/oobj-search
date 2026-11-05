<?php
/**
 * OOBJ_SEARCH_Suggestions: searches to try next, one of each kind, built from what was searched and the products
 * it found.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class SuggestionsTest extends OOBJ_SEARCH_TestCase {

	private function suggestions(): OOBJ_SEARCH_Suggestions {
		return new OOBJ_SEARCH_Suggestions( new OOBJ_SEARCH_Query_Parser() );
	}

	/**
	 * Ten rings: titanium or steel, three threadless, three black, two heart-shaped, three 16g, prices 4 to 22.
	 *
	 * @return int[] Their IDs.
	 */
	private function rings(): array {
		$names = array(
			'Titanium Threadless Ring'  => 4,
			'Titanium Threadless Heart' => 6,
			'Titanium Threadless Black' => 9,
			'Surgical Steel Ring 16G'   => 12,
			'Surgical Steel Ring 14G'   => 14,
			'Surgical Steel Black Ring' => 16,
			'Surgical Steel Heart Ring' => 18,
			'Surgical Steel Plain Ring' => 20,
			'Steel Black Ring 16G'      => 21,
			'Steel Ring Two 16G'        => 22,
		);
		$ids   = array();
		foreach ( $names as $name => $price ) {
			$ids[] = $this->product( $name, $price )->get_id();
		}
		return $ids;
	}

	private function texts( array $suggestions ): array {
		return array_column( $suggestions, 'text', 'kind' );
	}

	public function test_one_search_of_each_kind_from_what_was_found() {
		$ids = $this->rings();

		$suggestions = $this->suggestions()->suggest( ( new OOBJ_SEARCH_Query_Parser() )->parse( 'ring' ), $ids );

		$this->assertCount( OOBJ_SEARCH_Suggestions::MAX, $suggestions );
		$texts = $this->texts( $suggestions );
		$this->assertSame( array( 'material', 'price', 'colour', 'fitting', 'gauge' ), array_keys( $texts ), 'Hearts: too few; one of each kind, at most MAX' );
		$this->assertSame( '16g ring', $texts['gauge'] );
		$this->assertSame( 'titanium ring', $texts['material'], 'Closest to a third of the results' );
		$this->assertSame( 'ring under $10', $texts['price'], 'The lowest price with a fifth of the results' );
		$this->assertSame( 'black ring', $texts['colour'] );
		$this->assertSame( 'threadless ring', $texts['fitting'] );
		$this->assertSame( 3, array_column( $suggestions, 'count', 'kind' )['material'] );
	}

	public function test_kinds_the_search_names_are_left_alone() {
		$ids = $this->rings();

		$texts = $this->texts( $this->suggestions()->suggest( ( new OOBJ_SEARCH_Query_Parser() )->parse( 'cheap titanium ring under $30' ), $ids ) );

		$this->assertArrayNotHasKey( 'material', $texts );
		$this->assertArrayNotHasKey( 'price', $texts );
		$this->assertArrayNotHasKey( 'sort', $texts );
	}

	public function test_cheapest_first_when_nothing_else_narrows() {
		$ids = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$ids[] = $this->product( "Plain Ring $i", 30 )->get_id();
		}

		$suggestions = $this->suggestions()->suggest( ( new OOBJ_SEARCH_Query_Parser() )->parse( 'plain ring' ), $ids );

		$this->assertSame(
			array(
				array(
					'text'  => 'cheapest plain ring',
					'kind'  => 'sort',
					'count' => 6,
				),
			),
			$suggestions
		);
	}

	public function test_nothing_for_few_results_or_no_words() {
		$ids = $this->rings();

		$this->assertSame( array(), $this->suggestions()->suggest( ( new OOBJ_SEARCH_Query_Parser() )->parse( 'ring' ), array_slice( $ids, 0, 5 ) ) );
		$this->assertSame( array(), $this->suggestions()->suggest( ( new OOBJ_SEARCH_Query_Parser() )->parse( '' ), $ids ) );
	}

	public function test_a_fact_a_search_cant_name_isnt_suggested() {
		$ids = $this->rings();
		wp_insert_term( 'colour: mauve', OOBJ_SEARCH_Facts::TAXONOMY, array( 'slug' => 'colour-mauve' ) );
		foreach ( array_slice( $ids, 0, 4 ) as $id ) {
			wp_set_object_terms( $id, array( 'colour-mauve' ), OOBJ_SEARCH_Facts::TAXONOMY, true );
		}

		$texts = $this->texts( $this->suggestions()->suggest( ( new OOBJ_SEARCH_Query_Parser() )->parse( 'ring' ), $ids ) );

		$this->assertSame( 'black ring', $texts['colour'] );
	}
}
