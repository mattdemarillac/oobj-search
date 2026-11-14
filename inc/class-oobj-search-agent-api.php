<?php
/**
 * Class OOBJ_SEARCH_Agent_API.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Read-only product search for agents: GET /wp-json/oobj-search/v1/search runs a query through the same reader and
 * search as the shop (OOBJ_SEARCH_Query_Parser, OOBJ_SEARCH_Search::resolve), so "titanium threadless septum 16g under
 * $10" is understood, not just keyword-matched, and the response says what was understood and what was let go.
 *
 * GET /wp-json/oobj-search/v1/openapi describes it (OpenAPI 3.1), and /.well-known/openapi.json and
 * /.well-known/api-catalog (RFC 9727) point agents at it.
 */
class OOBJ_SEARCH_Agent_API implements OOBJ_SEARCH_Module {

	const REST_NAMESPACE = 'oobj-search/v1';
	const MAX_PER_PAGE   = 24;

	/**
	 * Reads queries.
	 *
	 * @var OOBJ_SEARCH_Query_Parser
	 */
	private $parser;

	/**
	 * The search.
	 *
	 * @var OOBJ_SEARCH_Search
	 */
	private $search;

	public function __construct( OOBJ_SEARCH_Query_Parser $parser, OOBJ_SEARCH_Search $search ) {
		$this->parser = $parser;
		$this->search = $search;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'init', array( $this, 'well_known' ) );
		add_action( 'wp_head', array( $this, 'link_header' ), 1 );
		add_action( 'send_headers', array( $this, 'send_headers' ) );
	}

	public function routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'        => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 200,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 12,
						'minimum' => 1,
						'maximum' => self::MAX_PER_PAGE,
					),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/openapi',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response( $this->openapi() ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Runs a search.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$query    = $this->parser->parse( (string) $request['q'] );
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $request['per_page'] ) );

		// What the shop's own search keeps: published products not hidden from search.
		$vars = array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => wc_get_product_visibility_term_ids()['exclude-from-search'] ?? array(),
					'operator' => 'NOT IN',
				),
			),
		);

		$relaxed = array();
		$args    = array();
		if ( null !== $query->product_number ) {
			$args = array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_sku',
						'value' => $query->product_number,
					),
				),
			);
		} elseif ( $query->understood() ) {
			$resolved = $this->search->resolve( $query, $vars );
			$args     = $resolved['args'];
			$relaxed  = $resolved['relaxed'];
		} else {
			$args = array( 's' => $query->text );
		}

		$found = new WP_Query(
			array_merge(
				$vars,
				array(
					'fields'              => 'ids',
					'posts_per_page'      => $per_page,
					'paged'               => $page,
					'ignore_sticky_posts' => true,
				),
				$args
			)
		);

		$response = rest_ensure_response(
			array(
				'query'       => $query->text,
				'understood'  => array(
					'parts'          => $query->parts,
					'keywords'       => $query->keywords,
					'min_price'      => $query->min_price,
					'max_price'      => $query->max_price,
					'sort'           => $query->sort,
					'product_number' => $query->product_number,
				),
				'left_out'    => $relaxed,
				'total'       => (int) $found->found_posts,
				'page'        => $page,
				'total_pages' => (int) $found->max_num_pages,
				'results'     => array_values( array_filter( array_map( array( $this, 'product' ), $found->posts ) ) ),
				'search_url'  => add_query_arg(
					array(
						's'         => rawurlencode( $query->text ),
						'post_type' => 'product',
					),
					home_url( '/' )
				),
			)
		);
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * A product as the API shows it.
	 *
	 * @param int $id Product ID.
	 * @return array<string, mixed>|null
	 */
	public function product( $id ): ?array {
		$product = wc_get_product( (int) $id );
		if ( ! $product ) {
			return null;
		}
		$image = wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' );
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		return array(
			'id'          => $product->get_id(),
			'name'        => $product->get_name(),
			'sku'         => $product->get_sku(),
			'url'         => get_permalink( $product->get_id() ),
			'price'       => $product->get_price(),
			'price_min'   => $product->is_type( 'variable' ) ? $product->get_variation_price( 'min' ) : $product->get_price(),
			'price_max'   => $product->is_type( 'variable' ) ? $product->get_variation_price( 'max' ) : $product->get_price(),
			'currency'    => get_woocommerce_currency(),
			'in_stock'    => $product->is_in_stock(),
			'image'       => $image ? $image : null,
			'categories'  => is_array( $terms ) ? array_values( wp_list_pluck( $terms, 'name' ) ) : array(),
			'description' => wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 40 ),
		);
	}

	/**
	 * The OpenAPI 3.1 description of this API.
	 *
	 * @return array<string, mixed>
	 */
	public function openapi(): array {
		$part = array(
			'type'       => 'object',
			'properties' => array(
				'kind'  => array(
					'type'        => 'string',
					'description' => 'A facet (category, material, fitting, colour, theme, gauge, size), "price" or "sort".',
				),
				'value' => array( 'type' => 'string' ),
				'label' => array(
					'type'    => 'string',
					'example' => 'Titanium',
				),
				'words' => array(
					'type'        => 'string',
					'description' => 'The words of the query this part was understood from.',
				),
			),
		);

		return array(
			'openapi' => '3.1.0',
			'info'    => array(
				'title'       => 'Out Of Body Jewellery product search',
				'version'     => OOBJ_SEARCH_VERSION,
				'description' => 'Read-only product search for outofbody.com.au. Natural-language queries are understood: "cheap titanium threadless septum 16g under $10" filters by material, fitting, category, gauge and price and sorts lowest price first. The response says what was understood and which parts were left out to find anything. Prices are in the shop currency (see `currency`).',
			),
			'servers' => array( array( 'url' => untrailingslashit( rest_url( self::REST_NAMESPACE ) ) ) ),
			'paths'   => array(
				'/search' => array(
					'get' => array(
						'operationId' => 'searchProducts',
						'summary'     => 'Search products',
						'description' => 'Write q the way a shopper would: material, fitting, gauge, size, colour, theme, "under $10", "cheap". Do not pre-split it into filters.',
						'parameters'  => array(
							array(
								'name'     => 'q',
								'in'       => 'query',
								'required' => true,
								'schema'   => array(
									'type'      => 'string',
									'minLength' => 1,
									'maxLength' => 200,
								),
								'example'  => 'titanium threadless septum 16g under $10',
							),
							array(
								'name'   => 'page',
								'in'     => 'query',
								'schema' => array(
									'type'    => 'integer',
									'minimum' => 1,
									'default' => 1,
								),
							),
							array(
								'name'   => 'per_page',
								'in'     => 'query',
								'schema' => array(
									'type'    => 'integer',
									'minimum' => 1,
									'maximum' => self::MAX_PER_PAGE,
									'default' => 12,
								),
							),
						),
						'responses'   => array(
							'200' => array(
								'description' => 'Matching products, best first.',
								'content'     => array(
									'application/json' => array(
										'schema' => array(
											'type'       => 'object',
											'properties' => array(
												'query'    => array( 'type' => 'string' ),
												'understood' => array(
													'type' => 'object',
													'properties' => array(
														'parts'     => array(
															'type'  => 'array',
															'items' => $part,
														),
														'keywords'  => array( 'type' => 'string' ),
														'min_price' => array( 'type' => array( 'number', 'null' ) ),
														'max_price' => array( 'type' => array( 'number', 'null' ) ),
														'sort'      => array(
															'type' => array( 'string', 'null' ),
															'enum' => array( 'price', 'price-desc', null ),
														),
														'product_number' => array( 'type' => array( 'string', 'null' ) ),
													),
												),
												'left_out' => array(
													'type' => 'array',
													'items' => array( 'type' => 'string' ),
													'description' => 'Kinds of part let go because nothing matched all of the query.',
												),
												'total'    => array( 'type' => 'integer' ),
												'page'     => array( 'type' => 'integer' ),
												'total_pages' => array( 'type' => 'integer' ),
												'search_url' => array(
													'type' => 'string',
													'format' => 'uri',
												),
												'results'  => array(
													'type' => 'array',
													'items' => array(
														'type'       => 'object',
														'properties' => array(
															'id'          => array( 'type' => 'integer' ),
															'name'        => array( 'type' => 'string' ),
															'sku'         => array( 'type' => 'string' ),
															'url'         => array(
																'type'   => 'string',
																'format' => 'uri',
															),
															'price'       => array( 'type' => 'string' ),
															'price_min'   => array( 'type' => 'string' ),
															'price_max'   => array( 'type' => 'string' ),
															'currency'    => array( 'type' => 'string' ),
															'in_stock'    => array( 'type' => 'boolean' ),
															'image'       => array( 'type' => array( 'string', 'null' ) ),
															'categories'  => array(
																'type'  => 'array',
																'items' => array( 'type' => 'string' ),
															),
															'description' => array( 'type' => 'string' ),
														),
													),
												),
											),
										),
									),
								),
							),
							'400' => array( 'description' => 'Missing or invalid q.' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Adds the /.well-known/ rewrite rules.
	 */
	public function well_known(): void {
		add_rewrite_rule( '^\.well-known/(openapi\.json|api-catalog|ard\.json)$', 'index.php?oobj_search_well_known=$matches[1]', 'top' );
		add_rewrite_tag( '%oobj_search_well_known%', '([^&]+)' );
	}

	/**
	 * Serves /.well-known/openapi.json and /.well-known/api-catalog, and adds Link headers pointing to them to every
	 * other response.
	 */
	public function send_headers(): void {
		$document = $this->well_known_document( (string) get_query_var( 'oobj_search_well_known' ) );
		if ( null !== $document ) {
			// @codeCoverageIgnoreStart
			status_header( 200 );
			header( 'Content-Type: ' . $document['type'] );
			header( 'Cache-Control: public, max-age=3600' );
			echo $document['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
			exit;
			// @codeCoverageIgnoreEnd
		}
		// @codeCoverageIgnoreStart
		if ( ! headers_sent() ) {
			foreach ( $this->link_headers() as $link ) {
				header( 'Link: ' . $link, false );
			}
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * What a /.well-known/ path serves.
	 *
	 * @param string $which "openapi.json" or "api-catalog" (anything else is not ours).
	 * @return array{type: string, body: string}|null
	 */
	public function well_known_document( string $which ): ?array {
		$flags = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
		if ( 'openapi.json' === $which ) {
			return array(
				'type' => 'application/vnd.oai.openapi+json; charset=utf-8',
				'body' => (string) wp_json_encode( $this->openapi(), $flags ),
			);
		}
		if ( 'ard.json' === $which ) {
			return array(
				'type' => 'application/json; charset=utf-8',
				'body' => (string) wp_json_encode( $this->ard(), $flags ),
			);
		}
		if ( 'api-catalog' === $which ) {
			return array(
				'type' => 'application/linkset+json; profile="https://www.rfc-editor.org/info/rfc9727"; charset=utf-8',
				'body' => (string) wp_json_encode( $this->catalog(), $flags ),
			);
		}
		return null;
	}

	/**
	 * The Link headers that point to the spec and the catalogue.
	 *
	 * @return string[]
	 */
	public function link_headers(): array {
		return array(
			sprintf( '<%s>; rel="service-desc"; type="application/vnd.oai.openapi+json"', esc_url_raw( home_url( '/.well-known/openapi.json' ) ) ),
			sprintf( '<%s>; rel="ard"', esc_url_raw( home_url( '/.well-known/ard.json' ) ) ),
			sprintf( '<%s>; rel="api-catalog"', esc_url_raw( home_url( '/.well-known/api-catalog' ) ) ),
		);
	}

	/**
	 * A discoverable link to the spec in every page's head.
	 */
	public function link_header(): void {
		printf(
			'<link rel="service-desc" type="application/vnd.oai.openapi+json" href="%1$s">' . "\n" . '<link rel="ard" href="%2$s">' . "\n",
			esc_url( home_url( '/.well-known/openapi.json' ) ),
			esc_url( home_url( '/.well-known/ard.json' ) )
		);
	}

	/**
	 * The Agentic Resource Discovery manifest (/.well-known/ard.json, v0.91): one entry, the product search, pointing at
	 * its OpenAPI description. Discovery only; it says nothing about trust.
	 *
	 * @return array<string, mixed>
	 */
	public function ard(): array {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return array(
			'specVersion' => '0.91',
			'entries'     => array(
				array(
					'identifier'            => 'urn:air:' . $host . ':search:products',
					'displayName'           => 'Out Of Body Jewellery product search',
					'type'                  => 'application/vnd.oai.openapi+json',
					'url'                   => home_url( '/.well-known/openapi.json' ),
					'representativeQueries' => array(
						'Titanium threadless septum 16g under $10',
						'Rose gold nose ring',
						'Cheap pink belly bars',
						'Christmas earrings',
					),
					'capabilities'          => array( 'searchProducts' ),
				),
			),
		);
	}

	/**
	 * The RFC 9727 API catalogue (a linkset).
	 *
	 * @return array<string, mixed>
	 */
	public function catalog(): array {
		return array(
			'linkset' => array(
				array(
					'anchor'       => rest_url( self::REST_NAMESPACE . '/search' ),
					'service-desc' => array(
						array(
							'href' => home_url( '/.well-known/openapi.json' ),
							'type' => 'application/vnd.oai.openapi+json',
						),
					),
				),
			),
		);
	}
}
