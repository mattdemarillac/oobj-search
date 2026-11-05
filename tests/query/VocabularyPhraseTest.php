<?php
/**
 * OOBJ_SEARCH_Vocabulary::phrase(): a value as a shopper types it, and reading it back gives the value.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class VocabularyPhraseTest extends TestCase {

	public function test_phrases_read_naturally() {
		$this->assertSame( 'titanium', OOBJ_SEARCH_Vocabulary::phrase( 'material', 'titanium' ) );
		$this->assertSame( 'rose gold', OOBJ_SEARCH_Vocabulary::phrase( 'colour', 'rose-gold' ) );
		$this->assertSame( '16g', OOBJ_SEARCH_Vocabulary::phrase( 'gauge', '1.2' ) );
		$this->assertSame( '00g', OOBJ_SEARCH_Vocabulary::phrase( 'gauge', '10' ) );
		$this->assertSame( '7mm', OOBJ_SEARCH_Vocabulary::phrase( 'gauge', '7' ) );
		$this->assertSame( '8mm', OOBJ_SEARCH_Vocabulary::phrase( 'size', '8' ) );
		$this->assertSame( 'unknown', OOBJ_SEARCH_Vocabulary::phrase( 'colour', 'unknown' ) );
	}

	/**
	 * Every fact value's phrase reads back as that value.
	 */
	public function test_every_phrase_reads_back_as_its_value() {
		$parser = new OOBJ_SEARCH_Query_Parser();
		foreach ( array( 'material', 'fitting', 'colour', 'theme' ) as $facet ) {
			foreach ( array_keys( OOBJ_SEARCH_Vocabulary::CONCEPTS[ $facet ] ) as $value ) {
				$query = $parser->parse( OOBJ_SEARCH_Vocabulary::phrase( $facet, (string) $value ) );
				$this->assertSame( array( (string) $value ), $query->facets[ $facet ] ?? array(), "$facet $value" );
			}
		}
		foreach ( OOBJ_SEARCH_Vocabulary::GAUGES as $mm ) {
			$this->assertSame( array( $mm ), $parser->parse( OOBJ_SEARCH_Vocabulary::phrase( 'gauge', $mm ) )->facets['gauge'] ?? array(), "gauge $mm" );
		}
	}
}
