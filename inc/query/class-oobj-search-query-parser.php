<?php
/**
 * Class OOBJ_SEARCH_Query_Parser.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

/**
 * Reads what a shopper typed into an OOBJ_SEARCH_Query: "cheap titanium threadless septum 16g under $10" is category
 * septum, material titanium, fitting threadless, gauge 1.2mm, up to $10, cheapest first, and no words left over.
 * Plain PHP, deterministic, no WordPress.
 *
 * It reads the normalised query (OOBJ_SEARCH_Text) as words, in three passes over the words not yet taken, so each
 * word belongs to at most one part:
 * 1. Prices, anchored on a number: "under $10", "$5-10", "between 5 and 10", "10 dollars". A bare number is a price
 *    only after a price word ("under 10"); a number with a unit ("10mm", "16 g") never is.
 * 2. Vocabulary phrases (every facet's, the sort phrases, and NOISE_PHRASES and KEYWORD_PHRASES, which stop words
 *    being misread), longest first over the whole query: "rose gold" before gold, "septum retainer" before septum.
 * 3. Measurements: gauges ("16g", "16 gauge", "1.2mm") and sizes ("8mm"). Over GAUGE_MAX_MM a measurement is a size,
 *    unless the query names stretching jewellery (pass 2), where it's a gauge.
 * What's left, less stop words, is keywords. Values of a facet are kept once each, in query order; a category another
 * named category is inside is dropped from the facet ("tragus flat back" is flat back studs), though its part stays.
 * A query that is only a 3 to 6 digit number is a product number and nothing else.
 */
final class OOBJ_SEARCH_Query_Parser {

	/**
	 * Words before an amount that make it the highest price ("under 10", "less than $10").
	 */
	const MAX_BEFORE = array( 'under', 'below', 'less than', 'lower than', 'cheaper than', 'up to', 'upto', 'max', 'maximum', 'at most', 'no more than', 'not more than', '<' );

	/**
	 * Words before an amount that make it the lowest price ("over 20", "at least $20").
	 */
	const MIN_BEFORE = array( 'over', 'above', 'more than', 'greater than', 'higher than', 'at least', 'from', 'min', 'minimum', 'starting at', '>' );

	/**
	 * Words after an amount that make it the highest price ("$10 or less").
	 */
	const MAX_AFTER = array( 'or less', 'or under', 'and under', 'or below', 'and below', 'or cheaper', 'max' );

	/**
	 * Words after an amount that make it the lowest price ("$20 or more").
	 */
	const MIN_AFTER = array( 'or more', 'and up', 'and over', 'and above', 'or over', 'plus', 'min' );

	/**
	 * Words after a number that make it money ("10 dollars"), so a price on its own.
	 */
	const CURRENCY_WORDS = array( 'dollar', 'dollars', 'buck', 'bucks', 'aud' );

	/**
	 * Words after a number that make it a measurement or a count, never a price ("10 mm", "10 pack").
	 */
	const UNIT_WORDS = array( 'mm', 'cm', 'g', 'ga', 'gauge', 'gauges', 'guage', 'gage', 'inch', 'inches', 'k', 'kt', 'ct', 'pc', 'pcs', 'pack', 'packs', 'piece', 'pieces' );

	/**
	 * Words that make a number next to them a gauge ("16 gauge", "gauge 16").
	 */
	const GAUGE_WORDS = array( 'g', 'ga', 'gauge', 'guage', 'gage' );

	/**
	 * Phrases that say nothing about the product and would otherwise be misread ("for sale" isn't on sale; Gold Coast
	 * is a place, not a colour): taken and dropped.
	 */
	const NOISE_PHRASES = array( 'for sale', 'near me', 'gold coast' );

	/**
	 * Phrases whose words would otherwise be misread as a facet ("butterfly back" is a backing, not an animal): kept as
	 * keywords.
	 */
	const KEYWORD_PHRASES = array( 'butterfly back', 'butterfly backs', 'snake chain', 'cat eye', 'cats eye' );

