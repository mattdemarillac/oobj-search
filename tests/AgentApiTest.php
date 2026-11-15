<?php
/**
 * OOBJ_SEARCH_Agent_API: the read-only search endpoint and the OpenAPI description agents find it by.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

class AgentApiTest extends OOBJ_SEARCH_TestCase {

	private function api(): OOBJ_SEARCH_Agent_API {
		return oobj_search( OOBJ_SEARCH_Agent_API::class );
	}

	private function get( array $params ): WP_REST_Response {
		do_action( 'rest_api_init' );
		$request = new WP_REST_Request( 'GET', '/oobj-search/v1/search' );
		$request->set_query_params( $params );
		return rest_do_request( $request );
	}

	public function test_it_registers_its_routes() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/oobj-search/v1/search', $routes );
		$this->assertArrayHasKey( '/oobj-search/v1/openapi', $routes );
	}

	public function test_a_query_is_understood_and_finds_products() {
		$this->product( 'Titanium Septum Clicker', 8, array( 'septum-daith-clickers-and-rings' ) );
		$this->product( 'Steel Septum Clicker', 6, array( 'septum-daith-clickers-and-rings' ) );

		$response = $this->get( array( 'q' => 'titanium septum under $10' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( 'material', wp_list_pluck( $data['understood']['parts'], 'kind' ) );
		$this->assertSame( 10.0, (float) $data['understood']['max_price'] );
		$this->assertSame( array(), $data['left_out'] );
		$this->assertSame( array( 'Titanium Septum Clicker' ), wp_list_pluck( $data['results'], 'name' ) );
		$this->assertSame( 1, $data['total'] );
		$this->assertSame( 'public, max-age=300', $response->get_headers()['Cache-Control'] );
	}

	public function test_it_says_what_was_left_out_to_find_anything() {
		$this->product( 'Titanium Septum Clicker', 8, array( 'septum-daith-clickers-and-rings' ) );

		$data = $this->get( array( 'q' => 'titanium septum under $1' ) )->get_data();

		$this->assertContains( 'price', $data['left_out'] );
		$this->assertSame( 1, $data['total'] );
	}

	public function test_a_plain_query_searches_the_words() {
		$this->product( 'Zebra Charm', 5 );
		$this->product( 'Other Thing', 5 );

		$data = $this->get( array( 'q' => 'zebra' ) )->get_data();

		$this->assertSame( array( 'Zebra Charm' ), wp_list_pluck( $data['results'], 'name' ) );
	}

	public function test_a_product_number_finds_that_sku() {
		$this->product( 'Numbered', 5, array(), array( 'sku' => '966' ) );
		$this->product( 'Another', 5, array(), array( 'sku' => '967' ) );

		$data = $this->get( array( 'q' => '966' ) )->get_data();

		$this->assertSame( array( 'Numbered' ), wp_list_pluck( $data['results'], 'name' ) );
	}

	public function test_products_hidden_from_search_stay_hidden() {
		$hidden = $this->product( 'Hidden Zebra', 5 );
		$hidden->set_catalog_visibility( 'hidden' );
		$hidden->save();

		$data = $this->get( array( 'q' => 'zebra' ) )->get_data();

		$this->assertSame( 0, $data['total'] );
	}

	public function test_paging_is_limited() {
		$this->product( 'Zebra One', 5 );
		$this->product( 'Zebra Two', 5 );

		$data = $this->get(
			array(
				'q'        => 'zebra',
				'per_page' => 1,
				'page'     => 2,
			)
		)->get_data();

		$this->assertCount( 1, $data['results'] );
		$this->assertSame( 2, $data['page'] );
		$this->assertSame( 2, $data['total_pages'] );
		$this->assertSame(
			400,
			$this->get(
				array(
					'q'        => 'zebra',
					'per_page' => 500,
				)
			)->get_status()
		);
	}

	public function test_q_is_required() {
		$this->assertSame( 400, $this->get( array() )->get_status() );
	}

	public function test_a_product_has_its_price_range_and_stock() {
		$simple   = $this->product( 'Simple', 5 );
		$variable = $this->variable_product(
			'Variable',
			array(
				'Small' => 4.0,
				'Large' => 9.0,
			)
		);

		$one = $this->api()->product( $simple->get_id() );
		$two = $this->api()->product( $variable->get_id() );

		$this->assertSame( 'Simple', $one['name'] );
		$this->assertTrue( $one['in_stock'] );
		$this->assertSame( $one['price_min'], $one['price_max'] );
		$this->assertSame( '4.00', $two['price_min'] );
		$this->assertSame( '9.00', $two['price_max'] );
		$this->assertNull( $this->api()->product( 999999 ) );
	}

	public function test_the_openapi_description_covers_the_search() {
		$spec = $this->api()->openapi();

		$this->assertSame( '3.0.3', $spec['openapi'] );
		$this->assertStringNotContainsString( '"type": [', wp_json_encode( $spec, JSON_PRETTY_PRINT ), 'OpenAPI 3.0 has no type arrays' );
		$this->assertSame( 'searchProducts', $spec['paths']['/search']['get']['operationId'] );
		$this->assertSame( array( 'q', 'page', 'per_page' ), wp_list_pluck( $spec['paths']['/search']['get']['parameters'], 'name' ) );
		$this->assertSame( untrailingslashit( rest_url( 'oobj-search/v1' ) ), $spec['servers'][0]['url'] );
	}

	public function test_well_known_documents() {
		$spec    = $this->api()->well_known_document( 'openapi.json' );
		$catalog = $this->api()->well_known_document( 'api-catalog' );

		$this->assertStringStartsWith( 'application/vnd.oai.openapi+json', $spec['type'] );
		$this->assertSame( '3.0.3', json_decode( $spec['body'], true )['openapi'] );
		$this->assertStringStartsWith( 'application/linkset+json', $catalog['type'] );
		$this->assertSame( home_url( '/.well-known/openapi.json' ), json_decode( $catalog['body'], true )['linkset'][0]['service-desc'][0]['href'] );
		$ard = json_decode( $this->api()->well_known_document( 'ard.json' )['body'], true );
		$this->assertSame( '0.91', $ard['specVersion'] );
		$this->assertCount( 1, $ard['entries'] );
		$this->assertMatchesRegularExpression( '/^urn:air:[^:]+:[^:]+:[^:]+$/', $ard['entries'][0]['identifier'] );
		$this->assertSame( home_url( '/.well-known/openapi.json' ), $ard['entries'][0]['url'] );
		$this->assertArrayNotHasKey( 'data', $ard['entries'][0], 'Exactly one of url or data' );
		$this->assertGreaterThanOrEqual( 2, count( $ard['entries'][0]['representativeQueries'] ) );
		$this->assertLessThanOrEqual( 5, count( $ard['entries'][0]['representativeQueries'] ) );
		$this->assertNull( $this->api()->well_known_document( 'other' ) );
	}

	public function test_it_points_agents_at_the_spec() {
		$links = $this->api()->link_headers();

		$this->assertStringContainsString( 'rel="service-desc"', $links[0] );
		$this->assertStringContainsString( 'rel="ard"', $links[1] );
		$this->assertStringContainsString( 'rel="api-catalog"', $links[2] );

		ob_start();
		$this->api()->link_header();
		$head = ob_get_clean();
		$this->assertStringContainsString( 'rel="service-desc"', $head );
		$this->assertStringContainsString( '<link rel="ard" href="' . esc_url( home_url( '/.well-known/ard.json' ) ) . '">', $head );
	}

	public function test_the_well_known_rules_are_added() {
		$this->api()->well_known();

		$this->assertArrayHasKey( '^\.well-known/(openapi\.json|api-catalog|ard\.json)$', $GLOBALS['wp_rewrite']->extra_rules_top );
	}
}
