<?php
/**
 * Class OOBJ_SEARCH_Notice.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * What the search understood, above the results: each part as a link that searches again without it ("Titanium ×"),
 * and, when nothing matched all of it, which parts the results leave out. Then searches to try next
 * (OOBJ_SEARCH_Suggestions), which show what else the search understands, for plain searches too. Put in front of the product collection on
 * product search pages; a theme can place it elsewhere (html()) and turn this off (the oobj_search_show_notice filter).
 * Unstyled beyond its class names: the theme styles it.
 */
class OOBJ_SEARCH_Notice implements OOBJ_SEARCH_Module {

	/**
	 * The search.
	 *
	 * @var OOBJ_SEARCH_Search
	 */
	private $search;

	/**
	 * Searches to try next.
	 *
	 * @var OOBJ_SEARCH_Suggestions
	 */
	private $suggestions;

	public function __construct( OOBJ_SEARCH_Search $search, OOBJ_SEARCH_Suggestions $suggestions ) {
		$this->search      = $search;
		$this->suggestions = $suggestions;
	}

	public function register(): void {
		// After WooCommerce's own, which puts the store notices inside the first element of the collection's output.
		add_filter( 'render_block_woocommerce/product-collection', array( $this, 'before_results' ), 99 );
	}

	/**
	 * Puts the notice in front of the product search's results.
	 *
	 * @param string $html The product collection.
	 */
	public function before_results( $html ): string {
		if ( ! apply_filters( 'oobj_search_show_notice', true ) ) {
			return (string) $html;
		}
		return $this->html() . $html;
	}

	/**
	 * The notice, or '' on other pages and when there's nothing to say.
	 */
	public function html(): string {
		$current = $this->search->current();
		if ( null === $current || ! $current['found'] ) {
			return '';
		}

		$query = $current['query'];
		$html  = $query->understood() ? $this->understood( $query, $current['relaxed'] ) : '';
		$ids   = $this->found_ids();
		$html .= $this->suggestions_html( $this->suggestions->suggest( $query, $ids ) );
		$html .= $this->search_by( $query, $ids );
		return '' === $html ? '' : '<div class="oobj-search-notice" role="status">' . $html . '</div>';
	}

	/**
	 * What was understood, each part a link to search without it, and what the results leave out.
	 *
	 * @param OOBJ_SEARCH_Query $query   The query.
	 * @param string[]          $relaxed Kinds let go.
	 */
	private function understood( OOBJ_SEARCH_Query $query, array $relaxed ): string {
		$items = '';
		$left  = array();
		foreach ( $query->parts as $index => $part ) {
			$is_off = in_array( $part['kind'], $relaxed, true );
			if ( $is_off ) {
				$left[] = $part['label'];
			}
			$items .= sprintf(
				'<li><a class="oobj-search-notice__part%1$s" href="%2$s" aria-label="%3$s">%4$s <span aria-hidden="true">×</span></a></li>',
				$is_off ? ' oobj-search-notice__part--left-out' : '',
				esc_url( $this->search_url( $query->without( $index ) ) ),
				/* translators: %s: what the search understood, e.g. "Titanium". */
				esc_attr( sprintf( __( 'Search again without %s', 'oobj-search' ), $part['label'] ) ),
				esc_html( $part['label'] )
			);
		}
		if ( '' !== $query->keywords && in_array( 'keywords', $relaxed, true ) ) {
			/* translators: %s: words searched for. */
			$left[] = sprintf( __( '“%s”', 'oobj-search' ), $query->keywords );
		}

		$note = $left ? sprintf(
			'<p class="oobj-search-notice__left-out">%s</p>',
			/* translators: %s: the parts of the search left out, e.g. "Titanium, Under $5". */
			esc_html( sprintf( __( 'Nothing matched all of that, so these results leave out: %s.', 'oobj-search' ), implode( ', ', $left ) ) )
		) : '';

		return sprintf(
			'<p class="oobj-search-notice__label">%1$s</p><ul class="oobj-search-notice__parts">%2$s</ul>%3$s',
			esc_html__( 'Showing:', 'oobj-search' ),
			$items,
			$note
		);
	}

