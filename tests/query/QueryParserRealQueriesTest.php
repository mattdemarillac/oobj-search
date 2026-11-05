<?php
/**
 * OOBJ_SEARCH_Query_Parser on real queries (fixtures/real-queries.php): each one's whole reading.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class QueryParserRealQueriesTest extends TestCase {

	/**
	 * @dataProvider queries
	 */
	public function test_reading( string $text, array $facets, string $keywords, array $more = array() ) {
		$query    = ( new OOBJ_SEARCH_Query_Parser() )->parse( $text );
		$expected = array(
			'facets'   => $facets,
			'keywords' => $keywords,
			'price'    => $more['price'] ?? null,
			'sort'     => $more['sort'] ?? null,
			'number'   => $more['number'] ?? null,
		);
		$actual   = array(
			'facets'   => array_map( fn( $values ) => implode( ',', $values ), $query->facets ),
			'keywords' => $query->keywords,
			'price'    => null === $query->min_price && null === $query->max_price ? null : $query->min_price . '-' . $query->max_price,
			'sort'     => $query->sort,
			'number'   => $query->product_number,
		);
		$this->assertSame( $expected, $actual );
	}

	public static function queries(): array {
		$queries = array();
		foreach ( require __DIR__ . '/fixtures/real-queries.php' as $query ) {
			$queries[ $query[0] ] = $query;
		}
		return $queries;
	}
}
