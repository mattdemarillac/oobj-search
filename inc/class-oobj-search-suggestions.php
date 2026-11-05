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
 * understands and what this part of the shop has. "nose ring" offers "titanium nose ring", "threadless nose ring",
 * "nose ring under $10"…, each with how many products it finds.
 *
 * Every fact value the found products have (OOBJ_SEARCH_Facts) is a candidate, scored by:
 * - distinctiveness: how much more common it is among these results than in the whole shop (OOBJ_SEARCH_Catalogue),
 *   so spooky things suggest black, not what every product is;
 * - demand: how much shoppers search for it (OOBJ_SEARCH_Vocabulary::DEMAND): titanium, threadless, opal, rose gold;
 * - share: it keeps a useful part of the results, ideally IDEAL_SHARE, never all of them or fewer than MIN_FOUND;
 * - season: in season (OOBJ_SEARCH_Vocabulary::SEASONS), spooky or Christmas things are favoured.
 * A price limit and lowest price first are candidates too. The best are offered, at most one of each kind, at most MAX.
 * Counted with three small queries when the search page is built (search pages are cached).
 */
class OOBJ_SEARCH_Suggestions {

	const MAX = 5;

	/**
	 * Fewer results than this need no narrowing.
	 */
	const MIN_RESULTS = 6;

	/**
	 * A suggestion must find at least this many products.
	 */
	const MIN_FOUND = 3;

	/**
	 * The share of the results a suggestion should ideally keep.
	 */
	const IDEAL_SHARE = 0.3;

	/**
	 * Prices to offer "under" (the shop's prices are mostly under $15).
	 */
	const PRICES = array( 5, 8, 10, 15, 20 );

	/**
	 * Distinctiveness counts up to this many times as common as in the whole shop.
	 */
	const MAX_LIFT = 4;

	/**
	 * How much more an in-season value scores.
	 */
	const SEASON_BOOST = 3;

	/**
	 * Fixed weights for the kinds that aren't facts: a price limit is a strong showcase, lowest price first a fallback.
	 */
	const PRICE_WEIGHT = 2.5;

	const SORT_WEIGHT = 0.6;

	/**
	 * Reads queries.
	 *
	 * @var OOBJ_SEARCH_Query_Parser
	 */
	private $parser;

	/**
	 * The whole shop's facts.
	 *
	 * @var OOBJ_SEARCH_Catalogue
	 */
	private $catalogue;

	/**
	 * The date seasons are judged by.
	 *
	 * @var DateTimeInterface
	 */
	private $today;

	/**
	 * Counts already made this request.
	 *
	 * @var array<string, array>
	 */
	private $counted = array();

	public function __construct( OOBJ_SEARCH_Query_Parser $parser, OOBJ_SEARCH_Catalogue $catalogue, ?DateTimeInterface $today = null ) {
		$this->parser    = $parser;
		$this->catalogue = $catalogue;
		$this->today     = $today ?? current_datetime();
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
		if ( $total < self::MIN_RESULTS ) {
			return array();
		}

		$candidates = array_merge(
			$this->facts( $query, $this->facts_of( $ids ), $total ),
			$this->seasonal_categories( $query, $ids, $total ),
			array_filter( array( $this->price( $query, $ids, $total ), $this->sort( $query, $total ) ) )
		);
		usort( $candidates, fn( $a, $b ) => array( $b['score'], $a['text'] ) <=> array( $a['score'], $b['text'] ) );

		$chosen = array();
		foreach ( $candidates as $candidate ) {
			if ( isset( $chosen[ $candidate['kind'] ] ) ) {
				continue;
			}
			unset( $candidate['score'] );
			// An empty search (the last part removed) gets suggestions too: "titanium", "under $10".
			$candidate['text']            = trim( $candidate['text'] );
			$chosen[ $candidate['kind'] ] = $candidate;
			if ( count( $chosen ) === self::MAX ) {
				break;
			}
		}
		return array_values( $chosen );
	}