	/**
	 * Searches to try next, each with how many products it finds.
	 *
	 * @param array<int, array{text: string, kind: string, count: int}> $suggestions The searches.
	 */
	private function suggestions_html( array $suggestions ): string {
		if ( ! $suggestions ) {
			return '';
		}
		$items = '';
		foreach ( $suggestions as $suggestion ) {
			$items .= sprintf(
				'<li><a class="oobj-search-notice__suggestion oobj-search-notice__suggestion--%1$s" href="%2$s">%3$s <span class="oobj-search-notice__count">%4$s</span></a></li>',
				esc_attr( $suggestion['kind'] ),
				esc_url( $this->search_url( $suggestion['text'] ) ),
				esc_html( $suggestion['text'] ),
				/* translators: %d: how many products a search finds. */
				esc_html( sprintf( _n( '%d product', '%d products', $suggestion['count'], 'oobj-search' ), $suggestion['count'] ) )
			);
		}
		return sprintf(
			'<p class="oobj-search-notice__label oobj-search-notice__label--try">%1$s</p><ul class="oobj-search-notice__suggestions">%2$s</ul>',
			esc_html__( 'Try:', 'oobj-search' ),
			$items
		);
	}

	/**
	 * "Search by" for these results, as on the search panel (OOBJ_SEARCH_Examples): for each kind, what the search
	 * already has (on: a link to search without it), or the values among the results that would narrow them, each a
	 * link to the search with it added. Prices likewise.
	 *
	 * @param OOBJ_SEARCH_Query $query The search.
	 * @param int[]             $ids   Its results.
	 */
	private function search_by( OOBJ_SEARCH_Query $query, array $ids ): string {
		$total = count( $ids );
		if ( $total < OOBJ_SEARCH_Suggestions::MIN_RESULTS ) {
			return '';
		}

		$headings = array();
		$facts    = $this->suggestions->facts_of( $ids );
		$groups   = '';
		foreach ( OOBJ_SEARCH_Examples::GROUPS as $facet => $heading ) {
			$chips = '';
			if ( ! empty( $query->facets[ $facet ] ) ) {
				foreach ( $query->parts as $index => $part ) {
					if ( $facet === $part['kind'] ) {
						$chips .= $this->on( $query, $index, OOBJ_SEARCH_Examples::chip_label( $facet, $part['value'] ), OOBJ_SEARCH_Examples::chip_detail( $facet, $part['value'] ) );
					}
				}
			} else {
				foreach ( $this->narrowing( $facet, $facts[ $facet ] ?? array(), $total ) as $value => $count ) {
					$chips .= $this->link( OOBJ_SEARCH_Suggestions::compose( $query, $facet, (string) $value ), OOBJ_SEARCH_Examples::chip_label( $facet, (string) $value ), OOBJ_SEARCH_Examples::chip_detail( $facet, (string) $value ), $count );
				}
			}
			if ( '' !== $chips ) {
				$groups    .= OOBJ_SEARCH_Examples::group( $facet, $heading, $chips );
				$headings[] = strtolower( $heading );
			}
		}

		$chips = '';
		foreach ( $query->parts as $index => $part ) {
			if ( 'price' === $part['kind'] ) {
				$chips .= $this->on( $query, $index, $part['label'], '' );
			}
		}
		if ( '' === $chips ) {
			$under = $this->suggestions->prices_of( $ids );
			foreach ( OOBJ_SEARCH_Suggestions::PRICES as $price ) {
				if ( $under[ $price ] >= OOBJ_SEARCH_Suggestions::MIN_FOUND && $under[ $price ] < $total ) {
					/* translators: %d: a price in dollars. */
					$chips .= $this->link( trim( $query->normalised . ' under $' . $price ), sprintf( __( 'Under $%d', 'oobj-search' ), $price ), '', $under[ $price ] );
				}
			}
		}
		if ( '' !== $chips ) {
			$groups    .= OOBJ_SEARCH_Examples::group( 'price', 'Price', $chips );
			$headings[] = 'price';
		}

		return '' === $groups ? '' : sprintf(
			'<details class="oobj-search-notice__by"><summary class="oobj-search-notice__toggle"><span class="oobj-search-notice__toggle-label">%1$s</span> <span class="oobj-search-notice__toggle-hint">%3$s</span></summary><div class="oobj-search-notice__groups">%2$s</div></details>',
			esc_html__( 'Search by', 'oobj-search' ),
			$groups,
			esc_html( implode( ', ', $headings ) )
		);
	}

