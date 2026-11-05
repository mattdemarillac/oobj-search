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
 * and, when nothing matched all of it, which parts the results leave out. Put in front of the product collection on
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

	public function __construct( OOBJ_SEARCH_Search $search ) {
		$this->search = $search;
	}

	public function register(): void {
		add_filter( 'render_block_woocommerce/product-collection', array( $this, 'before_results' ) );
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
	 * The notice, or '' when the search wasn't understood.
	 */
	public function html(): string {
		$current = $this->search->current();
		if ( null === $current ) {
			return '';
		}

		$query   = $current['query'];
		$relaxed = $current['relaxed'];
		$items   = '';
		$left    = array();
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

		$note = '';
		if ( $current['found'] && $left ) {
			$note = sprintf(
				'<p class="oobj-search-notice__left-out">%s</p>',
				/* translators: %s: the parts of the search left out, e.g. "Titanium, Under $5". */
				esc_html( sprintf( __( 'Nothing matched all of that, so these results leave out: %s.', 'oobj-search' ), implode( ', ', $left ) ) )
			);
		}

		return sprintf(
			'<div class="oobj-search-notice" role="status"><p class="oobj-search-notice__label">%1$s</p><ul class="oobj-search-notice__parts">%2$s</ul>%3$s</div>',
			esc_html__( 'Showing:', 'oobj-search' ),
			$items,
			$note
		);
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