	/**
	 * The query with a value's words added where a person would put them: before it ("titanium nose ring"), after it
	 * for a noun ("septum clicker") or when it starts with a theme or colour ("spooky black"), and before a price at its
	 * end ("pink belly bars under $10").
	 *
	 * @param OOBJ_SEARCH_Query $query What was searched.
	 * @param string            $facet The value's facet.
	 * @param string            $value The value.
	 */
	public static function compose( OOBJ_SEARCH_Query $query, string $facet, string $value ): string {
		$base = $query->normalised;
		$tail = '';
		$last = end( $query->parts );
		if ( $last && 'price' === $last['kind'] && str_ends_with( $base, ' ' . $last['words'] ) ) {
			$base = substr( $base, 0, -strlen( $last['words'] ) - 1 );
			$tail = ' ' . $last['words'];
		}
		$words = OOBJ_SEARCH_Vocabulary::phrase( $facet, $value );
		$first = reset( $query->parts );
		$after = in_array( $facet . ':' . $value, self::NOUNS, true ) || ( $first && in_array( $first['kind'], array( 'theme', 'colour' ), true ) && 0 === strpos( $base, $first['words'] ) );
		return ( $after ? $base . ' ' . $words : $words . ' ' . $base ) . $tail;
	}

	/**
	 * Values that read as nouns, so they go after the search ("septum clicker").
	 */
	const NOUNS = array( 'fitting:clicker' );

	/**
	 * How well a suggestion that keeps this share of the results fits: 1 at IDEAL_SHARE, less either side.
	 *
	 * @param float $share A share of the results, 0 to 1.
	 */
	private static function fit( float $share ): float {
		return max( 0.05, 1 - abs( $share - self::IDEAL_SHARE ) / ( 1 - self::IDEAL_SHARE ) );
	}

	/**
	 * How much shoppers ask for something, as a weight from 1 up.
	 *
	 * @param int $demand OOBJ_SEARCH_Vocabulary::DEMAND's figure.
	 */
	private static function demand_weight( int $demand ): float {
		return 1 + log( 1 + $demand ) / 2;
	}

