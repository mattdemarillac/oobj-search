<?php
/**
 * OOBJ_SEARCH_Query: the query without one of its parts.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase {

	/**
	 * A query with parts set by hand.
	 *
	 * @param string   $text  The query.
	 * @param string[] $words Each part's words.
	 */
	private static function query( string $text, array $words ): OOBJ_SEARCH_Query {
		$query = new OOBJ_SEARCH_Query( $text );
		foreach ( $words as $part ) {
			$query->parts[] = array(
				'kind'  => 'colour',
				'value' => $part,
				'label' => $part,
				'words' => $part,
			);
		}
		return $query;
	}

	public function test_without_removes_the_parts_own_words() {
		$query = self::query( 'Gold ring, gold!', array( 'gold', 'gold' ) );
		$this->assertSame( 'ring gold', $query->without( 0 ) );
		$this->assertSame( 'gold ring', $query->without( 1 ) );
	}

	public function test_without_an_unknown_part_is_the_whole_query() {
		$this->assertSame( 'gold ring', self::query( 'gold ring', array( 'gold' ) )->without( 3 ) );
	}

	public function test_parts_out_of_order_fall_back_to_the_first_match() {
		$query = self::query( 'black ring gold', array( 'gold', 'black' ) );
		$this->assertSame( 'ring gold', $query->without( 1 ) );
		$this->assertSame( 'black ring', $query->without( 0 ) );
	}

	public function test_words_not_in_the_query_leave_it_whole() {
		$this->assertSame( 'black ring', self::query( 'black ring', array( 'gold' ) )->without( 0 ) );
	}
}
