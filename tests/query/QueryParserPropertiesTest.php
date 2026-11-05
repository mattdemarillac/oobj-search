<?php
/**
 * OOBJ_SEARCH_Query_Parser: what holds for every vocabulary phrase and every real query.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class QueryParserPropertiesTest extends TestCase {

	/**
	 * Every phrase, alone, is its own value, as one part made of all its words.
	 */
	public function test_every_phrase_reads_as_its_value() {
		$parser = new OOBJ_SEARCH_Query_Parser();
		foreach ( OOBJ_SEARCH_Vocabulary::FACETS as $facet ) {
			foreach ( OOBJ_SEARCH_Vocabulary::phrases( $facet ) as $phrase => $value ) {
				$phrase = (string) $phrase;
				$query  = $parser->parse( $phrase );
				$this->assertSame( array( $facet => array( $value ) ), $query->facets, $phrase );
				$this->assertSame( array( array( $facet, $value, $phrase ) ), array_map( fn( $part ) => array( $part['kind'], $part['value'], $part['words'] ), $query->parts ), $phrase );
				$this->assertSame( '', $query->keywords, $phrase );
			}
		}
	}

	public function test_every_sort_phrase_is_its_sort() {
		$parser = new OOBJ_SEARCH_Query_Parser();
		foreach ( OOBJ_SEARCH_Vocabulary::PRICE_LOW_PHRASES as $phrase ) {
			$this->assertSame( 'price', $parser->parse( $phrase )->sort, $phrase );
		}
		foreach ( OOBJ_SEARCH_Vocabulary::PRICE_HIGH_PHRASES as $phrase ) {
			$this->assertSame( 'price-desc', $parser->parse( $phrase )->sort, $phrase );
		}
	}

	/**
	 * Every value has a label of its own, not its value.
	 */
	public function test_every_value_has_a_label() {
		foreach ( OOBJ_SEARCH_Vocabulary::CONCEPTS as $facet => $concepts ) {
			foreach ( array_keys( $concepts ) as $value ) {
				$this->assertNotSame( $value, OOBJ_SEARCH_Vocabulary::label( $facet, (string) $value ), "$facet $value" );
			}
		}
	}

	/**
	 * No phrase means two things, and none is a stop word or a price word (it would never be read).
	 */
	public function test_phrases_mean_one_thing() {
		$seen = array();
		foreach ( OOBJ_SEARCH_Vocabulary::FACETS as $facet ) {
			foreach ( array_keys( OOBJ_SEARCH_Vocabulary::phrases( $facet ) ) as $phrase ) {
				$this->assertArrayNotHasKey( (string) $phrase, $seen, "$phrase is $facet and " . ( $seen[ $phrase ] ?? '' ) );
				$seen[ (string) $phrase ] = $facet;
			}
		}
		$words = array_merge( OOBJ_SEARCH_Vocabulary::STOP_WORDS, OOBJ_SEARCH_Vocabulary::PRICE_LOW_PHRASES, OOBJ_SEARCH_Vocabulary::PRICE_HIGH_PHRASES, OOBJ_SEARCH_Query_Parser::MAX_BEFORE, OOBJ_SEARCH_Query_Parser::MIN_BEFORE );
		$this->assertSame( array(), array_values( array_intersect( array_keys( $seen ), $words ) ) );
	}

	/**
	 * @dataProvider real_queries
	 */
	public function test_reading_the_keywords_again_finds_nothing_new( string $text ) {
		$parser = new OOBJ_SEARCH_Query_Parser();
		$query  = $parser->parse( $text );
		$again  = $parser->parse( $query->keywords );
		$this->assertSame( array(), $again->parts, $query->keywords );
		$this->assertSame( $query->keywords, $again->keywords );
	}

	/**
	 * Every word of the query is in one part or the keywords, or dropped (a stop word, symbols, a noise phrase), and
	 * the parts come in query order.
	 *
	 * @dataProvider real_queries
	 */
	public function test_each_word_is_read_once( string $text ) {
		$query = ( new OOBJ_SEARCH_Query_Parser() )->parse( $text );
		$left  = explode( ' ', $query->normalised );
		$at    = 0;
		$found = array_merge( array_column( $query->parts, 'words' ), array( $query->keywords, (string) $query->product_number ) );
		foreach ( preg_split( '/ /', implode( ' ', $found ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			$key = array_search( $word, $left, true );
			$this->assertNotFalse( $key, "$word read twice" );
			unset( $left[ $key ] );
		}
		$noise = explode( ' ', implode( ' ', OOBJ_SEARCH_Query_Parser::NOISE_PHRASES ) );
		foreach ( $left as $word ) {
			$this->assertTrue( in_array( $word, array_merge( OOBJ_SEARCH_Vocabulary::STOP_WORDS, $noise ), true ) || 0 === preg_match( '/[\p{L}\p{N}]/u', $word ), "$word dropped" );
		}
		foreach ( $query->parts as $part ) {
			$this->assertSame( 1, preg_match( OOBJ_SEARCH_Text::pattern( $part['words'] ), $query->normalised, $match, PREG_OFFSET_CAPTURE, $at ), $part['words'] );
			$at = $match[0][1] + strlen( $part['words'] );
		}
	}

	/**
	 * Removing a part leaves the others: each is still read, though it may join a longer phrase now beside it ("flat
	 * back push pin earrings" without push pin is "flat back earrings").
	 *
	 * @dataProvider real_queries
	 */
	public function test_without_a_part_keeps_the_others( string $text ) {
		$parser = new OOBJ_SEARCH_Query_Parser();
		$query  = $parser->parse( $text );
		$words  = array_column( $query->parts, 'words' );
		$this->assertSame( $query->normalised, $query->without( count( $words ) ), 'no such part' );
		foreach ( array_keys( $query->parts ) as $index ) {
			$rest = array_column( $parser->parse( $query->without( $index ) )->parts, 'words' );
			foreach ( $words as $other => $part ) {
				if ( $other !== $index ) {
					$this->assertNotEmpty( array_filter( $rest, fn( $words ) => OOBJ_SEARCH_Text::has( $words, $part ) ), "$part, in " . $query->without( $index ) );
				}
			}
		}
	}

	/**
	 * Reading is stable: the same query reads the same, and so does its normalised form.
	 *
	 * @dataProvider real_queries
	 */
	public function test_stable( string $text ) {
		$parser = new OOBJ_SEARCH_Query_Parser();
		$once   = $parser->parse( $text );
		$twice  = $parser->parse( $once->normalised );
		$this->assertEquals( array( $once->facets, $once->parts, $once->keywords, $once->min_price, $once->max_price, $once->sort ), array( $twice->facets, $twice->parts, $twice->keywords, $twice->min_price, $twice->max_price, $twice->sort ) );
	}

	public static function real_queries(): array {
		$queries = array();
		foreach ( require __DIR__ . '/fixtures/real-queries.php' as $query ) {
			$queries[ $query[0] ] = array( $query[0] );
		}
		foreach ( array( 'cheap titanium threadless septum 16g under $10', 'black nose ring 8mm between $5 and $10', '16g/1.2mm gold under 10 dollars', 'gold ring gold' ) as $query ) {
			$queries[ $query ] = array( $query );
		}
		return $queries;
	}
}
