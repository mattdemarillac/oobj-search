<?php
/**
 * Base test case: categories and products (simple and variable, with prices and attributes), and the shop's product
 * search run as a visitor's request.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

abstract class OOBJ_SEARCH_TestCase extends WP_UnitTestCase {

	/**
	 * A product category, by slug, under a parent's slug if given; made once.
	 *
	 * @param string $slug        The category's slug.
	 * @param string $parent_slug The parent's slug.
	 */
	protected function category( string $slug, string $parent_slug = '' ): int {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$args = array( 'slug' => $slug );
		if ( '' !== $parent_slug ) {
			$args['parent'] = $this->category( $parent_slug );
		}
		return (int) wp_insert_term( ucfirst( str_replace( '-', ' ', $slug ) ), 'product_cat', $args )['term_id'];
	}

	/**
	 * A published simple product. Its facts are worked out as it's saved.
	 *
	 * @param string   $name       Its name.
	 * @param float    $price      Its price.
	 * @param string[] $categories Category slugs ("parent/child" makes the child under the parent).
	 * @param array    $props      Other properties (sku, description, catalog_visibility …).
	 */
	protected function product( string $name, float $price, array $categories = array(), array $props = array() ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_props(
			array_merge(
				array(
					'name'          => $name,
					'regular_price' => (string) $price,
					'status'        => 'publish',
					'category_ids'  => $this->categories( $categories ),
				),
				$props
			)
		);
		$product->save();
		return $product;
	}

	/**
	 * A published variable product with one variation per price, its "Colour / size" attribute holding the values.
	 *
	 * @param string                $name       Its name.
	 * @param array<string, float>  $variations Attribute value => price.
	 * @param string[]              $categories Category slugs.
	 */
	protected function variable_product( string $name, array $variations, array $categories = array() ): WC_Product_Variable {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Colour / size' );
		$attribute->set_options( array_keys( $variations ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product = new WC_Product_Variable();
		$product->set_props(
			array(
				'name'         => $name,
				'status'       => 'publish',
				'category_ids' => $this->categories( $categories ),
			)
		);
		$product->set_attributes( array( $attribute ) );
		$product->save();

		foreach ( $variations as $value => $price ) {
			$variation = new WC_Product_Variation();
			$variation->set_props(
				array(
					'parent_id'     => $product->get_id(),
					'regular_price' => (string) $price,
					'status'        => 'publish',
				)
			);
			$variation->set_attributes( array( sanitize_title( 'Colour / size' ) => $value ) );
			$variation->save();
		}
		WC_Product_Variable::sync( $product->get_id() );
		wc_update_product_lookup_tables_column( 'min_max_price' );
		return wc_get_product( $product->get_id() );
	}

	/**
	 * Category IDs for slugs, made as needed.
	 *
	 * @param string[] $slugs Slugs, "parent/child" for a child.
	 * @return int[]
	 */
	private function categories( array $slugs ): array {
		return array_map(
			function ( $path ) {
				$parts = explode( '/', $path );
				return $this->category( end( $parts ), 2 === count( $parts ) ? $parts[0] : '' );
			},
			$slugs
		);
	}

	/**
	 * The shop's product search for a query, as a visitor's request: the main query, after it ran.
	 *
	 * @param string               $search The query.
	 * @param array<string, mixed> $more   More query parameters (orderby …).
	 */
	protected function search( string $search, array $more = array() ): WP_Query {
		$_GET = array_merge(
			array(
				's'         => $search,
				'post_type' => 'product',
			),
			$more
		);
		$this->go_to( add_query_arg( array_map( 'rawurlencode', $_GET ), home_url( '/' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the test's own request.
		return $GLOBALS['wp_query'];
	}

	/**
	 * The names of the products a query found, in order.
	 *
	 * @param WP_Query $query The query.
	 * @return string[]
	 */
	protected function names( WP_Query $query ): array {
		return array_map( fn( $post ) => get_the_title( $post ), $query->posts );
	}

	public function tear_down() {
		$_GET = array();
		parent::tear_down();
	}
}