	/**
	 * Every phrase the second pass knows, by its first word (built once).
	 *
	 * @var array<string, array<int, array{words: string[], length: int, order: int, kind: string, value: string}>>|null
	 */
	private static $phrases = null;

	/**
	 * STOP_WORDS as keys (built once).
	 *
	 * @var array<string, bool>|null
	 */
	private static $stop_words = null;

	/**
	 * The query's words.
	 *
	 * @var string[]
	 */
	private $words = array();

	/**
	 * For each word, whether a part (or a noise phrase) has it.
	 *
	 * @var bool[]
	 */
	private $taken = array();

	/**
	 * What was understood: kind ('price', 'sort', a facet, or 'noise' / 'keywords'), value, label, first and last word.
	 *
	 * @var array<int, array{kind: string, value: string, label: string, start: int, end: int, min?: float|null, max?: float|null}>
	 */
	private $spans = array();

	/**
	 * Reads a query.
	 *
	 * @param string $text The query as typed.
	 */
	public function parse( string $text ): OOBJ_SEARCH_Query {
		$query = new OOBJ_SEARCH_Query( $text );
		if ( '' === $query->normalised ) {
			return $query;
		}
		$this->words = explode( ' ', $query->normalised );
		$this->taken = array_fill( 0, count( $this->words ), false );
		$this->spans = array();

		// A number on its own is a product's SKU or ID, unless the vocabulary knows it ("925" is sterling silver).
		if ( 1 === count( $this->words ) && 1 === preg_match( '/^\d{3,6}$/', $this->words[0] ) && ! self::is_phrase( $this->words[0] ) ) {
			$query->product_number = $this->words[0];
			return $query;
		}

		$this->read_prices();
		$this->read_phrases();
		$this->read_measurements();
		$this->fill( $query );
		return $query;
	}

	/**
	 * Pass 1: prices, each from the first word it can start at. Being first, it finds every word after the last price
	 * free.
	 */
	private function read_prices(): void {
		$count = count( $this->words );
		for ( $i = 0; $i < $count; $i++ ) {
			$price = $this->price_at( $i );
			if ( null !== $price ) {
				$this->take(
					$i,
					$price['end'],
					'price',
					'',
					array(
						'min' => $price['min'],
						'max' => $price['max'],
					)
				);
				$i = $price['end'];
			}
		}
	}

	/**
	 * The price starting at a word, if one does.
	 *
	 * @param int $i A word.
	 * @return array{min: float|null, max: float|null, end: int}|null
	 */
	private function price_at( int $i ): ?array {
		$word = $this->words[ $i ];

		// A range after between or from: between 5 and 10, from $5 to $10.
		if ( 'between' === $word || 'from' === $word ) {
			$range = $this->range_at( $i + 1, true );
			if ( null !== $range ) {
				return $range;
			}
		}

		// A price word, then an amount: under 10, less than $10, at least $20.
		foreach ( array(
			'max' => self::MAX_BEFORE,
			'min' => self::MIN_BEFORE,
		) as $bound => $phrases ) {
			foreach ( $phrases as $phrase ) {
				$after = $this->phrase_at( $i, $phrase );
				if ( null !== $after ) {
					$amount = $this->amount_at( $after );
				} elseif ( 1 === strlen( $phrase ) && 0 === strpos( $word, $phrase ) ) {
					$amount = $this->amount_at( $i, 1 );
				} else {
					continue;
				}
				if ( null !== $amount ) {
					return $this->bound( $bound, $amount['value'], $amount['end'] );
				}
			}
		}

		// A range of money: $5 to $10, 5 to 10 dollars, $5-10.
		$range = $this->range_at( $i, false );
		if ( null !== $range ) {
			return $range;
		}

		$amount = $this->amount_at( $i );
		if ( null === $amount ) {
			return null;
		}
		// An amount, then a price word: $10 or less, 20 or more.
		foreach ( array(
			'max' => self::MAX_AFTER,
			'min' => self::MIN_AFTER,
		) as $bound => $phrases ) {
			foreach ( $phrases as $phrase ) {
				$after = $this->phrase_at( $amount['end'] + 1, $phrase );
				if ( null !== $after ) {
					return $this->bound( $bound, $amount['value'], $after - 1 );
				}
			}
		}
		// Money alone ($10, 10 dollars) is up to that.
		return $amount['money'] ? $this->bound( 'max', $amount['value'], $amount['end'] ) : null;
	}

