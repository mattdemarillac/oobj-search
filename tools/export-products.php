<?php
/**
 * Every published product as JSON, in the shape OOBJ_SEARCH_Product_Facts reads (and tests/query/fixtures/products.json
 * holds a sample of): to check facts across the whole shop, or to pick new fixtures. See docs/tuning.md.
 *
 * Usage (WordPress loaded): wp eval-file tools/export-products.php > products.json
 *
 * @package oobj-search
 */

/**
 * The products, as Product_Facts reads them.
 *
 * @return array<int, array<string, mixed>>
 */
function oobj_search_export_products(): array {
	$out = array();
	foreach ( get_posts(
		array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	) as $id ) {
		$product    = wc_get_product( $id );
		$categories = array();
		foreach ( wp_get_post_terms( $id, 'product_cat' ) as $term ) {
			$path   = array( $term->slug );
			$parent = $term->parent;
			while ( $parent ) {
				$up = get_term( $parent );
				array_unshift( $path, $up->slug );
				$parent = $up->parent;
			}
			$categories[] = implode( '/', $path );
		}
		$attributes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			$attributes[ $attribute->get_name() ] = $attribute->is_taxonomy() ? wc_get_product_terms( $id, $attribute->get_name(), array( 'fields' => 'names' ) ) : $attribute->get_options();
		}
		$out[] = array(
			'id'                => $id,
			'name'              => $product->get_name(),
			'sku'               => $product->get_sku(),
			'categories'        => $categories,
			'attributes'        => $attributes,
			'tags'              => wp_get_post_terms( $id, 'product_tag', array( 'fields' => 'names' ) ),
			'short_description' => wp_strip_all_tags( $product->get_short_description() ),
			'description'       => mb_substr( wp_strip_all_tags( $product->get_description() ), 0, 1500 ),
			'price_min'         => $product->is_type( 'variable' ) ? (float) $product->get_variation_price( 'min' ) : (float) $product->get_price(),
			'price_max'         => $product->is_type( 'variable' ) ? (float) $product->get_variation_price( 'max' ) : (float) $product->get_price(),
		);
	}//end foreach
	return $out;
}

echo wp_json_encode( oobj_search_export_products(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
