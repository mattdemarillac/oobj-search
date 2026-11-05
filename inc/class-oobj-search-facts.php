<?php
/**
 * Class OOBJ_SEARCH_Facts.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Each product's facts (OOBJ_SEARCH_Product_Facts: material, fitting, colour, theme, gauge, size) as terms of a hidden
 * taxonomy, so a search filters by them with an ordinary taxonomy query, kept in the object cache like categories.
 *
 * A product's facts are worked out again when it's saved (or one of its variations is). When the way facts are worked
 * out changes (the vocabulary or the extraction, VERSION below and the vocabulary's contents), every product's are,
 * in the background, BATCH products at a time (Action Scheduler). Per product, not per variation: one relationship row
 * per fact, a few per product.
 */
class OOBJ_SEARCH_Facts implements OOBJ_SEARCH_Module {

	const TAXONOMY = 'oobj_search_fact';

	/**
	 * Bump when OOBJ_SEARCH_Product_Facts changes what it extracts.
	 */
	const VERSION = '1';

	const VERSION_OPTION = 'oobj_search_facts_version';

	const BATCH_HOOK = 'oobj_search_index_batch';

	const BATCH = 200;

	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ), 5 );
		add_action( 'init', array( $this, 'maybe_rebuild' ), 20 );
		add_action( 'woocommerce_new_product', array( $this, 'index' ) );
		add_action( 'woocommerce_update_product', array( $this, 'index' ) );
		add_action( 'woocommerce_new_product_variation', array( $this, 'index_parent' ) );
		add_action( 'woocommerce_update_product_variation', array( $this, 'index_parent' ) );
		add_action( self::BATCH_HOOK, array( $this, 'index_batch' ) );
	}

	public function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			'product',
			array(
				'public'            => false,
				'show_ui'           => false,
				'show_in_rest'      => false,
				'show_in_nav_menus' => false,
				'show_admin_column' => false,
				'query_var'         => false,
				'rewrite'           => false,
				'hierarchical'      => false,
			)
		);
	}

	/**
	 * The term slug of a fact: material titanium is "material-titanium", gauge 1.2 is "gauge-1-2".
	 *
	 * @param string $facet A fact facet.
	 * @param string $value Its value.
	 */
	public static function slug( string $facet, string $value ): string {
		return sanitize_title( $facet . '-' . str_replace( '.', '-', $value ) );
	}

	/**
	 * What OOBJ_SEARCH_Product_Facts reads from a product: its name, descriptions, categories (slug paths), attribute
	 * values (all variations') and tags.
	 *
	 * @param WC_Product $product A product.
	 * @return array<string, mixed>
	 */
	public static function product_data( WC_Product $product ): array {
		$categories = array();
		foreach ( wp_get_post_terms( $product->get_id(), 'product_cat' ) as $term ) {
			$path = array( $term->slug );
			foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $ancestor ) {
				array_unshift( $path, (string) get_term_field( 'slug', $ancestor, 'product_cat' ) );
			}
			$categories[] = implode( '/', $path );
		}

		$attributes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			$attributes[ $attribute->get_name() ] = $attribute->is_taxonomy()
				? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
				: $attribute->get_options();
		}

		return array(
			'name'              => $product->get_name(),
			'short_description' => wp_strip_all_tags( $product->get_short_description() ),
			'description'       => wp_strip_all_tags( $product->get_description() ),
			'categories'        => $categories,
			'attributes'        => $attributes,
			'tags'              => wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) ),
		);
	}

	/**
	 * Works out a product's facts again and keeps them.
	 *
	 * @param int $product_id A product.
	 * @return string[] Its facts' slugs.
	 */
	public function index( $product_id ): array {
		$product = wc_get_product( (int) $product_id );
		if ( ! $product || $product->is_type( 'variation' ) ) {
			return array();
		}

		$slugs = array();
		foreach ( OOBJ_SEARCH_Product_Facts::facts( self::product_data( $product ) ) as $facet => $values ) {
			foreach ( $values as $value ) {
				$slug = self::slug( $facet, (string) $value );
				if ( ! term_exists( $slug, self::TAXONOMY ) ) {
					wp_insert_term( "$facet: $value", self::TAXONOMY, array( 'slug' => $slug ) );
				}
				$slugs[] = $slug;
			}
		}
		wp_set_object_terms( $product->get_id(), $slugs, self::TAXONOMY );
		return $slugs;
	}

	/**
	 * A variation was saved: its product's facts include its attributes.
	 *
	 * @param int $variation_id A variation.
	 */
	public function index_parent( $variation_id ): void {
		$parent = wp_get_post_parent_id( (int) $variation_id );
		if ( $parent ) {
			$this->index( $parent );
		}
	}

	/**
	 * What the facts are worked out with: VERSION and the vocabulary's contents.
	 */
	public static function version(): string {
		return self::VERSION . '-' . substr( md5( (string) wp_json_encode( OOBJ_SEARCH_Vocabulary::CONCEPTS ) ), 0, 8 );
	}

	/**
	 * Queues working out every product's facts when the way they're worked out changed.
	 */
	public function maybe_rebuild(): void {
		if ( get_option( self::VERSION_OPTION ) === self::version() ) {
			return;
		}
		update_option( self::VERSION_OPTION, self::version() );
		$this->rebuild();
	}

	/**
	 * Queues working out every product's facts, from the first.
	 */
	public function rebuild(): void {
		as_unschedule_all_actions( self::BATCH_HOOK );
		as_enqueue_async_action( self::BATCH_HOOK, array( 0 ), 'oobj-search' );
	}

	/**
	 * Works out the facts of BATCH products after the one with this ID, then queues the next batch.
	 *
	 * @param int $after A product ID; 0 for the first batch.
	 * @return int How many products it did.
	 */
	public function index_batch( $after = 0 ): int {
		$ids = $this->product_ids( (int) $after, self::BATCH );
		foreach ( $ids as $id ) {
			$this->index( $id );
		}
		if ( count( $ids ) === self::BATCH ) {
			as_enqueue_async_action( self::BATCH_HOOK, array( (int) end( $ids ) ), 'oobj-search' );
		}
		return count( $ids );
	}

	/**
	 * Product IDs after one, in order.
	 *
	 * @param int $after A product ID.
	 * @param int $limit At most this many.
	 * @return int[]
	 */
	public function product_ids( int $after, int $limit ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish', 'private', 'draft', 'pending', 'future') AND ID > %d ORDER BY ID LIMIT %d", $after, $limit ) ) );
	}

	/**
	 * How many products have a fact of each facet, and how many facts there are in all.
	 *
	 * @return array{products: int, facets: array<string, int>, relationships: int, terms: int}
	 */
	public function coverage(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$products = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" );
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SUBSTRING_INDEX(t.slug, '-', 1) AS facet, COUNT(DISTINCT tr.object_id) AS products, COUNT(*) AS relationships
				FROM {$wpdb->term_relationships} tr
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_status = 'publish'
				WHERE tt.taxonomy = %s GROUP BY facet",
				self::TAXONOMY
			)
		);
		$terms    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", self::TAXONOMY ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$facets = array_fill_keys( OOBJ_SEARCH_Vocabulary::FACT_FACETS, 0 );
		$count  = 0;
		foreach ( $rows as $row ) {
			$facets[ $row->facet ] = (int) $row->products;
			$count                += (int) $row->relationships;
		}
		return array(
			'products'      => $products,
			'facets'        => $facets,
			'relationships' => $count,
			'terms'         => $terms,
		);
	}
}