	/**
	 * A price range starting at a word: "5-10", or "5 to 10" / "5 and 10" (after between or from), "$5 $10".
	 *
	 * @param int  $i     A word.
	 * @param bool $after Whether it follows "between" or "from", so bare numbers are prices.
	 * @return array{min: float|null, max: float|null, end: int}|null
	 */
	private function range_at( int $i, bool $after ): ?array {
		if ( ! isset( $this->words[ $i ] ) ) {
			return null;
		}
		if ( 1 === preg_match( '/^(\$?)(\d{1,6}(?:\.\d{1,2})?)\$?-(\$?)(\d{1,6}(?:\.\d{1,2})?)(\$?)$/', $this->words[ $i ], $match ) ) {
			$end   = $i;
			$money = '' !== $match[1] . $match[3] . $match[5] || $this->currency_at( $i + 1 );
			if ( $this->currency_at( $i + 1 ) ) {
				++$end;
			}
			if ( ! $money && ! $after ) {
				return null;
			}
			return $this->range( (float) $match[2], (float) $match[4], $end );
		}

		$low = $this->amount_at( $i );
		if ( null === $low ) {
			return null;
		}
		$next = $low['end'] + 1;
		if ( in_array( $this->words[ $next ] ?? '', array( 'to', 'and' ), true ) ) {
			$high = $this->amount_at( $next + 1 );
			if ( null !== $high && ( $after || $low['money'] || $high['money'] ) ) {
				return $this->range( $low['value'], $high['value'], $high['end'] );
			}
			return null;
		}
		// Two amounts of money side by side: $5 - $10 is normalised to that.
		$high = $this->amount_at( $next );
		if ( $low['money'] && null !== $high && $high['money'] ) {
			return $this->range( $low['value'], $high['value'], $high['end'] );
		}
		return null;
	}

	/**
	 * The amount of money at a word: "10", "$10", "10$", "10aud", "10 dollars". Not a number followed by a unit
	 * ("10 mm", "10 pack"), which is never a price.
	 *
	 * @param int $i    A word.
	 * @param int $skip Characters to skip at its start ("<" in "<10").
	 * @return array{value: float, money: bool, end: int}|null
	 */
	private function amount_at( int $i, int $skip = 0 ): ?array {
		if ( 1 !== preg_match( '/^(\$?)(\d{1,6}(?:\.\d{1,2})?)(\$|aud|dollars?|bucks?)?$/', substr( $this->words[ $i ] ?? '', $skip ), $match ) ) {
			return null;
		}
		$end   = $i;
		$money = '' !== $match[1] || isset( $match[3] );
		if ( $this->currency_at( $i + 1 ) ) {
			$money = true;
			++$end;
		}
		if ( in_array( $this->words[ $end + 1 ] ?? '', self::UNIT_WORDS, true ) ) {
			return null;
		}
		// Nor the start of a range of measurements ("5 to 10mm").
		if ( 'to' === ( $this->words[ $end + 1 ] ?? '' ) && 1 === preg_match( '/^\d/', $this->words[ $end + 2 ] ?? '' ) && null === $this->amount_at( $end + 2 ) ) {
			return null;
		}
		return array(
			'value' => (float) $match[2],
			'money' => $money,
			'end'   => $end,
		);
	}

	/**
	 * Whether a word is a currency word ("dollars").
	 *
	 * @param int $i A word.
	 */
	private function currency_at( int $i ): bool {
		return in_array( $this->words[ $i ] ?? '', self::CURRENCY_WORDS, true );
	}

