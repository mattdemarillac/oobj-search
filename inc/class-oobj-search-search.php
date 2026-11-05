<?php
/**
 * Class OOBJ_SEARCH_Search.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * The shop's product search, understood (OOBJ_SEARCH_Query_Parser): on a product search page (?s=…&post_type=product),
 * the main query is narrowed by what the query says: categories, product facts (OOBJ_SEARCH_Facts), a price range
 * (WooCommerce's product lookup table, so a variable product counts when any variation is in range), and ordered by
 * price when asked ("cheap"). Only the words left over are searched for in product text, as WordPress does. WooCommerce's
 * product collection block shows a copy of the main query, so its products, count and pagination all follow.
 *
 * When nothing matches everything, the parts least likely to matter are let go one at a time (RELAX_ORDER) until
 * something does, and current() says which, for the page to tell the shopper. A query that's only a product's number
 * (SKU or ID) goes to that product. The page heading still shows what was typed. A search that finds nothing even then
 * is logged (WooCommerce log, source oobj-search), so the vocabulary can learn from it.
 */
class OOBJ_SEARCH_Search implements OOBJ_SEARCH_Module {

	const LOG_SOURCE = 'oobj-search';

	/**
	 * What to let go first when nothing matches: free words, then the facts least likely to be the point of the search,
	 * then the price, and the category last.
	 */
	const RELAX_ORDER = array( 'keywords', 'theme', 'colour', 'size', 'gauge', 'fitting', 'material', 'sort', 'price', 'category' );

	/**
	 * Reads queries.
	 *
	 * @var OOBJ_SEARCH_Query_Parser
	 */
	private $parser;

	/**
	 * This request's search: the query, and what was let go to find something.
	 *
	 * @var array{query: OOBJ_SEARCH_Query, relaxed: string[], found: bool}|null
	 */
	private $current = null;