	/**
	 * A facet's values among the results that would narrow them (MIN_FOUND or more, not all), as on the search panel:
	 * gauges and sizes the most common smallest first, the rest most searched first; at most PER_GROUP.
	 *
	 * @param string             $facet  A facet.
	 * @param array<string, int> $counts Its values among the results.
	 * @param int                $total  How many results.
	 * @return array<string, int> Value => products.
	 */
	private function narrowing( string $facet, array $counts, int $total ): array {
		$counts = array_filter( $counts, fn( $count ) => $count >= OOBJ_SEARCH_Suggestions::MIN_FOUND && $count < $total );
		$values = array_map( 'strval', array_keys( $counts ) );
		if ( 'gauge' === $facet ) {
			$values = array_values( array_intersect( $values, OOBJ_SEARCH_Vocabulary::GAUGES ) );
		}
		if ( 'gauge' === $facet || 'size' === $facet ) {
			$values = OOBJ_SEARCH_Examples::smallest_first( $values, $counts );
		} else {
			$demand = OOBJ_SEARCH_Vocabulary::DEMAND[ $facet ] ?? array();
			usort( $values, fn( $a, $b ) => array( $demand[ $b ] ?? 0, $counts[ $b ] ) <=> array( $demand[ $a ] ?? 0, $counts[ $a ] ) );
			$values = array_slice( $values, 0, OOBJ_SEARCH_Examples::PER_GROUP );
		}
		$narrowing = array();
		foreach ( $values as $value ) {
			$narrowing[ $value ] = $counts[ $value ];
		}
		return $narrowing;
	}

	/**
	 * A "Search by" link to the search with something added.
	 *
	 * @param string $search The search.
	 * @param string $label  Its label.
	 * @param string $detail Its detail ("1.2mm"), or ''.
	 * @param int    $count  How many products it finds.
	 */
	private function link( string $search, string $label, string $detail, int $count ): string {
		return sprintf(
			'<li><a class="oobj-search-panel__chip" href="%1$s" aria-label="%2$s">%3$s</a></li>',
			esc_url( $this->search_url( $search ) ),
			/* translators: 1: what to add, e.g. "Titanium"; 2: how many products. */
			esc_attr( sprintf( _n( '%1$s, %2$d product', '%1$s, %2$d products', $count, 'oobj-search' ), trim( $label . ' ' . $detail ), $count ) ),
			OOBJ_SEARCH_Examples::chip_text( $label, $detail )
		);
	}

	/**
	 * A "Search by" link for something the search has: on, to search without it.
	 *
	 * @param OOBJ_SEARCH_Query $query The search.
	 * @param int               $index The part.
	 * @param string            $label Its label.
	 * @param string            $detail Its detail, or ''.
	 */
	private function on( OOBJ_SEARCH_Query $query, int $index, string $label, string $detail ): string {
		return sprintf(
			'<li><a class="oobj-search-panel__chip oobj-search-panel__chip--on" href="%1$s" aria-label="%2$s">%3$s</a></li>',
			esc_url( $this->search_url( $query->without( $index ) ) ),
			/* translators: %s: what the search has, e.g. "Titanium". */
			esc_attr( sprintf( __( 'Remove %s from the search', 'oobj-search' ), trim( $label . ' ' . $detail ) ) ),
			OOBJ_SEARCH_Examples::chip_text( $label, $detail )
		);
	}

	/**
	 * Every product the main query found, not only this page's.
	 *
	 * @return int[]
	 */
	private function found_ids(): array {
		global $wp_query;
		$found = new WP_Query(
			array_merge(
				$wp_query->query_vars,
				array(
					'fields'                 => 'ids',
					'posts_per_page'         => -1,
					'nopaging'               => true,
					'paged'                  => 1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			)
		);
		return array_map( 'intval', $found->posts );
	}

	/**
	 * The product search for a query, keeping the shopper's chosen order.
	 *
	 * @param string $search The query.
	 */
	public function search_url( string $search ): string {
		$args = array(
			's'         => $search,
			'post_type' => 'product',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		if ( '' !== $orderby ) {
			$args['orderby'] = $orderby;
		}
		return add_query_arg( array_map( 'rawurlencode', $args ), home_url( '/' ) );
	}
}