	/**
	 * Where a phrase ends, plus one, if its words start at a word.
	 *
	 * @param int    $i      A word.
	 * @param string $phrase Words.
	 * @return int|null The word after the phrase.
	 */
	private function phrase_at( int $i, string $phrase ): ?int {
		foreach ( explode( ' ', $phrase ) as $offset => $word ) {
			if ( ( $this->words[ $i + $offset ] ?? null ) !== $word ) {
				return null;
			}
		}
		return $i + $offset + 1;
	}

	/**
	 * A one-sided price.
	 *
	 * @param string $bound 'min' or 'max'.
	 * @param float  $value The amount.
	 * @param int    $end   Its last word.
	 * @return array{min: float|null, max: float|null, end: int}
	 */
	private function bound( string $bound, float $value, int $end ): array {
		return array(
			'min' => 'min' === $bound ? $value : null,
			'max' => 'max' === $bound ? $value : null,
			'end' => $end,
		);
	}

	/**
	 * A price range, lowest first whichever way it was typed.
	 *
	 * @param float $a   One end.
	 * @param float $b   The other.
	 * @param int   $end Its last word.
	 * @return array{min: float|null, max: float|null, end: int}
	 */
	private function range( float $a, float $b, int $end ): array {
		return array(
			'min' => min( $a, $b ),
			'max' => max( $a, $b ),
			'end' => $end,
		);
	}

	/**
	 * Pass 2: vocabulary phrases, longest first over the whole query (then the leftmost), each on free words only.
	 */
	private function read_phrases(): void {
		$phrases    = self::phrases();
		$candidates = array();
		foreach ( $this->words as $i => $word ) {
			foreach ( $phrases[ $word ] ?? array() as $phrase ) {
				if ( array_slice( $this->words, $i, count( $phrase['words'] ) ) === $phrase['words'] ) {
					$candidates[] = array( $i, $phrase );
				}
			}
		}
		usort(
			$candidates,
			fn( $a, $b ) => array( count( $b[1]['words'] ), $b[1]['length'], $a[0], $a[1]['order'] ) <=> array( count( $a[1]['words'] ), $a[1]['length'], $b[0], $b[1]['order'] )
		);
		foreach ( $candidates as list( $i, $phrase ) ) {
			$end = $i + count( $phrase['words'] ) - 1;
			if ( ! in_array( true, array_slice( $this->taken, $i, $end - $i + 1 ), true ) ) {
				$this->take( $i, $end, $phrase['kind'], $phrase['value'] );
			}
		}
	}

	/**
	 * Pass 3: gauges and sizes. Two of the same thickness side by side are one ("16g 1.2mm", "0g 8mm").
	 */
	private function read_measurements(): void {
		$stretching = $this->stretching();
		$count      = count( $this->words );
		$last       = null;
		for ( $i = 0; $i < $count; $i++ ) {
			$measure = $this->measurement_at( $i );
			if ( null === $measure ) {
				continue;
			}
			$kind = $measure['gauge'] || (float) $measure['mm'] <= OOBJ_SEARCH_Vocabulary::GAUGE_MAX_MM || $stretching ? 'gauge' : 'size';
			if ( null !== $last && $this->spans[ $last ]['end'] === $i - 1 && $this->spans[ $last ]['value'] === $measure['mm'] ) {
				// The same thickness written twice ("0g 8mm") is one gauge.
				$this->spans[ $last ]['kind'] = 'gauge' === $kind ? 'gauge' : $this->spans[ $last ]['kind'];
				$this->extend( $last, $measure['end'] );
			} else {
				$last = $this->take( $i, $measure['end'], $kind, $measure['mm'] );
			}
			$i = $measure['end'];
		}
	}

