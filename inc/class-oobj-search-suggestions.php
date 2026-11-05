<?php
/**
 * Class OOBJ_SEARCH_Suggestions.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Searches to try next, built from what was searched and what it found, so shoppers see what else the search
 * understands: each adds something of a different kind (a material, a fitting, a gauge, a colour, a theme, a price,
 * cheapest first) that the found products have. "nose ring" offers "titanium nose ring", "threadless nose ring",
 * "16g nose ring", "nose ring under $10"… each with how many products it finds, and only when it narrows the results.
 *
 * Counted from the found products' facts (OOBJ_SEARCH_Facts) and prices (WooCommerce's product lookup table), with
 * two queries, when the search page is built (search pages are cached). Of each kind, the value that keeps closest to
 * IDEAL_SHARE of the results, which shows the search off better than the most common one.
 */
class OOBJ_SEARCH_Suggestions {

	/**
	 * The kinds of suggestion, in the order offered: one of each, at most MAX.
	 */
	const KINDS = array( 'material', 'price', 'colour', 'fitting', 'theme', 'gauge', 'sort' );

	const MAX = 5;

	/**
	 * The share of the found products a suggestion should ideally keep: one that picks out some of them shows more
	 * than one most of them have ("titanium nose ring" over "surgical steel nose ring").
	 */
	const IDEAL_SHARE = 0.3;

	/**
	 * Fewer results than this need no narrowing.
	 */
	const MIN_RESULTS = 6;

	/**
	 * A suggestion must find at least this many products.
	 */
	const MIN_FOUND = 3;

	/**
	 * Prices to offer "under" (the shop's prices are mostly under $15).
	 */
	const PRICES = array( 5, 8, 10, 15, 20 );

	/**
	 * Reads queries.
	 *
	 * @var OOBJ_SEARCH_Query_Parser
	 */
	private $parser;

	public function __construct( OOBJ_SEARCH_Query_Parser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * Searches to try next.
	 *
	 * @param OOBJ_SEARCH_Query $query What was searched.
	 * @param int[]             $ids   The products it found.
	 * @return array<int, array{text: string, kind: string, count: int}>
	 */
	public function suggest( OOBJ_SEARCH_Query $query, array $ids ): array {
		$total = count( $ids );
		if ( $total < self::MIN_RESULTS || '' === $query->normalised ) {
			return array();
		}

		$facts       = $this->fact_counts( $ids );
		$suggestions = array();
		foreach ( self::KINDS as $kind ) {
			$suggestion = 'price' === $kind ? $this->price( $query, $ids, $total ) : ( 'sort' === $kind ? $this->sort( $query, $total ) : $this->fact( $query, $kind, $facts[ $kind ] ?? array(), $total ) );
			if ( null !== $suggestion ) {
				$suggestions[] = $suggestion;
			}
			if ( count( $suggestions ) === self::MAX ) {
				break;
			}
		}
		return $suggestions;
	}

	/**
	 * The query with a fact added in front ("titanium nose ring"), for the fact the found products most often have
	 * that leaves fewer of them, when the query doesn't name that kind already.
	 *
	 * @param OOBJ_SEARCH_Query  $query  What was searched.
	 * @param string             $facet  A fact facet.
	 * @param array<string, int> $counts Its values among the found products, most common first.
	 * @param int                $total  How many products were found.
	 * @return array{text: string, kind: string, count: int}|null
	 */
	private function fact( OOBJ_SEARCH_Query $query, string $facet, array $counts, int $total ): ?array {
		if ( ! empty( $query->facets[ $facet ] ) ) {
			return null;
		}
		// Closest to the ideal share first.
		uasort( $counts, fn( $a, $b ) => abs( $a / $total - self::IDEAL_SHARE ) <=> abs( $b / $total - self::IDEAL_SHARE ) );
		foreach ( $counts as $value => $count ) {
			if ( $count < self::MIN_FOUND || $count >= $total ) {
				continue;
			}
			$text = OOBJ_SEARCH_Vocabulary::phrase( $facet, (string) $value ) . ' ' . $query->normalised;
			if ( in_array( (string) $value, $this->parser->parse( $text )->facets[ $facet ] ?? array(), true ) ) {
				return array(
					'text'  => $text,
					'kind'  => $facet,
					'count' => $count,
				);
			}
		}
		return null;
	}

	/**
	 * The query with a price limit ("nose ring under $10"): the lowest of PRICES that still finds a fair share.
	 *
	 * @param OOBJ_SEARCH_Query $query What was searched.
	 * @param int[]             $ids   The products it found.
	 * @param int               $total How many.
	 * @return array{text: string, kind: string, count: int}|null
	 */
	private function price( OOBJ_SEARCH_Query $query, array $ids, int $total ): ?array {
		if ( null !== $query->min_price || null !== $query->max_price ) {
			return null;
		}
		$under = $this->under_counts( $ids );
		foreach ( self::PRICES as $price ) {
			$count = $under[ $price ];
			if ( $count >= max( self::MIN_FOUND, (int) ceil( $total / 5 ) ) && $count < $total ) {
				return array(
					'text'  => $query->normalised . ' under $' . $price,
					'kind'  => 'price',
					'count' => $count,
				);
			}
		}
		return null;
	}

	/**
	 * The query cheapest first ("cheap nose ring"), when it isn't already in an order.
	 *
	 * @param OOBJ_SEARCH_Query $query What was searched.
	 * @param int               $total How many products it found.
	 * @return array{text: string, kind: string, count: int}|null
	 */
	private function sort( OOBJ_SEARCH_Query $query, int $total ): ?array {
		if ( null !== $query->sort ) {
			return null;
		}
		return array(
			'text'  => 'cheapest ' . $query->normalised,
			'kind'  => 'sort',
			'count' => $total,
		);
	}

	/**
	 * How many of the products have each fact, by facet, most common first.
	 *
	 * @param int[] $ids Products.
	 * @return array<string, array<string, int>>
	 */
	private function fact_counts( array $ids ): array {
		global $wpdb;
		$in = implode( ',', array_map( 'intval', $ids ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.name, COUNT(*) AS products FROM {$wpdb->term_relationships} tr
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s AND tr.object_id IN ($in)
				GROUP BY t.term_id ORDER BY products DESC, t.name",
				OOBJ_SEARCH_Facts::TAXONOMY
			)
		);
		// phpcs:enable
		$counts = array();
		foreach ( $rows as $row ) {
			list( $facet, $value )      = array_map( 'trim', explode( ':', $row->name, 2 ) + array( '', '' ) );
			$counts[ $facet ][ $value ] = (int) $row->products;
		}
		return $counts;
	}

	/**
	 * How many of the products have a price up to each of PRICES (any variation).
	 *
	 * @param int[] $ids Products.
	 * @return array<int, int>
	 */
	private function under_counts( array $ids ): array {
		global $wpdb;
		$in      = implode( ',', array_map( 'intval', $ids ) );
		$columns = implode( ', ', array_map( fn( $price ) => "SUM(min_price <= $price) AS under_$price", self::PRICES ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
		$row    = (array) $wpdb->get_row( "SELECT $columns FROM {$wpdb->wc_product_meta_lookup} WHERE product_id IN ($in)" );
		$counts = array();
		foreach ( self::PRICES as $price ) {
			$counts[ $price ] = (int) ( $row[ "under_$price" ] ?? 0 );
		}
		return $counts;
	}
}
