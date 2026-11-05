<?php
/**
 * OOBJ_SEARCH_Query_Parser: each rule, its edges and the ambiguities it settles.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class QueryParserTest extends TestCase {

	/**
	 * A query's reading, briefly: facets (values joined by ","), price "min-max", sort, keywords, product number.
	 *
	 * @param string $text A query.
	 */
	private static function read( string $text ): array {
		$query   = ( new OOBJ_SEARCH_Query_Parser() )->parse( $text );
		$reading = array();
		foreach ( $query->facets as $facet => $values ) {
			$reading[ $facet ] = implode( ',', $values );
		}
		if ( null !== $query->min_price || null !== $query->max_price ) {
			$reading['price'] = $query->min_price . '-' . $query->max_price;
		}
		if ( null !== $query->sort ) {
			$reading['sort'] = $query->sort;
		}
		if ( '' !== $query->keywords ) {
			$reading['keywords'] = $query->keywords;
		}
		if ( null !== $query->product_number ) {
			$reading['number'] = $query->product_number;
		}
		return $reading;
	}

	/**
	 * A query's parts as "kind:value:label:words".
	 *
	 * @param string $text A query.
	 */
	private static function parts( string $text ): array {
		return array_map(
			fn( $part ) => implode( ':', array( $part['kind'], $part['value'], $part['label'], $part['words'] ) ),
			( new OOBJ_SEARCH_Query_Parser() )->parse( $text )->parts
		);
	}

	public function test_the_example() {
		$this->assertSame(
			array(
				'sort:price:Cheapest first:cheap',
				'material:titanium:Titanium:titanium',
				'fitting:threadless:Threadless (push pin):threadless',
				'category:septum-daith-clickers-and-rings:Septum:septum',
				'gauge:1.2:16g (1.2mm):16g',
				'price:-10:Under $10:under $10',
			),
			self::parts( 'Cheap titanium threadless septum 16g under $10' )
		);
		$this->assertSame(
			array(
				'material' => 'titanium',
				'fitting'  => 'threadless',
				'category' => 'septum-daith-clickers-and-rings',
				'gauge'    => '1.2',
				'price'    => '-10',
				'sort'     => 'price',
			),
			self::read( 'cheap titanium threadless septum 16g under $10' )
		);
	}

	/**
	 * @dataProvider prices
	 */
	public function test_prices( string $text, array $reading, string $label ) {
		$this->assertSame( $reading, self::read( $text ) );
		$prices = array_values( array_filter( self::parts( $text ), fn( $part ) => 0 === strpos( $part, 'price:' ) ) );
		$this->assertSame( $label, explode( ':', $prices[0] ?? 'price:::' )[2] );
	}

	public static function prices(): array {
		return array(
			'under'                    => array(
				'studs under $10',
				array(
					'price'    => '-10',
					'keywords' => 'studs',
				),
				'Under $10',
			),
			'under, bare number'       => array( 'under 10', array( 'price' => '-10' ), 'Under $10' ),
			'below'                    => array( 'below $10', array( 'price' => '-10' ), 'Under $10' ),
			'less than'                => array( 'less than 10', array( 'price' => '-10' ), 'Under $10' ),
			'up to'                    => array( 'up to $10', array( 'price' => '-10' ), 'Under $10' ),
			'upto'                     => array( 'upto 10', array( 'price' => '-10' ), 'Under $10' ),
			'max'                      => array( 'max 10', array( 'price' => '-10' ), 'Under $10' ),
			'cheaper than'             => array( 'cheaper than $10', array( 'price' => '-10' ), 'Under $10' ),
			'less-than sign'           => array( '< 10', array( 'price' => '-10' ), 'Under $10' ),
			'less-than sign, attached' => array( '<10', array( 'price' => '-10' ), 'Under $10' ),
			'less-than with dollar'    => array( '<$10', array( 'price' => '-10' ), 'Under $10' ),
			'dollar alone'             => array( '$10', array( 'price' => '-10' ), 'Under $10' ),
			'dollar after'             => array( '10$', array( 'price' => '-10' ), 'Under $10' ),
			'dollars'                  => array( '10 dollars', array( 'price' => '-10' ), 'Under $10' ),
			'bucks'                    => array( '10 bucks', array( 'price' => '-10' ), 'Under $10' ),
			'aud'                      => array( '10 aud', array( 'price' => '-10' ), 'Under $10' ),
			'aud attached'             => array( '10aud', array( 'price' => '-10' ), 'Under $10' ),
			'dollar and aud'           => array( '$10 aud', array( 'price' => '-10' ), 'Under $10' ),
			'or less'                  => array( '$10 or less', array( 'price' => '-10' ), 'Under $10' ),
			'and under, bare'          => array( '10 and under', array( 'price' => '-10' ), 'Under $10' ),
			'max after'                => array( '$10 max', array( 'price' => '-10' ), 'Under $10' ),
			'cents'                    => array( 'under $9.50', array( 'price' => '-9.5' ), 'Under $9.50' ),
			'over'                     => array( 'over $20', array( 'price' => '20-' ), 'Over $20' ),
			'above'                    => array( 'above 20', array( 'price' => '20-' ), 'Over $20' ),
			'more than'                => array( 'more than 20', array( 'price' => '20-' ), 'Over $20' ),
			'at least'                 => array( 'at least $20', array( 'price' => '20-' ), 'Over $20' ),
			'from'                     => array( 'from $20', array( 'price' => '20-' ), 'Over $20' ),
			'greater-than sign'        => array( '>20', array( 'price' => '20-' ), 'Over $20' ),
			'or more'                  => array( '$20 or more', array( 'price' => '20-' ), 'Over $20' ),
			'and up'                   => array( '20 and up', array( 'price' => '20-' ), 'Over $20' ),
			'plus'                     => array( '$20 plus', array( 'price' => '20-' ), 'Over $20' ),
			'range'                    => array( '$5-10', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'range, both dollars'      => array( '$5-$10', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'range, dollars after'     => array( '5-10 dollars', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'range, spaced dash'       => array( '$5 - $10', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'range, en dash'           => array( "\$5\u{2013}\$10", array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'range with to'            => array( '5 to 10 dollars', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'range with to, dollar'    => array( '$5 to 10', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'between'                  => array( 'between 5 and 10', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'between, dash'            => array( 'between 5-10', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'from to'                  => array( 'from $5 to $10', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'range backwards'          => array( '$10-5', array( 'price' => '5-10' ), "\$5\u{2013}\$10" ),
			'range with cents'         => array( '$2.50-$7.50', array( 'price' => '2.5-7.5' ), "\$2.50\u{2013}\$7.50" ),
			'both bounds'              => array( 'over $5 under $10', array( 'price' => '5-10' ), 'Over $5' ),
			'the tighter bound'        => array( 'under $10 under $8', array( 'price' => '-8' ), 'Under $10' ),
			'the higher lowest'        => array( 'over 5 over 8', array( 'price' => '8-' ), 'Over $5' ),
		);
	}

	/**
	 * @dataProvider not_prices
	 */
	public function test_not_prices( string $text, array $reading ) {
		$this->assertSame( $reading, self::read( $text ) );
	}

	public static function not_prices(): array {
		return array(
			// A bare number is only a price after a price word or with money: "16" here could be anything.
			'bare number'               => array(
				'16 tunnel',
				array(
					'category' => 'ear-cartilage/tunnels-plugs-tapers',
					'keywords' => '16',
				),
			),
			'bare range'                => array( '5-10', array( 'keywords' => '5-10' ) ),
			'bare to'                   => array( '5 to 10', array( 'keywords' => '5 10' ) ),
			'mm is never a price'       => array( '10mm', array( 'size' => '10' ) ),
			// "under" can't take the size, so it's left as a word.
			'under a size'              => array(
				'under 10mm',
				array(
					'size'     => '10',
					'keywords' => 'under',
				),
			),
			'under a spaced size'       => array(
				'under 10 mm',
				array(
					'size'     => '10',
					'keywords' => 'under',
				),
			),
			'a count'                   => array( '10 pack', array( 'keywords' => '10 pack' ) ),
			'a range of sizes'          => array(
				'from 5 to 10mm',
				array(
					'size'     => '10',
					'keywords' => 'from 5',
				),
			),
			'a range to a size'         => array(
				'$5 to 10mm',
				array(
					'size'     => '10',
					'keywords' => '$5',
				),
			),
			'a gauge after from'        => array(
				'from 16g',
				array(
					'gauge'    => '1.2',
					'keywords' => 'from',
				),
			),
			'a price word alone'        => array( 'under', array( 'keywords' => 'under' ) ),
			'from alone'                => array(
				'gold from',
				array(
					'colour'   => 'gold',
					'keywords' => 'from',
				),
			),
			'between with no second'    => array(
				'between $5 and gold',
				array(
					'colour'   => 'gold',
					'price'    => '-5',
					'keywords' => 'between',
				),
			),
			'money then a bare number'  => array(
				'$5 10',
				array(
					'price'    => '-5',
					'keywords' => '10',
				),
			),
			'a bare number then money'  => array(
				'5 $10',
				array(
					'price'    => '-10',
					'keywords' => '5',
				),
			),
			'dollars alone'             => array( 'dollars', array( 'keywords' => 'dollars' ) ),
			'too many digits'           => array( '$1234567', array( 'keywords' => '$1234567' ) ),
			'a dangling less-than sign' => array( '< gold', array( 'colour' => 'gold' ) ),
		);
	}

	public function test_sort() {
		$this->assertSame( array( 'sort' => 'price' ), self::read( 'cheapest' ) );
		$this->assertSame( array( 'sort' => 'price' ), self::read( 'price low to high' ) );
		$this->assertSame( array( 'sort' => 'price-desc' ), self::read( 'Most expensive' ) );
		$this->assertSame( array( 'sort:price-desc:Most expensive first:most expensive' ), self::parts( 'most expensive' ) );
		// The first sort in the query wins; both are parts.
		$this->assertSame( array( 'sort' => 'price-desc' ), self::read( 'expensive cheap' ) );
		$this->assertCount( 2, self::parts( 'expensive cheap' ) );
		$this->assertSame(
			array(
				'colour' => 'gold',
				'price'  => '-10',
				'sort'   => 'price',
			),
			self::read( 'cheap under $10 gold' )
		);
	}

	/**
	 * @dataProvider gauges
	 */
	public function test_gauges( string $text, array $reading ) {
		$this->assertSame( $reading, self::read( $text ) );
	}

	public static function gauges(): array {
		return array(
			'16g'                     => array( '16g', array( 'gauge' => '1.2' ) ),
			'16G'                     => array( '16G', array( 'gauge' => '1.2' ) ),
			'16 g'                    => array( '16 g', array( 'gauge' => '1.2' ) ),
			'16ga'                    => array( '16ga', array( 'gauge' => '1.2' ) ),
			'18ga'                    => array( '18ga', array( 'gauge' => '1' ) ),
			'16 gauge'                => array( '16 gauge', array( 'gauge' => '1.2' ) ),
			'16 guage'                => array( '16 guage', array( 'gauge' => '1.2' ) ),
			'16-gauge'                => array( '16-gauge', array( 'gauge' => '1.2' ) ),
			'16gauge'                 => array( '16gauge', array( 'gauge' => '1.2' ) ),
			'gauge 16'                => array( 'gauge 16', array( 'gauge' => '1.2' ) ),
			'00g'                     => array( '00g', array( 'gauge' => '10' ) ),
			'0g'                      => array( '0g', array( 'gauge' => '8' ) ),
			'2g'                      => array( '2g', array( 'gauge' => '6' ) ),
			'1.2mm'                   => array( '1.2mm', array( 'gauge' => '1.2' ) ),
			'1.20 mm'                 => array( '1.20 mm', array( 'gauge' => '1.2' ) ),
			'GAUGE_MAX_MM is a gauge' => array( '2.5mm', array( 'gauge' => '2.5' ) ),
			'16g/1.2mm is one'        => array( '16g/1.2mm', array( 'gauge' => '1.2' ) ),
			'1.2mm (16g) is one'      => array( '1.2mm (16g)', array( 'gauge' => '1.2' ) ),
			'0g 8mm is one'           => array( '0g 8mm', array( 'gauge' => '8' ) ),
			'8mm 0g is one'           => array( '8mm 0g', array( 'gauge' => '8' ) ),
			'gauge and length'        => array(
				'16g 8mm',
				array(
					'gauge' => '1.2',
					'size'  => '8',
				),
			),
			'two gauges'              => array( '14g/16g', array( 'gauge' => '1.6,1.2' ) ),
			'unknown gauge'           => array( '15g', array( 'keywords' => '15g' ) ),
			'unknown spaced gauge'    => array( '15 gauge', array( 'keywords' => '15 gauge' ) ),
			'unknown gauge after'     => array( 'gauge 15', array( 'keywords' => 'gauge 15' ) ),
			'gauge word alone'        => array( 'gauge', array( 'keywords' => 'gauge' ) ),
			'g alone'                 => array( 'g 16', array( 'keywords' => 'g 16' ) ),
			'gauge with mm'           => array( 'gauge 10mm', array( 'gauge' => '10' ) ),
			'mm with gauge after'     => array( '10mm gauge', array( 'gauge' => '10' ) ),
			'stretching: plugs'       => array(
				'10mm plugs',
				array(
					'gauge'    => '10',
					'category' => 'ear-cartilage/tunnels-plugs-tapers',
				),
			),
			'stretching: before'      => array(
				'tunnels 12mm',
				array(
					'category' => 'ear-cartilage/tunnels-plugs-tapers',
					'gauge'    => '12',
				),
			),
		);
	}

	public function test_gauge_labels() {
		$this->assertSame( array( 'gauge:1.2:16g (1.2mm):16 gauge' ), self::parts( '16 gauge' ) );
		$this->assertSame( array( 'gauge:1.2:16g (1.2mm):16g 1.2mm' ), self::parts( '16g/1.2mm' ) );
		$this->assertSame( array( 'gauge:1.5:1.5mm:1.5 mm' ), self::parts( '1.5 mm' ) );
		$this->assertSame( array( 'gauge:12:12mm:12mm', 'category:ear-cartilage/tunnels-plugs-tapers:Tunnels, plugs and tapers:tunnel' ), self::parts( '12mm tunnel' ) );
	}

	/**
	 * @dataProvider sizes
	 */
	public function test_sizes( string $text, array $reading ) {
		$this->assertSame( $reading, self::read( $text ) );
	}

	public static function sizes(): array {
		return array(
			'8mm'                 => array( '8mm', array( 'size' => '8' ) ),
			'8 mm'                => array( '8 mm', array( 'size' => '8' ) ),
			'8mm bar'             => array(
				'8mm bar',
				array(
					'size'     => '8',
					'keywords' => 'bar',
				),
			),
			'10mm ring'           => array(
				'10mm ring',
				array(
					'size'     => '10',
					'keywords' => 'ring',
				),
			),
			'decimal'             => array( '6.5mm', array( 'size' => '6.5' ) ),
			'trailing zero'       => array( '8.0mm', array( 'size' => '8' ) ),
			'just over the gauge' => array( '2.6mm', array( 'size' => '2.6' ) ),
			'two sizes'           => array( '8mm or 10mm', array( 'size' => '8,10' ) ),
			'zero is nothing'     => array( '0mm', array( 'keywords' => '0mm' ) ),
			'no number'           => array( 'mm', array( 'keywords' => 'mm' ) ),
			'size then mm word'   => array(
				'8 mm hoop',
				array(
					'size'     => '8',
					'keywords' => 'hoop',
				),
			),
		);
		// A size next to a gauge of another thickness stays a size: covered by "gauge and length".
	}

	public function test_size_label() {
		$this->assertSame( array( 'size:8:8mm:8 mm', 'colour:gold:Gold:gold' ), self::parts( '8 mm gold' ) );
	}

	public function test_product_numbers() {
		$this->assertSame( array( 'number' => '966' ), self::read( '966' ) );
		$this->assertSame( array( 'number' => '4019' ), self::read( ' #4019 ' ) );
		$this->assertSame( array( 'number' => '123456' ), self::read( '123456' ) );
		$this->assertSame( array(), self::parts( '5043' ) );
		// Too short, too long, or not alone: words.
		$this->assertSame( array( 'keywords' => '12' ), self::read( '12' ) );
		$this->assertSame( array( 'keywords' => '1234567' ), self::read( '1234567' ) );
		$this->assertSame( array( 'keywords' => 'sku 966' ), self::read( 'sku 966' ) );
		// A number the vocabulary knows is that.
		$this->assertSame( array( 'material' => 'sterling-silver' ), self::read( '925' ) );
	}

	/**
	 * @dataProvider phrases
	 */
	public function test_phrases( string $text, array $reading ) {
		$this->assertSame( $reading, self::read( $text ) );
	}

	public static function phrases(): array {
		return array(
			'rose gold, not gold'           => array( 'rose gold', array( 'colour' => 'rose-gold' ) ),
			'rose gold and gold'            => array( 'rose gold or gold', array( 'colour' => 'rose-gold,gold' ) ),
			// One part per word: gold plated is the material, and gold isn't also a colour.
			'gold plated'                   => array(
				'gold plated hoop',
				array(
					'material' => 'gold-plated',
					'keywords' => 'hoop',
				),
			),
			'rose gold plated'              => array( 'rose gold plated', array( 'colour' => 'rose-gold' ) ),
			'gold'                          => array( 'gold', array( 'colour' => 'gold' ) ),
			'golden is a phrase, too'       => array( 'golden', array( 'colour' => 'gold' ) ),
			'goldfish is not gold'          => array( 'goldfish', array( 'keywords' => 'goldfish' ) ),
			'black or gold'                 => array( 'black or gold', array( 'colour' => 'black,gold' ) ),
			'a value once'                  => array( 'gold gold', array( 'colour' => 'gold' ) ),
			'longest across facets'         => array( 'septum retainer', array( 'category' => 'retainers-hiders' ) ),
			'leftmost of equal phrases'     => array(
				'nose ring ring',
				array(
					'category' => 'nose',
					'keywords' => 'ring',
				),
			),
			'overlapping phrases'           => array( 'flat back earrings', array( 'category' => 'ear-cartilage/labret-flat-base' ) ),
			'casing'                        => array(
				'TiTaNiUm ThReAdLeSs',
				array(
					'material' => 'titanium',
					'fitting'  => 'threadless',
				),
			),
			'punctuation'                   => array(
				'Titanium, threadless; (septum)!',
				array(
					'material' => 'titanium',
					'fitting'  => 'threadless',
					'category' => 'septum-daith-clickers-and-rings',
				),
			),
			'hyphens'                       => array( 'push-pin', array( 'fitting' => 'threadless' ) ),
			'typo in the vocabulary'        => array(
				'tounge titanuim',
				array(
					'category' => 'tongue',
					'material' => 'titanium',
				),
			),
			'misspelt earrings'             => array( 'earings', array( 'category' => 'ear-cartilage|earrings-post-and-hoops' ) ),
			'misspelt navel'                => array( 'naval', array( 'category' => 'navel' ) ),
			'stop words dropped'            => array( 'i want some nice jewellery for my nose', array( 'category' => 'nose' ) ),
			'leftover words kept'           => array( 'spike labret chain', array( 'keywords' => 'spike labret chain' ) ),
			'noise phrase'                  => array( 'gold coast piercing', array() ),
			'for sale is not on sale'       => array( 'nose rings for sale', array( 'category' => 'nose' ) ),
			'sale is on sale'               => array( 'nose rings sale', array( 'category' => 'nose,sale-items' ) ),
			'keyword phrase'                => array( 'butterfly back', array( 'keywords' => 'butterfly back' ) ),
			'butterfly alone'               => array( 'butterfly', array( 'theme' => 'animal' ) ),
			'symbols only'                  => array( '* / -', array() ),
			'narrower category wins'        => array( 'helix earrings', array( 'category' => 'ear-cartilage' ) ),
			'sub-category wins'             => array( 'belly ring dangle belly ring', array( 'category' => 'navel/dangle-belly-rings' ) ),
			'unrelated categories both'     => array( 'nose septum', array( 'category' => 'nose,septum-daith-clickers-and-rings' ) ),
			'a multi-category value inside' => array( 'daith septum', array( 'category' => 'septum-daith-clickers-and-rings' ) ),
		);
	}

	public function test_parts_in_query_order() {
		$this->assertSame(
			array(
				'colour:black:Black:black',
				'category:nose:Nose:nose ring',
				'size:8:8mm:8mm',
				'price:5-10:$5–$10:between $5 and $10',
			),
			self::parts( 'black nose ring 8mm between $5 and $10' )
		);
		// A category narrowed out of the facet is still a part.
		$this->assertSame(
			array( 'category:ear-cartilage:Ear and cartilage:helix', 'category:ear-cartilage|earrings-post-and-hoops:Earrings:earrings' ),
			self::parts( 'helix earrings' )
		);
	}

	/**
	 * @dataProvider without
	 */
	public function test_without( string $text, array $rest ) {
		$query = ( new OOBJ_SEARCH_Query_Parser() )->parse( $text );
		$this->assertSame( $rest, array_map( array( $query, 'without' ), array_keys( $query->parts ) ) );
	}

	public static function without(): array {
		return array(
			'each part'      => array(
				'cheap titanium threadless septum 16g under $10',
				array(
					'titanium threadless septum 16g under $10',
					'cheap threadless septum 16g under $10',
					'cheap titanium septum 16g under $10',
					'cheap titanium threadless 16g under $10',
					'cheap titanium threadless septum under $10',
					'cheap titanium threadless septum 16g',
				),
			),
			'repeated words' => array( 'gold ring gold', array( 'ring gold', 'gold ring' ) ),
			'inside another' => array( 'rose gold gold', array( 'gold', 'rose gold' ) ),
			'a merged gauge' => array( '16g/1.2mm gold', array( 'gold', '16g 1.2mm' ) ),
			'a range'        => array( 'hoops $5 - $10', array( 'hoops' ) ),
			'stop words'     => array( 'a nose ring for me', array( 'a for me' ) ),
		);
	}

	public function test_empty_and_garbage() {
		foreach ( array( '', '   ', '!!!', '<b></b>', "\xff\xfe\xfd", str_repeat( '-', 50 ) ) as $text ) {
			$query = ( new OOBJ_SEARCH_Query_Parser() )->parse( $text );
			$this->assertSame( array(), $query->facets, $text );
			$this->assertSame( array(), $query->parts, $text );
			$this->assertSame( '', $query->keywords, $text );
			$this->assertNull( $query->min_price );
			$this->assertNull( $query->max_price );
			$this->assertNull( $query->sort );
			$this->assertNull( $query->product_number );
			$this->assertFalse( $query->understood() );
		}
	}

	public function test_the_query_keeps_its_text() {
		$query = ( new OOBJ_SEARCH_Query_Parser() )->parse( 'Rose  Gold!' );
		$this->assertSame( 'Rose  Gold!', $query->text );
		$this->assertSame( 'rose gold', $query->normalised );
		$this->assertTrue( $query->understood() );
	}

	public function test_a_parser_reads_one_query_after_another() {
		$parser = new OOBJ_SEARCH_Query_Parser();
		$parser->parse( 'gold 16g under $10 cheap' );
		$this->assertSame( array( 'colour' => array( 'black' ) ), $parser->parse( 'black' )->facets );
		$this->assertSame( 'ring', $parser->parse( 'ring' )->keywords );
	}

	public function test_fast() {
		if ( 'phpdbg' === PHP_SAPI || extension_loaded( 'xdebug' ) || extension_loaded( 'pcov' ) ) {
			$this->markTestSkipped( 'Timed without a coverage driver.' );
		}
		$parser = new OOBJ_SEARCH_Query_Parser();
		$parser->parse( 'warm up' );
		$start = hrtime( true );
		for ( $i = 0; $i < 200; $i++ ) {
			$parser->parse( 'cheap titanium threadless septum 16g under $10' );
		}
		$this->assertLessThan( 1.0, ( hrtime( true ) - $start ) / 1e6 / 200, 'ms per query' );
	}
}