	public function __construct( OOBJ_SEARCH_Query_Parser $parser ) {
		$this->parser = $parser;
	}

	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'narrow' ) );
		add_action( 'template_redirect', array( $this, 'go_to_product' ) );
		add_filter( 'get_search_query', array( $this, 'typed' ) );
		add_filter( 'posts_clauses', array( $this, 'clauses' ), 10, 2 );
	}

	/**
	 * This request's product search: query (understood or not), relaxed (the kinds let go) and found (anything at all,
	 * once understood; true for a plain search).
	 *
	 * @return array{query: OOBJ_SEARCH_Query, relaxed: string[], found: bool}|null
	 */
	public function current(): ?array {
		return $this->current;
	}

	/**
	 * Whether a query is the shop's product search: the main query of a front-end search for products.
	 *
	 * @param WP_Query $query A query.
	 */
	public static function is_product_search( WP_Query $query ): bool {
		$post_type = (array) $query->get( 'post_type' );
		return ! is_admin() && $query->is_main_query() && $query->is_search() && in_array( 'product', $post_type, true ) && 1 === count( $post_type );
	}

	/**
	 * Narrows the shop's product search by what its query means.
	 *
	 * @param WP_Query $query The query about to run.
	 */
	public function narrow( WP_Query $query ): void {
		if ( $query->is_main_query() ) {
			$this->current = null;
		}
		if ( ! self::is_product_search( $query ) ) {
			return;
		}

		$understood    = $this->parser->parse( (string) $query->get( 's' ) );
		$this->current = array(
			'query'   => $understood,
			'relaxed' => array(),
			'found'   => true,
		);
		if ( ! $understood->understood() || null !== $understood->product_number ) {
			return;
		}

		$resolved      = $this->resolve( $understood, $query->query_vars );
		$this->current = array(
			'query'   => $understood,
			'relaxed' => $resolved['relaxed'],
			'found'   => $resolved['found'],
		);
		if ( ! $resolved['found'] && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->notice( 'No products for: ' . $understood->text, array( 'source' => self::LOG_SOURCE ) );
		}

		foreach ( $resolved['args'] as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	/**
	 * The query arguments for what a query means, letting go of parts (RELAX_ORDER) until something matches.
	 *
	 * @param OOBJ_SEARCH_Query    $understood The query, understood.
	 * @param array<string, mixed> $query_vars The search's own query vars (post type, WooCommerce's visibility
	 *                                         conditions), which stay.
	 * @return array{args: array<string, mixed>, relaxed: string[], found: bool}
	 */
	public function resolve( OOBJ_SEARCH_Query $understood, array $query_vars ): array {
		$base    = array_filter( (array) ( $query_vars['tax_query'] ?? array() ) );
		$relaxed = array();
		$args    = $this->args( $understood, $relaxed, $base );
		$found   = $this->finds( $args, $query_vars );
		foreach ( self::RELAX_ORDER as $kind ) {
			if ( $found ) {
				break;
			}
			if ( ! $this->has( $understood, $kind ) ) {
				continue;
			}
			$relaxed[] = $kind;
			$args      = $this->args( $understood, $relaxed, $base );
			$found     = $this->finds( $args, $query_vars );
		}

		// Something matched: put back each part let go on the way that still leaves something ("titanium spiral under
		// $5" leaves out the words and the price, but stays titanium).
		foreach ( $found ? array_slice( $relaxed, 0, -1 ) : array() as $kind ) {
			$without = array_values( array_diff( $relaxed, array( $kind ) ) );
			$fewer   = $this->args( $understood, $without, $base );
			if ( $this->finds( $fewer, $query_vars ) ) {
				$relaxed = $without;
				$args    = $fewer;
			}
		}
		return array(
			'args'    => $args,
			'relaxed' => $relaxed,
			'found'   => $found,
		);
	}

	/**
	 * Whether the query has a part of a kind: words, a facet, a price or an order.
	 *
	 * @param OOBJ_SEARCH_Query $query The query.
	 * @param string            $kind  'keywords', a facet, 'price' or 'sort'.
	 */
	private function has( OOBJ_SEARCH_Query $query, string $kind ): bool {
		if ( 'keywords' === $kind ) {
			return '' !== $query->keywords;
		}
		if ( 'price' === $kind ) {
			return null !== $query->min_price || null !== $query->max_price;
		}
		if ( 'sort' === $kind ) {
			return null !== $query->sort;
		}
		return ! empty( $query->facets[ $kind ] );
	}

	/**
	 * The main query's arguments for what the query means, without the kinds let go.
	 *
	 * @param OOBJ_SEARCH_Query    $query   The query.
	 * @param string[]             $relaxed Kinds let go.
	 * @param array<string, mixed> $base    The main query's own taxonomy conditions.
	 * @return array<string, mixed>
	 */
	private function args( OOBJ_SEARCH_Query $query, array $relaxed, array $base ): array {
		unset( $base['relation'] );
		$tax_query = array_merge( array( 'relation' => 'AND' ), $base );
		// Products hidden from search stay hidden (WooCommerce adds this to shop pages, not to this search).
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => array( 'exclude-from-search' ),
			'operator' => 'NOT IN',
		);
		foreach ( $query->facets as $facet => $values ) {
			if ( in_array( $facet, $relaxed, true ) ) {
				continue;
			}
			// Categories say both where jewellery is worn and what it is ("horseshoe nose ring"): each one named must
			// match. A concept's own alternatives ("daith": two categories) are either.
			if ( 'category' === $facet ) {
				foreach ( $values as $value ) {
					$tax_query[] = array(
						'taxonomy'         => 'product_cat',
						'field'            => 'slug',
						'terms'            => array_map( 'basename', explode( '|', $value ) ),
						'include_children' => true,
					);
				}
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => OOBJ_SEARCH_Facts::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array_map( fn( $value ) => OOBJ_SEARCH_Facts::slug( $facet, $value ), $values ),
			);
		}//end foreach

		$price = in_array( 'price', $relaxed, true ) ? array() : array_filter(
			array(
				'min' => $query->min_price,
				'max' => $query->max_price,
			),
			fn( $limit ) => null !== $limit
		);

		return array(
			's'                 => in_array( 'keywords', $relaxed, true ) ? '' : $query->keywords,
			'tax_query'         => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'oobj_search_price' => $price,
			'oobj_search_sort'  => in_array( 'sort', $relaxed, true ) || $this->sorted_by_shopper() ? null : $query->sort,
		);
	}

	/**
	 * Whether the shopper chose an order (WooCommerce's sorting), which a "cheap" in the query doesn't override.
	 */
	private function sorted_by_shopper(): bool {
		return ! empty( $_GET['orderby'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Whether anything matches: a quick count of the query with these arguments.
	 *
	 * @param array<string, mixed> $args       Arguments from args().
	 * @param array<string, mixed> $query_vars The search's other arguments (post type).
	 */
	private function finds( array $args, array $query_vars ): bool {
		$probe = new WP_Query(
			array_merge(
				$query_vars,
				$args,
				array(
					'fields'                 => 'ids',
					'posts_per_page'         => 1,
					'paged'                  => 1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'oobj_search_probe'      => true,
				)
			)
		);
		return array() !== $probe->posts;
	}

	/**
	 * A query that's only a product's number goes to that product: its SKU first, then its ID.
	 */
	public function go_to_product(): void {
		global $wp_query;
		if ( ! $wp_query instanceof WP_Query || ! self::is_product_search( $wp_query ) ) {
			return;
		}
		$number = $this->parser->parse( (string) $wp_query->get( 's' ) )->product_number;
		if ( null === $number ) {
			return;
		}
		$id      = wc_get_product_id_by_sku( $number );
		$product = wc_get_product( $id ? $id : (int) $number );
		if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() ) {
			return;
		}
		$product = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		wp_safe_redirect( (string) get_permalink( $product->get_id() ), 302, 'Out Of Body Search' );
		$this->stop();
	}

	/**
	 * Ends the request after a redirect (replaced in the tests).
	 *
	 * @codeCoverageIgnore
	 */
	protected function stop(): void {
		exit;
	}

	/**
	 * The search as typed, for the page heading and the search box, once the main query searches only its leftover words.
	 *
	 * @param string $search The search WordPress has.
	 */
	public function typed( $search ): string {
		return null === $this->current || ! $this->current['query']->understood() ? (string) $search : $this->current['query']->text;
	}

	/**
	 * The price range and price order (oobj_search_price, oobj_search_sort) as SQL, from WooCommerce's product lookup
	 * table: its min and max price cover every variation, so "under $10" finds a product with any variation up to $10.
	 *
	 * @param array<string, string> $clauses The query's SQL clauses.
	 * @param WP_Query              $query   The query.
	 * @return array<string, string>
	 */
	public function clauses( $clauses, $query ): array {
		$clauses = (array) $clauses;
		$price   = (array) $query->get( 'oobj_search_price' );
		$sort    = $query->get( 'oobj_search_sort' );
		if ( ! $price && ! in_array( $sort, array( 'price', 'price-desc' ), true ) ) {
			return $clauses;
		}

		global $wpdb;
		$clauses['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} oobj_search_lookup ON oobj_search_lookup.product_id = {$wpdb->posts}.ID";
		if ( isset( $price['min'] ) ) {
			$clauses['where'] .= $wpdb->prepare( ' AND oobj_search_lookup.max_price >= %f', (float) $price['min'] );
		}
		if ( isset( $price['max'] ) ) {
			$clauses['where'] .= $wpdb->prepare( ' AND oobj_search_lookup.min_price <= %f', (float) $price['max'] );
		}
		if ( 'price' === $sort ) {
			$clauses['orderby'] = "oobj_search_lookup.min_price ASC, {$wpdb->posts}.ID DESC";
		} elseif ( 'price-desc' === $sort ) {
			$clauses['orderby'] = "oobj_search_lookup.max_price DESC, {$wpdb->posts}.ID DESC";
		}
		return $clauses;
	}
}