	/**
	 * The measurement starting at a word, if one does: its thickness or length in mm, whether it's a gauge whatever
	 * its size, and its last word.
	 *
	 * @param int $i A word.
	 * @return array{mm: string, gauge: bool, end: int}|null
	 */
	private function measurement_at( int $i ): ?array {
		if ( $this->taken[ $i ] ) {
			return null;
		}
		$word = $this->words[ $i ];
		$next = $this->free( $i + 1 ) ? $this->words[ $i + 1 ] : '';

		// A gauge (16g, 16ga, 16 gauge, gauge 16) the shop sells, or nothing: 15g stays a word.
		if ( 1 === preg_match( '/^(\d{1,2})(?:g|ga|gauge|guage|gage)$/', $word, $match ) ) {
			return $this->gauge( $match[1], $i );
		}
		if ( 1 === preg_match( '/^\d{1,2}$/', $word ) && in_array( $next, self::GAUGE_WORDS, true ) ) {
			return $this->gauge( $word, $i + 1 );
		}
		$gauge_word = in_array( $word, self::GAUGE_WORDS, true ) && 'g' !== $word && 'ga' !== $word;
		if ( $gauge_word && 1 === preg_match( '/^\d{1,2}$/', $next ) ) {
			return $this->gauge( $next, $i + 1 );
		}

		// A number of mm (8mm, 8 mm), maybe called a gauge (gauge 10mm, 10mm gauge).
		$start = $gauge_word ? $i + 1 : $i;
		if ( ! $this->free( $start ) ) {
			return null;
		}
		if ( 1 === preg_match( '/^(\d{1,3}(?:\.\d{1,2})?)mm$/', $this->words[ $start ], $match ) ) {
			$end = $start;
		} elseif ( 1 === preg_match( '/^\d{1,3}(?:\.\d{1,2})?$/', $this->words[ $start ] ) && $this->free( $start + 1 ) && 'mm' === $this->words[ $start + 1 ] ) {
			$match = array( '', $this->words[ $start ] );
			$end   = $start + 1;
		} else {
			return null;
		}
		$mm = OOBJ_SEARCH_Vocabulary::mm( $match[1] );
		if ( '0' === $mm ) {
			return null;
		}
		$gauge_after = $this->free( $end + 1 ) && in_array( $this->words[ $end + 1 ], array( 'gauge', 'guage', 'gage' ), true );
		return array(
			'mm'    => $mm,
			'gauge' => $gauge_word || $gauge_after,
			'end'   => $gauge_after ? $end + 1 : $end,
		);
	}

	/**
	 * A gauge by its number, when the shop sells it.
	 *
	 * @param string $gauge The gauge ("16", "00").
	 * @param int    $end   Its last word.
	 * @return array{mm: string, gauge: bool, end: int}|null
	 */
	private function gauge( string $gauge, int $end ): ?array {
		if ( ! isset( OOBJ_SEARCH_Vocabulary::GAUGES[ $gauge ] ) ) {
			return null;
		}
		return array(
			'mm'    => OOBJ_SEARCH_Vocabulary::GAUGES[ $gauge ],
			'gauge' => true,
			'end'   => $end,
		);
	}

