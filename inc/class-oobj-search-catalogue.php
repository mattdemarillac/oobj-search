<?php
/**
 * Class OOBJ_SEARCH_Catalogue.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * The whole catalogue's facts: how many published products have each fact value. Suggestions compare a search's
 * results with it (what's distinctive about them), and the search panel offers the values the shop has. One query,
 * kept CACHE_TTL (a transient, in the object cache), and worked out again sooner when the way facts are worked out
 * changes (OOBJ_SEARCH_Facts::version()). Not kept while products' facts are being worked out in the background
 * (they'd be counted part done), and forgotten when that finishes (OOBJ_SEARCH_Facts::index_batch()).
 */
class OOBJ_SEARCH_Catalogue {

	const CACHE_KEY = 'oobj_search_catalogue_2';

	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Published products, and how many have each fact value, by facet, most first.
	 *
	 * @return array{total: int, facts: array<string, array<string, int>>}
	 */
	public function counts(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ( $cached['version'] ?? '' ) === OOBJ_SEARCH_Facts::version() ) {
			return $cached['counts'];
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.name, COUNT(*) AS products FROM {$wpdb->term_relationships} tr
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_status = 'publish'
				WHERE tt.taxonomy = %s GROUP BY t.term_id ORDER BY products DESC, t.name",
				OOBJ_SEARCH_Facts::TAXONOMY
			)
		);
		// phpcs:enable

		$counts = array(
			'total' => $total,
			'facts' => self::by_facet( $rows ),
		);
		if ( OOBJ_SEARCH_Facts::indexing() ) {
			return $counts;
		}
		set_transient(
			self::CACHE_KEY,
			array(
				'version' => OOBJ_SEARCH_Facts::version(),
				'counts'  => $counts,
			),
			self::CACHE_TTL
		);
		return $counts;
	}

	/**
	 * Forgets the kept counts, so the next call works them out again.
	 */
	public static function forget(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Rows of fact term name ("material: titanium") and products, as facet => value => products.
	 *
	 * @param array<int, object> $rows Rows with name and products.
	 * @return array<string, array<string, int>>
	 */
	public static function by_facet( array $rows ): array {
		$facts = array();
		foreach ( $rows as $row ) {
			list( $facet, $value )     = array_map( 'trim', explode( ':', (string) $row->name, 2 ) + array( '', '' ) );
			$facts[ $facet ][ $value ] = (int) $row->products;
		}
		return $facts;
	}
}