	/**
	 * Whether a facet value is in season.
	 *
	 * @param string $facet A facet.
	 * @param string $value Its value.
	 */
	private function in_season( string $facet, string $value ): bool {
		foreach ( OOBJ_SEARCH_Vocabulary::seasons( $this->today ) as $season ) {
			if ( OOBJ_SEARCH_Vocabulary::SEASONS[ $season ]['facet'] === $facet && OOBJ_SEARCH_Vocabulary::SEASONS[ $season ]['value'] === $value ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Every fact value the results have that the query doesn't name, scored, as "<value> <query>".
	 *
	 * @param OOBJ_SEARCH_Query                 $query  What was searched.
	 * @param array<string, array<string, int>> $counts Fact values among the results.
	 * @param int                               $total  How many results.
	 * @return array<int, array{text: string, kind: string, count: int, score: float}>
	 */
	private function facts( OOBJ_SEARCH_Query $query, array $counts, int $total ): array {
		$catalogue  = $this->catalogue->counts();
		$candidates = array();
		foreach ( $counts as $facet => $values ) {
			if ( ! empty( $query->facets[ $facet ] ) ) {
				continue;
			}
			foreach ( $values as $value => $count ) {
				if ( $count < self::MIN_FOUND || $count >= $total ) {
					continue;
				}
				$text = self::compose( $query, $facet, (string) $value );
				if ( ! in_array( (string) $value, $this->parser->parse( $text )->facets[ $facet ] ?? array(), true ) ) {
					continue;
				}
				$share   = $count / $total;
				$overall = ( $catalogue['facts'][ $facet ][ $value ] ?? $count ) / max( 1, $catalogue['total'] );
				$lift    = min( self::MAX_LIFT, $share / max( $overall, 0.001 ) );

				$candidates[] = array(
					'text'  => $text,
					'kind'  => $facet,
					'count' => $count,
					'score' => self::fit( $share ) * sqrt( $lift ) * self::demand_weight( (int) ( OOBJ_SEARCH_Vocabulary::DEMAND[ $facet ][ $value ] ?? 0 ) ) * ( $this->in_season( $facet, (string) $value ) ? self::SEASON_BOOST : 1 ),
				);
			}
		}//end foreach
		return $candidates;
	}

	/**
	 * In season, a seasonal category the results have ("christmas earrings"), when the query names no category.
	 *
	 * @param OOBJ_SEARCH_Query $query What was searched.
	 * @param int[]             $ids   The results.
	 * @param int               $total How many.
	 * @return array<int, array{text: string, kind: string, count: int, score: float}>
	 */
	private function seasonal_categories( OOBJ_SEARCH_Query $query, array $ids, int $total ): array {
		$candidates = array();
		foreach ( OOBJ_SEARCH_Vocabulary::seasons( $this->today ) as $season ) {
			$season = OOBJ_SEARCH_Vocabulary::SEASONS[ $season ];
			if ( 'category' !== $season['facet'] || ! empty( $query->facets['category'] ) ) {
				continue;
			}
			$term  = get_term_by( 'slug', $season['value'], 'product_cat' );
			$count = $term ? count( array_intersect( $ids, get_objects_in_term( (int) $term->term_id, 'product_cat' ) ) ) : 0;
			if ( $count >= self::MIN_FOUND && $count < $total ) {
				$candidates[] = array(
					'text'  => self::compose( $query, 'category', $season['value'] ),
					'kind'  => 'season',
					'count' => $count,
					'score' => self::fit( $count / $total ) * self::SEASON_BOOST * self::PRICE_WEIGHT,
				);
			}
		}
		return $candidates;
	}

	/**
	 * The query with a price limit ("nose ring under $10"): the lowest of PRICES that keeps a fair share.
	 *
	 * @param OOBJ_SEARCH_Query $query What was searched.
	 * @param int[]             $ids   The products it found.
	 * @param int               $total How many.
	 * @return array{text: string, kind: string, count: int, score: float}|null
	 */
	private function price( OOBJ_SEARCH_Query $query, array $ids, int $total ): ?array {
		if ( null !== $query->min_price || null !== $query->max_price ) {
			return null;
		}
		$under = $this->prices_of( $ids );
		foreach ( self::PRICES as $price ) {
			$count = $under[ $price ];
			if ( $count >= max( self::MIN_FOUND, (int) ceil( $total / 5 ) ) && $count < $total ) {
				return array(
					'text'  => $query->normalised . ' under $' . $price,
					'kind'  => 'price',
					'count' => $count,
					'score' => self::fit( $count / $total ) * self::PRICE_WEIGHT,
				);
			}
		}
		return null;
	}

	/**
	 * The query lowest price first ("nose ring lowest price first"), when it isn't already in an order. Shown to
	 * shoppers, so never "cheap" or "cheapest", though the search understands them.
	 *
	 * @param OOBJ_SEARCH_Query $query What was searched.
	 * @param int               $total How many products it found.
	 * @return array{text: string, kind: string, count: int, score: float}|null
	 */
	private function sort( OOBJ_SEARCH_Query $query, int $total ): ?array {
		if ( null !== $query->sort ) {
			return null;
		}
		return array(
			'text'  => trim( $query->normalised . ' lowest price first' ),
			'kind'  => 'sort',
			'count' => $total,
			'score' => self::SORT_WEIGHT,
		);
	}

	/**
	 * How many of the products have each fact, by facet, most first. Kept for the request: the results page's
	 * "Search by" asks for the same products.
	 *
	 * @param int[] $ids Products.
	 * @return array<string, array<string, int>>
	 */
	public function facts_of( array $ids ): array {
		$key = 'facts:' . md5( implode( ',', $ids ) );
		if ( ! isset( $this->counted[ $key ] ) ) {
			$this->counted[ $key ] = $this->count_facts( $ids );
		}
		return $this->counted[ $key ];
	}

	/**
	 * How many of the products have a price up to each of PRICES (any variation), kept for the request.
	 *
	 * @param int[] $ids Products.
	 * @return array<int, int>
	 */
	public function prices_of( array $ids ): array {
		$key = 'prices:' . md5( implode( ',', $ids ) );
		if ( ! isset( $this->counted[ $key ] ) ) {
			$this->counted[ $key ] = $this->count_prices( $ids );
		}
		return $this->counted[ $key ];
	}

	/**
	 * How many of the products have each fact, by facet.
	 *
	 * @param int[] $ids Products.
	 * @return array<string, array<string, int>>
	 */
	private function count_facts( array $ids ): array {
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
		return OOBJ_SEARCH_Catalogue::by_facet( $rows );
	}

	/**
	 * How many of the products have a price up to each of PRICES (any variation).
	 *
	 * @param int[] $ids Products.
	 * @return array<int, int>
	 */
	private function count_prices( array $ids ): array {
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