	/**
	 * Whether the query names stretching jewellery (STRETCHING_CATEGORIES), where any mm is a gauge.
	 */
	private function stretching(): bool {
		foreach ( $this->spans as $span ) {
			if ( 'category' !== $span['kind'] ) {
				continue;
			}
			foreach ( explode( '|', $span['value'] ) as $path ) {
				$parts = explode( '/', $path );
				if ( in_array( end( $parts ), OOBJ_SEARCH_Vocabulary::STRETCHING_CATEGORIES, true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether a word exists and is free.
	 *
	 * @param int $i A word.
	 */
	private function free( int $i ): bool {
		return isset( $this->words[ $i ] ) && ! $this->taken[ $i ];
	}

	/**
	 * Takes words for a span.
	 *
	 * @param int                  $start First word.
	 * @param int                  $end   Last word.
	 * @param string               $kind  What it is.
	 * @param string               $value What it means.
	 * @param array<string, mixed> $extra More (a price's min and max).
	 * @return int The span.
	 */
	private function take( int $start, int $end, string $kind, string $value, array $extra = array() ): int {
		$this->spans[] = array_merge( compact( 'kind', 'value', 'start', 'end' ), $extra );
		$this->extend( count( $this->spans ) - 1, $end );
		return count( $this->spans ) - 1;
	}

	/**
	 * Takes the words up to $end for a span.
	 *
	 * @param int $span A span.
	 * @param int $end  Its new last word.
	 */
	private function extend( int $span, int $end ): void {
		$this->spans[ $span ]['end'] = $end;
		for ( $i = $this->spans[ $span ]['start']; $i <= $end; $i++ ) {
			$this->taken[ $i ] = true;
		}
	}

	/**
	 * Fills the query from the spans, in the order they appear, and the free words.
	 *
	 * @param OOBJ_SEARCH_Query $query The query.
	 */
	private function fill( OOBJ_SEARCH_Query $query ): void {
		usort( $this->spans, fn( $a, $b ) => $a['start'] <=> $b['start'] );
		$keywords = array();
		foreach ( $this->spans as $span ) {
			$words = implode( ' ', array_slice( $this->words, $span['start'], $span['end'] - $span['start'] + 1 ) );
			if ( 'noise' === $span['kind'] ) {
				continue;
			}
			if ( 'keywords' === $span['kind'] ) {
				$keywords[ $span['start'] ] = $words;
				continue;
			}
			if ( 'price' === $span['kind'] ) {
				$query->min_price      = null === $span['min'] ? $query->min_price : max( $span['min'], $query->min_price ?? $span['min'] );
				$query->max_price      = null === $span['max'] ? $query->max_price : min( $span['max'], $query->max_price ?? $span['max'] );
				list( $value, $label ) = self::price_reading( $span['min'], $span['max'] );
			} elseif ( 'sort' === $span['kind'] ) {
				$query->sort = $query->sort ?? $span['value'];
				$value       = $span['value'];
				$label       = 'price' === $value ? 'Lowest price first' : 'Highest price first';
			} else {
				$value = $span['value'];
				$label = OOBJ_SEARCH_Vocabulary::label( $span['kind'], $value );
				if ( ! in_array( $value, $query->facets[ $span['kind'] ] ?? array(), true ) ) {
					$query->facets[ $span['kind'] ][] = $value;
				}
			}
			$query->parts[] = array(
				'kind'  => $span['kind'],
				'value' => $value,
				'label' => $label,
				'words' => $words,
			);
		}//end foreach

		if ( isset( $query->facets['category'] ) ) {
			$query->facets['category'] = self::narrowest( $query->facets['category'] );
		}

		$stop = self::stop_words();
		foreach ( $this->words as $i => $word ) {
			if ( ! $this->taken[ $i ] && ! isset( $stop[ $word ] ) && 1 === preg_match( '/[\p{L}\p{N}]/u', $word ) ) {
				$keywords[ $i ] = $word;
			}
		}
		ksort( $keywords );
		$query->keywords = implode( ' ', $keywords );
	}

	/**
	 * Categories without the ones another category named is inside: "tragus flat back" is flat back studs (in ear and
	 * cartilage), not anything for the ear; "helix earrings" is ear and cartilage, not every earring. Their parts stay.
	 *
	 * @param string[] $categories Category values (paths joined by "|").
	 * @return string[]
	 */
	private static function narrowest( array $categories ): array {
		$kept = array();
		foreach ( $categories as $broad ) {
			$wider = false;
			foreach ( $categories as $narrow ) {
				$wider = $wider || ( $narrow !== $broad && self::within( $narrow, $broad ) );
			}
			if ( ! $wider ) {
				$kept[] = $broad;
			}
		}
		return $kept;
	}

	/**
	 * Whether every category of one value is one of another's or inside it.
	 *
	 * @param string $narrow A category value.
	 * @param string $broad  Another.
	 */
	private static function within( string $narrow, string $broad ): bool {
		foreach ( explode( '|', $narrow ) as $path ) {
			$inside = false;
			foreach ( explode( '|', $broad ) as $outer ) {
				$inside = $inside || $path === $outer || 0 === strpos( $path, $outer . '/' );
			}
			if ( ! $inside ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A price's value ("-10", "20-", "5-10") and label ("Under $10", "Over $20", "$5–$10").
	 *
	 * @param float|null $min Lowest price.
	 * @param float|null $max Highest price.
	 * @return string[]
	 */
	private static function price_reading( ?float $min, ?float $max ): array {
		$value = self::number( $min ) . '-' . self::number( $max );
		if ( null === $min ) {
			return array( $value, 'Under ' . self::money( (float) $max ) );
		}
		if ( null === $max ) {
			return array( $value, 'Over ' . self::money( $min ) );
		}
		return array( $value, self::money( $min ) . "\u{2013}" . self::money( $max ) );
	}

	/**
	 * A number as briefly as it can be written ("10", "9.5"), or '' for none.
	 *
	 * @param float|null $number A number.
	 */
	private static function number( ?float $number ): string {
		return null === $number ? '' : rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * An amount as the shop shows it: "$10", "$9.50".
	 *
	 * @param float $amount An amount.
	 */
	private static function money( float $amount ): string {
		return '$' . ( floor( $amount ) === $amount ? number_format( $amount, 0, '.', '' ) : number_format( $amount, 2, '.', '' ) );
	}

	/**
	 * Whether a word or phrase is one the second pass knows.
	 *
	 * @param string $phrase Normalised words.
	 */
	private static function is_phrase( string $phrase ): bool {
		foreach ( self::phrases()[ explode( ' ', $phrase )[0] ] ?? array() as $known ) {
			if ( implode( ' ', $known['words'] ) === $phrase ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Every phrase the second pass knows (the facets' in FACETS order, the sort phrases, the noise and keyword
	 * phrases), by first word.
	 *
	 * @return array<string, array<int, array{words: string[], length: int, order: int, kind: string, value: string}>>
	 */
	private static function phrases(): array {
		if ( null !== self::$phrases ) {
			return self::$phrases;
		}
		$meanings = array();
		foreach ( OOBJ_SEARCH_Vocabulary::FACETS as $facet ) {
			foreach ( OOBJ_SEARCH_Vocabulary::phrases( $facet ) as $phrase => $value ) {
				$meanings[] = array( (string) $phrase, $facet, $value );
			}
		}
		$others = array(
			array( OOBJ_SEARCH_Vocabulary::PRICE_LOW_PHRASES, 'sort', 'price' ),
			array( OOBJ_SEARCH_Vocabulary::PRICE_HIGH_PHRASES, 'sort', 'price-desc' ),
			array( self::NOISE_PHRASES, 'noise', '' ),
			array( self::KEYWORD_PHRASES, 'keywords', '' ),
		);
		foreach ( $others as list( $phrases, $kind, $value ) ) {
			foreach ( $phrases as $phrase ) {
				$meanings[] = array( OOBJ_SEARCH_Text::normalise( $phrase ), $kind, $value );
			}
		}
		self::$phrases = array();
		foreach ( $meanings as $order => list( $phrase, $kind, $value ) ) {
			$words                        = explode( ' ', $phrase );
			self::$phrases[ $words[0] ][] = array(
				'words'  => $words,
				'length' => strlen( $phrase ),
				'order'  => $order,
				'kind'   => $kind,
				'value'  => $value,
			);
		}
		return self::$phrases;
	}

	/**
	 * STOP_WORDS as keys.
	 *
	 * @return array<string, bool>
	 */
	private static function stop_words(): array {
		if ( null === self::$stop_words ) {
			self::$stop_words = array_fill_keys( OOBJ_SEARCH_Vocabulary::STOP_WORDS, true );
		}
		return self::$stop_words;
	}
}
