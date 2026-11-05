<?php
/**
 * OOBJ_SEARCH_Vocabulary::seasons() and its demand figures.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class VocabularySeasonsTest extends TestCase {

	/**
	 * @dataProvider dates
	 */
	public function test_seasons( string $date, array $seasons ) {
		$this->assertSame( $seasons, OOBJ_SEARCH_Vocabulary::seasons( new DateTimeImmutable( $date ) ) );
	}

	public static function dates(): array {
		return array(
			'before Halloween season' => array( '2026-09-14', array() ),
			'Halloween season starts' => array( '2026-09-15', array( 'halloween' ) ),
			'Halloween'               => array( '2026-10-31', array( 'halloween' ) ),
			'Christmas season starts' => array( '2026-11-01', array( 'christmas' ) ),
			'Christmas'               => array( '2026-12-25', array( 'christmas' ) ),
			'after Christmas'         => array( '2026-12-26', array() ),
			'autumn'                  => array( '2026-04-01', array() ),
		);
	}

	public function test_seasons_name_known_values_and_examples_that_read_as_them() {
		$parser = new OOBJ_SEARCH_Query_Parser();
		foreach ( OOBJ_SEARCH_Vocabulary::SEASONS as $name => $season ) {
			$this->assertArrayHasKey( $season['value'], OOBJ_SEARCH_Vocabulary::CONCEPTS[ $season['facet'] ], $name );
			foreach ( $season['examples'] as $example ) {
				$this->assertContains( $season['value'], $parser->parse( $example )->facets[ $season['facet'] ] ?? array(), $example );
			}
		}
	}

	public function test_demand_is_for_known_values() {
		foreach ( OOBJ_SEARCH_Vocabulary::DEMAND as $facet => $values ) {
			foreach ( array_keys( $values ) as $value ) {
				$known = 'gauge' === $facet ? true : isset( OOBJ_SEARCH_Vocabulary::CONCEPTS[ $facet ][ $value ] );
				$this->assertTrue( $known, "$facet $value" );
			}
		}
	}
}
