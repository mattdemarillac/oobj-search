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
