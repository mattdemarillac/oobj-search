<?php
/**
 * Class OOBJ_SEARCH_Command.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Out Of Body Search from WP-CLI: what a query means and finds, working out product facts, and how many products
 * have them.
 */
class OOBJ_SEARCH_Command {

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

	/**
	 * Product facts.
	 *
	 * @var OOBJ_SEARCH_Facts
	 */
	private $facts;

	public function __construct( OOBJ_SEARCH_Query_Parser $parser, OOBJ_SEARCH_Search $search, OOBJ_SEARCH_Facts $facts ) {
		$this->parser = $parser;
		$this->search = $search;
		$this->facts  = $facts;
	}

	/**
	 * What a query means, and how many products it finds.
	 *
	 * ## OPTIONS
	 *
	 * <query>
	 * : The search, as a shopper types it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oobj-search explain "cheap titanium threadless septum 16g under \$10"
	 *
	 * @param string[] $args The query.
	 */
	public function explain( array $args ): void {
		$query = $this->parser->parse( (string) ( $args[0] ?? '' ) );
		WP_CLI::log( 'Query: ' . $query->normalised );
		if ( null !== $query->product_number ) {
			WP_CLI::log( 'A product number: goes to the product with SKU or ID ' . $query->product_number . '.' );
			return;
		}
		foreach ( $query->parts as $part ) {
			WP_CLI::log( sprintf( '  %-9s %-28s from "%s"', $part['kind'], $part['label'], $part['words'] ) );
		}
		WP_CLI::log( 'Words searched for: ' . ( '' !== $query->keywords ? $query->keywords : '(none)' ) );
		if ( ! $query->understood() ) {
			WP_CLI::log( 'Nothing understood: a plain search.' );
			return;
		}

		list( $count, $relaxed ) = $this->count( $query );
		if ( $relaxed ) {
			WP_CLI::log( 'Nothing matched all of it; left out: ' . implode( ', ', $relaxed ) );
		}
		WP_CLI::log( sprintf( 'Finds %d products.', $count ) );
	}

	/**
	 * How many products each of the search form's example searches finds (OOBJ_SEARCH_Examples).
	 *
	 * ## EXAMPLES
	 *
	 *     wp oobj-search examples
	 */
	public function examples(): void {
		foreach ( OOBJ_SEARCH_Examples::EXAMPLES as $example ) {
			list( $count, $relaxed ) = $this->count( $this->parser->parse( $example ) );
			WP_CLI::log( sprintf( '%-32s %4d products%s', $example, $count, $relaxed ? ' (leaving out ' . implode( ', ', $relaxed ) . ')' : '' ) );
		}
	}

	/**
	 * How many published products a query finds, and what it had to leave out to find any.
	 *
	 * @param OOBJ_SEARCH_Query $query The query, understood.
	 * @return array{0: int, 1: string[]}
	 */
	private function count( OOBJ_SEARCH_Query $query ): array {
		$vars     = array(
			'post_type'   => 'product',
			'post_status' => 'publish',
		);
		$resolved = $this->search->resolve( $query, $vars );
		$found    = new WP_Query(
			array_merge(
				$vars,
				array(
					'fields'         => 'ids',
					'posts_per_page' => 1,
				),
				$resolved['args']
			)
		);
		return array( $found->found_posts, $resolved['relaxed'] );
	}

	/**
	 * Works out every product's facts now (otherwise they're worked out in the background when needed).
	 *
	 * ## EXAMPLES
	 *
	 *     wp oobj-search index
	 */
	public function index(): void {
		$after = 0;
		$done  = 0;
		do {
			$ids = $this->facts->product_ids( $after, OOBJ_SEARCH_Facts::BATCH );
			foreach ( $ids as $id ) {
				$this->facts->index( $id );
			}
			$batch = count( $ids );
			$done += $batch;
			$after = $ids ? (int) end( $ids ) : $after;
			if ( $ids ) {
				WP_CLI::log( "$done products" );
			}
		} while ( OOBJ_SEARCH_Facts::BATCH === $batch );
		update_option( OOBJ_SEARCH_Facts::VERSION_OPTION, OOBJ_SEARCH_Facts::version() );
		WP_CLI::success( "Facts worked out for $done products." );
	}

	/**
	 * How many published products have facts of each kind, and how many facts are kept.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oobj-search coverage
	 */
	public function coverage(): void {
		$coverage = $this->facts->coverage();
		foreach ( $coverage['facets'] as $facet => $products ) {
			WP_CLI::log( sprintf( '%-9s %5d products (%d%%)', $facet, $products, $coverage['products'] ? round( 100 * $products / $coverage['products'] ) : 0 ) );
		}
		WP_CLI::log( sprintf( '%d facts kept (%d kinds) for %d products.', $coverage['relationships'], $coverage['terms'], $coverage['products'] ) );
	}
}
