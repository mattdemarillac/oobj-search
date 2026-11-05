<?php
/**
 * Class OOBJ_SEARCH_Query.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

/**
 * What a search query means (OOBJ_SEARCH_Query_Parser): facets to filter by, a price range, an order, and the words
 * left to search product text for. Plain PHP.
 *
 * Values of one facet are alternatives (titanium or steel); facets all apply (titanium and threadless).
 */
final class OOBJ_SEARCH_Query {

	/**
	 * The query as typed.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Facet => its values (OOBJ_SEARCH_Vocabulary::FACETS), in the order understood.
	 *
	 * @var array<string, string[]>
	 */
	public $facets = array();

	/**
	 * Lowest price, inclusive, in the shop's currency; null for none.
	 *
	 * @var float|null
	 */
	public $min_price = null;

	/**
	 * Highest price, inclusive; null for none.
	 *
	 * @var float|null
	 */
	public $max_price = null;

	/**
	 * 'price' (lowest first), 'price-desc', or null to leave the order alone.
	 *
	 * @var string|null
	 */
	public $sort = null;

	/**
	 * The words left to search product text for, normalised ('' for none).
	 *
	 * @var string
	 */
	public $keywords = '';

	/**
	 * A number that may be a product's SKU or ID ("966"), when the query is only that; null otherwise.
	 *
	 * @var string|null
	 */
	public $product_number = null;

	/**
	 * What was understood, in the order it appears in the query: each part's kind (a facet, 'price' or 'sort'), value
	 * (a facet value; for price "min-max" with either side empty, e.g. "-10"; for sort the sort), label for a person
	 * ("Titanium", "Under $10", "Cheapest first") and the words it was understood from, as they are in the normalised
	 * query.
	 *
	 * @var array<int, array{kind: string, value: string, label: string, words: string}>
	 */
	public $parts = array();

	/**
	 * The normalised query.
	 *
	 * @var string
	 */
	public $normalised = '';

	public function __construct( string $text ) {
		$this->text       = $text;
		$this->normalised = OOBJ_SEARCH_Text::normalise( $text );
	}

	/**
	 * Whether any of the query was understood (not only words to search for).
	 */
	public function understood(): bool {
		return array() !== $this->parts;
	}

	/**
	 * The query without one of its parts: what to search for when the shopper removes that part ("titanium nose ring
	 * under $10" without its price is "titanium nose ring").
	 *
	 * @param int $index The part, a key of parts.
	 */
	public function without( int $index ): string {
		if ( ! isset( $this->parts[ $index ] ) ) {
			return $this->normalised;
		}
		// Parts are in query order, so each is found after the one before: a part whose words come up twice ("gold
		// ring gold") removes its own. Parts out of order fall back to the first time the words come up.
		$offset = 0;
		$at     = null;
		foreach ( $this->parts as $key => $part ) {
			if ( 1 !== preg_match( OOBJ_SEARCH_Text::pattern( $part['words'] ), $this->normalised, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
				$at = null;
				break;
			}
			$at     = $match[0];
			$offset = $at[1] + strlen( $at[0] );
			if ( $key === $index ) {
				break;
			}
		}
		if ( null === $at ) {
			preg_match( OOBJ_SEARCH_Text::pattern( $this->parts[ $index ]['words'] ), $this->normalised, $match, PREG_OFFSET_CAPTURE );
			$at = $match[0] ?? array( '', 0 );
		}
		$rest = substr_replace( $this->normalised, ' ', $at[1], strlen( $at[0] ) );
		return trim( (string) preg_replace( '/\s+/', ' ', $rest ) );
	}
}
