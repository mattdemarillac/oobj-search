<?php
/**
 * OOBJ_SEARCH_Suggestions: searches to try next, scored by what's distinctive about the results, what shoppers
 * search for, a useful share and the season, at most one of each kind, worded naturally.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class SuggestionsTest extends OOBJ_SEARCH_TestCase {

	/**
	 * Suggestions on a date (autumn: no season, unless given).
	 *
	 * @param string $date A date.
	 */
	private function suggestions( string $date = '2026-04-01' ): OOBJ_SEARCH_Suggestions {
		delete_transient( OOBJ_SEARCH_Catalogue::CACHE_KEY );
		return new OOBJ_SEARCH_Suggestions( new OOBJ_SEARCH_Query_Parser(), new OOBJ_SEARCH_Catalogue(), new DateTimeImmutable( $date ) );
	}

	private function parse( string $text ): OOBJ_SEARCH_Query {
		return ( new OOBJ_SEARCH_Query_Parser() )->parse( $text );
	}

	/**
	 * Products by name and price.
	 *
	 * @param array<string, float> $products Name => price.
	 * @param string[]             $categories Their categories.
	 * @return int[]
	 */
	private function products( array $products, array $categories = array() ): array {
		$ids = array();
		foreach ( $products as $name => $price ) {
			$ids[] = $this->product( $name, $price, $categories )->get_id();
		}
		return $ids;
	}

	/**
	 * Ten rings: three titanium threadless, three black, three 16g, two heart-shaped, prices 4 to 22.
	 *
	 * @return int[]
	 */
	private function rings(): array {
		return $this->products(
			array(
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
			)
		);
	}

	private function texts( array $suggestions ): array {
		return array_column( $suggestions, 'text', 'kind' );
	}

	public function test_the_best_of_each_kind_at_most_max() {
		$ids = $this->rings();

		$suggestions = $this->suggestions()->suggest( $this->parse( 'ring' ), $ids );

		$this->assertCount( OOBJ_SEARCH_Suggestions::MAX, $suggestions );
		$this->assertSame( count( $suggestions ), count( array_unique( array_column( $suggestions, 'kind' ) ) ), 'One of each kind' );
		$texts = $this->texts( $suggestions );
		$this->assertSame( 'titanium ring', $texts['material'], 'In demand, and a third of the results' );
		$this->assertSame( 'threadless ring', $texts['fitting'] );
		$this->assertSame( 'ring under $10', $texts['price'] );
		$this->assertSame( 3, array_column( $suggestions, 'count', 'kind' )['material'] );
	}

	public function test_distinctive_values_win_over_common_ones() {
		// The shop: lots of black steel, a little rose gold. The search finds mostly rose gold.
		$this->products(
			array(
				'Black Steel Bar One'   => 9,
				'Black Steel Bar Two'   => 9,
				'Black Steel Bar Three' => 9,
				'Black Steel Bar Four'  => 9,
				'Black Steel Bar Five'  => 9,
				'Black Steel Bar Six'   => 9,
			)
		);
		$ids = $this->products(
			array(
				'Rose Gold Bloom One'   => 9,
				'Rose Gold Bloom Two'   => 9,
				'Rose Gold Bloom Three' => 9,
				'Black Bloom One'       => 9,
				'Black Bloom Two'       => 9,
				'Black Bloom Three'     => 9,
				'Plain Bloom'           => 9,
			)
		);

		$texts = $this->texts( $this->suggestions()->suggest( $this->parse( 'bloom' ), $ids ) );

		$this->assertSame( 'rose gold bloom', $texts['colour'], 'Rose gold is distinctive here; black is everywhere' );
	}

	public function test_in_season_spooky_things_are_favoured() {
		$ids = $this->products(
			array(
				'Skull Ring One'    => 9,
				'Skull Ring Two'    => 9,
				'Skull Ring Three'  => 9,
				'Skull Ring Four'   => 9,
				'Heart Ring One'    => 9,
				'Heart Ring Two'    => 9,
				'Heart Ring Three'  => 9,
				'Plain Ring Nought' => 9,
				'Plain Ring Eleven' => 9,
				'Plain Ring Twelve' => 9,
			)
		);

		$this->assertSame( 'heart ring', $this->texts( $this->suggestions()->suggest( $this->parse( 'ring' ), $ids ) )['theme'], 'Hearts: the better share, as much searched' );
		$this->assertSame( 'spooky ring', $this->texts( $this->suggestions( '2026-10-20' )->suggest( $this->parse( 'ring' ), $ids ) )['theme'] );
	}

	public function test_in_season_a_seasonal_category_is_offered() {
		$ids = array_merge(
			$this->products(
				array(
					'Candy Cane Stud' => 9,
					'Reindeer Stud'   => 9,
					'Bauble Stud'     => 9,
				),
				array( 'christmas-jewellery' )
			),
			$this->products(
				array(
					'Plain Stud One'   => 9,
					'Plain Stud Two'   => 9,
					'Plain Stud Three' => 9,
				)
			)
		);

		$this->assertArrayNotHasKey( 'season', $this->texts( $this->suggestions()->suggest( $this->parse( 'stud' ), $ids ) ) );
		$this->assertSame( 'christmas stud', $this->texts( $this->suggestions( '2026-12-01' )->suggest( $this->parse( 'stud' ), $ids ) )['season'] );
		$this->assertArrayNotHasKey( 'season', $this->texts( $this->suggestions( '2026-12-01' )->suggest( $this->parse( 'nose stud' ), $ids ) ), 'Not when the search names a category' );
	}

	public function test_kinds_the_search_names_are_left_alone() {
		$ids = $this->rings();

		$texts = $this->texts( $this->suggestions()->suggest( $this->parse( 'cheap titanium ring under $30' ), $ids ) );

		$this->assertArrayNotHasKey( 'material', $texts );
		$this->assertArrayNotHasKey( 'price', $texts );
		$this->assertArrayNotHasKey( 'sort', $texts );
	}

	public function test_cheapest_first_when_nothing_else_narrows() {
		$ids = $this->products( array_fill_keys( array( 'Plain Ring A', 'Plain Ring B', 'Plain Ring C', 'Plain Ring D', 'Plain Ring E', 'Plain Ring F' ), 30 ) );

		$this->assertSame(
			array(
				array(
					'text'  => 'plain ring lowest price first',
					'kind'  => 'sort',
					'count' => 6,
				),
			),
			$this->suggestions()->suggest( $this->parse( 'plain ring' ), $ids )
		);
	}

	public function test_counts_are_worked_out_once_per_set_of_results() {
		$ids         = $this->rings();
		$suggestions = $this->suggestions();

		$facts  = $suggestions->facts_of( $ids );
		$prices = $suggestions->prices_of( $ids );
		$this->assertSame( 3, $facts['material']['titanium'] );
		$this->assertSame( 3, $prices[10] );

		global $wpdb;
		$queries = $wpdb->num_queries;
		$this->assertSame( $facts, $suggestions->facts_of( $ids ) );
		$this->assertSame( $prices, $suggestions->prices_of( $ids ) );
		$this->assertSame( $queries, $wpdb->num_queries );
	}

	public function test_nothing_for_few_results() {
		$ids = $this->rings();

		$this->assertSame( array(), $this->suggestions()->suggest( $this->parse( 'ring' ), array_slice( $ids, 0, 5 ) ) );
	}

	public function test_an_empty_search_gets_suggestions_too() {
		$texts = $this->texts( $this->suggestions()->suggest( $this->parse( '' ), $this->rings() ) );

		$this->assertNotEmpty( $texts );
		$this->assertSame( 'titanium', $texts['material'] );
		foreach ( $texts as $text ) {
			$this->assertSame( trim( $text ), $text );
		}
	}

	public function test_a_fact_a_search_cant_name_isnt_suggested() {
		$ids = $this->rings();
		wp_insert_term( 'colour: mauve', OOBJ_SEARCH_Facts::TAXONOMY, array( 'slug' => 'colour-mauve' ) );
		foreach ( array_slice( $ids, 0, 4 ) as $id ) {
			wp_set_object_terms( $id, array( 'colour-mauve' ), OOBJ_SEARCH_Facts::TAXONOMY, true );
		}

		$this->assertSame( 'black ring', $this->texts( $this->suggestions()->suggest( $this->parse( 'ring' ), $ids ) )['colour'] );
	}

	/**
	 * @dataProvider wordings
	 */
	public function test_words_go_where_a_person_would_put_them( string $search, string $facet, string $value, string $text ) {
		$this->assertSame( $text, OOBJ_SEARCH_Suggestions::compose( $this->parse( $search ), $facet, $value ) );
	}

	public static function wordings(): array {
		return array(
			'before'                   => array( 'nose ring', 'material', 'titanium', 'titanium nose ring' ),
			'a noun after'             => array( 'septum', 'fitting', 'clicker', 'septum clicker' ),
			'after a theme'            => array( 'spooky', 'colour', 'black', 'spooky black' ),
			'after a colour'           => array( 'rose gold hoop', 'material', 'titanium', 'rose gold hoop titanium' ),
			'before a price at an end' => array( 'belly bars under $10', 'colour', 'pink', 'pink belly bars under $10' ),
			'a price not at the end'   => array( 'under $10 belly bars', 'colour', 'pink', 'pink under $10 belly bars' ),
		);
	}
}
