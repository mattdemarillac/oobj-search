<?php
/**
 * Class OOBJ_SEARCH_Product_Facts.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

/**
 * The facts a product has (OOBJ_SEARCH_Vocabulary::FACT_FACETS), so a search for "titanium" finds titanium products.
 * Plain PHP, no WordPress.
 *
 * A wrong fact puts a product in a search it doesn't belong in, which is worse than a missing one, so each facet
 * reads only the sources that are reliable for it:
 * - Name and tags: every facet. Theme only from these: descriptions mention skulls and flowers in passing.
 * - Attribute values (the variations): material, fitting, colour, gauge and size.
 * - Description lines that start with a label ("Material:", "Colours:", "Gauge/ Bar Thickness:", "Bar Length:",
 *   "Style:", or a "Sizes:" heading the lines under it): their own facet. Prose ("We offer...") isn't a list.
 * - Free description text: only its first sentence, only for a material nothing else named ("crafted from 316L
 *   Steel"). Later sentences compare ("easier than internally threaded jewellery").
 * - The vocabulary's 'categories': only when the product's own words named nothing in that facet (a silicone disc
 *   in the titanium category stays silicone).
 *
 * Within a facet the longest phrase wins and shorter phrases inside it don't count ("rose gold" isn't also gold).
 * PHRASES adds the shop's own wording and phrases that name nothing ("evil eye", "titanium ip" over steel).
 *
 * Material: a finish isn't the material: "IP Titanium", "Gold Titanium", "Titanium Anodised over 316L Steel" are
 * steel. "Implant grade" alone says nothing (the shop calls steel that too). "Steel" in a variation is its colour.
 * Silicone beside another material is the O-rings ("Material: Acrylic | Silicone" on tapers).
 *
 * Colour: colour words, and a colour before a stone or finish ("pink cz", "gold pvd", "anodised black"). In a list
 * of colours (variations, "Colours:") steel and titanium are silver and "Rose" is pink. A crystal or CZ with no
 * colour is clear, unless the product lists its colours. A "Material:" line gives colours only through a finish.
 * Lists are read part by part ("Gold | Steel"); names whole ("Crescent/ Pincher").
 *
 * Gauge: "16g", "16ga", "16 gauge" (GAUGES), or a thickness in mm ("Gauge: 1.2mm", "2mm thick") up to GAUGE_MAX_MM.
 * A mm next to a gauge ("1.2mm (16G)", "1.6mm/ 14GA") is the same thickness: the gauge counts, or on stretching
 * jewellery (sold by mm) the mm. There every mm is a gauge except lengths. Thicker gauges on other jewellery need
 * their own label or a length after them ("8G(3mm), 12mm"): alone, "Black 3mm (8G)" is a ball stud's ball.
 *
 * Size: a length or diameter a shopper searches for, above GAUGE_MAX_MM (smaller ones read as gauges): "8mm ring",
 * "Bar Length: 6mm", and the bare mm after a gauge in a variation ("16G, 8mm, 3*3mm" is an 8mm bar). Ball, gem, top
 * and charm sizes don't count: they sit beside the bar length, and "8mm labret" means the bar. They count only on
 * tops, balls and studs sold on their own, which have no other size ("4mm dermal top").
 */
class OOBJ_SEARCH_Product_Facts {

	/**
	 * The shop's own wording, by facet: phrase => value, '' for a phrase that names nothing (so the shorter phrases
	 * inside it don't count either). Added to, or overriding, the vocabulary's phrases.
	 */
	const PHRASES = array(
		'material' => array(
			'titanium steel'            => 'titanium',
			'ti6al4v'                   => 'titanium',
			'implant grade'             => '',
			'gemstone'                  => '',
			'gemstones'                 => '',
			'semi precious'             => 'stone',
			'titanium plated'           => '',
			'titanium plating'          => '',
			'titanium coated'           => '',
			'titanium coating'          => '',
			'titanium ip'               => '',
			'ip titanium'               => '',
			'ion plated titanium'       => '',
			'titanium anodised'         => '',
			'titanium anodized'         => '',
			'anodised titanium'         => '',
			'anodized titanium'         => '',
			'anodised titanium colours' => '',
			'anodized titanium colours' => '',
			'titanium anodising'        => '',
			'titanium anodizing'        => '',
			'titanium ion'              => '',
			'titanium over'             => '',
			'titanium pvd'              => '',
			'titanium colour'           => '',
			'titanium colours'          => '',
			'titanium coloured'         => '',
			'titanium color'            => '',
			'titanium colors'           => '',
			'titanium colored'          => '',
			'colourful titanium'        => '',
			'vacuum plated'             => '',
			'gold ip'                   => 'gold-plated',
			'ip gold'                   => 'gold-plated',
			'ip rose gold'              => 'gold-plated',
			'ip yellow'                 => 'gold-plated',
			'gold ion plated'           => 'gold-plated',
			'gold ion plating'          => 'gold-plated',
			'gold plate'                => 'gold-plated',
			'flexible'                  => '',
			'plastic'                   => '',
			'silicone o ring'           => '',
			'silicone o rings'          => '',
			'silicon o ring'            => '',
			'silicon o rings'           => '',
			'opalite stone'             => '',
			'opal stone'                => '',
			'opal stones'               => '',
			'wine glass'                => '',
			'cocktail glass'            => '',
			'martini glass'             => '',
		),
		'fitting'  => array(
			'press fit'   => '',
			'pressed fit' => '',
		),
		'colour'   => array(
			'anodised'       => '',
			'anodized'       => '',
			'great white'    => '',
			'siam'           => 'red',
			'burgundy'       => 'red',
			'rhodium'        => 'silver',
			'platinum'       => 'silver',
			'silvertone'     => 'silver',
			'ip yellow'      => 'gold',
			'yellow ip'      => 'gold',
			'cz'             => self::IMPLIED_CLEAR,
			'crystal'        => self::IMPLIED_CLEAR,
			'crystals'       => self::IMPLIED_CLEAR,
			'cubic zirconia' => self::IMPLIED_CLEAR,
			'diamante'       => self::IMPLIED_CLEAR,
			'rhinestone'     => self::IMPLIED_CLEAR,
			'rhinestones'    => self::IMPLIED_CLEAR,
			'zircon'         => self::IMPLIED_CLEAR,
			'zirconia'       => self::IMPLIED_CLEAR,
		),
		'theme'    => array(
			'evil eye'         => '',
			'cat eye'          => '',
			'cats eye'         => '',
			'crescent pincher' => '',
			'snake bite'       => '',
			'snake bites'      => '',
			'spider bite'      => '',
			'spider bites'     => '',
			'snake chain'      => '',
			'butterfly back'   => '',
			'butterfly backs'  => '',
			'fish hook'        => '',
		),
	);

	/**
	 * A stone with no colour named: clear only if the product doesn't list its colours.
	 */
	const IMPLIED_CLEAR = '~clear';

	/**
	 * Material words that in a variation name its colour, not its material ("Steel/ Clear" on a titanium top).
	 */
	const LIST_NOT_MATERIALS = array( 'steel' => '' );

	/**
	 * Colour words only in a list of colours: there "Steel" is the plain metal's colour and "Rose" a pink.
	 */
	const LIST_COLOURS = array(
		'steel'    => 'silver',
		'titanium' => 'silver',
		'rose'     => 'pink',
	);

	/**
	 * Finishes that take the colour before them ("gold pvd", "black titanium"): the colour counts, the word doesn't.
	 */
	const FINISHED = array( 'steel', 'titanium', 'pvd', 'ip', 'anodised', 'anodized', 'plated', 'plating', 'plate', 'tone', 'coated' );

	/**
	 * Finishes that also take the colour after them ("anodised gold", "ip black").
	 */
	const FINISHES = array( 'pvd', 'ip', 'anodised', 'anodized' );

	/**
	 * Stones: they take the colour before them ("pink cz"), and a mm next to one is the stone's size.
	 */
	const GEMS = array( 'cz', 'crystal', 'crystals', 'cubic zirconia', 'diamante', 'rhinestone', 'rhinestones', 'zircon', 'zirconia', 'gem', 'gems', 'stone', 'stones', 'pearl', 'pearls', 'opal', 'opals' );

	/**
	 * Words after a colour that make it not the product's ("black o rings").
	 */
	const NOT_COLOURED = array( 'o ring', 'o rings' );

	/**
	 * Words after (or before) a mm that make it a length or diameter.
	 */
	const SIZE_WORDS = array( 'bar', 'bars', 'barbell', 'barbells', 'ring', 'rings', 'hoop', 'hoops', 'length', 'lengths', 'long', 'diameter', 'diam', 'inner', 'internal', 'id', 'wearable', 'usable', 'useable', 'post', 'posts', 'labret', 'labrets', 'horseshoe', 'horseshoes', 'cbr', 'clicker', 'clickers', 'segment', 'captive', 'circular', 'curved', 'banana', 'huggie', 'huggies', 'sleeper', 'sleepers', 'size', 'sizes' );

	/**
	 * Words after a mm that make it a thickness.
	 */
	const THICKNESS_WORDS = array( 'thick', 'thickness', 'gauge', 'guage' );

	/**
	 * Words that make a mm the whole piece's size, not the bar's.
	 */
	const WHOLE_WORDS = array( 'total', 'full', 'overall', 'whole' );

	/**
	 * Words that don't change what a mm measures ("l" and "i" stand in for "|" in some lists).
	 */
	const NEUTRAL_WORDS = array( 'pair', 'pairs', 'approx', 'approximately', 'each', 'pc', 'pcs', 'piece', 'pieces', 'to', 'and', 'or', 'x', 'only', 'single', 'set', 'sets', 'mm', 'in', 'of', 'a', 'with', 'w', 'plus', 'from', 'up', 'available', 'l', 'i' );

	/**
	 * Description labels by what their line is read for ('length': a length or diameter, even on stretching
	 * jewellery; 'description': the first sentence).
	 */
	const LABELS = array(
		'material'    => array( 'material', 'materials' ),
		'colour'      => array( 'colour', 'colours', 'color', 'colors' ),
		'gauge'       => array( 'gauge', 'gauges', 'guage', 'guages', 'thickness' ),
		'size'        => array( 'size', 'sizes', 'sizing', 'length', 'lengths', 'diameter', 'diam', 'bar', 'bars', 'ring', 'rings', 'hoop', 'hoops' ),
		'length'      => array( 'length', 'lengths', 'diameter', 'diam', 'inner', 'wearable', 'usable' ),
		'fitting'     => array( 'style', 'styles', 'threading', 'thread', 'threads', 'fitting', 'closure', 'type' ),
		'description' => array( 'description' ),
	);

	/**
	 * Other words a size label can have and still be the jewellery's size ("Ring Inner Diameter"). A size label with
	 * any other word measures something else ("Ball Size", "Butterfly Size", "Taper Length", a dangle's "Total
	 * Length"); its mm are tops.
	 */
	const SIZE_LABEL_WORDS = array( 'sizing', 'approx', 'approximately', 'inside', 'available', 'and', 'or', 'of', 'the' );

	/**
	 * Products sold as a top, ball or stud on its own: there the top's size is the size.
	 */
	const TOP_CATEGORIES = array( 'dermal-tops', 'pin-tops-pin-bases', 'earrings-post-and-hoops' );

	const TOP_NAME_WORDS = array( 'top', 'tops', 'ball', 'balls', 'bead', 'beads', 'spare', 'spares', 'attachment', 'attachments', 'charm', 'charms', 'pendant', 'pendants' );

	/**
	 * Phrase tables by context, built once.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static $phrases = array();

	/**
	 * The facts a product has.
	 *
	 * @param array $product name, short_description, description, categories (slug paths), attributes (name =>
	 *                       values), tags; any may be missing.
	 * @return array<string, string[]> Facet => sorted values, only facets with a value.
	 */
	public static function facts( array $product ): array {
		$categories = array_map( 'strval', (array) ( $product['categories'] ?? array() ) );
		$name       = OOBJ_SEARCH_Text::normalise( (string) ( $product['name'] ?? '' ) );
		$found      = array(
			'facts'      => array_fill_keys( OOBJ_SEARCH_Vocabulary::FACT_FACETS, array() ),
			'stretching' => self::in_categories( $categories, OOBJ_SEARCH_Vocabulary::STRETCHING_CATEGORIES ),
			'tops'       => array(),
			'loose'      => array(),
			'summary'    => '',
			'listed'     => false,
			'implied'    => false,
		);

		self::read_name( $found, (string) ( $product['name'] ?? '' ) );
		foreach ( (array) ( $product['tags'] ?? array() ) as $tag ) {
			self::read_name( $found, (string) $tag );
		}
		foreach ( (array) ( $product['attributes'] ?? array() ) as $key => $values ) {
			foreach ( (array) $values as $value ) {
				$gauge_key = (bool) preg_match( '/gauge|guage/i', (string) $key );
				self::read_attribute( $found, (string) $value, $gauge_key );
			}
		}
		$description = (string) ( $product['description'] ?? '' ) . "\n" . (string) ( $product['short_description'] ?? '' );
		foreach ( self::lines( $description ) as list( $types, $value ) ) {
			self::read_line( $found, $types, $value );
		}

		$facts = $found['facts'];
		if ( ! $facts['material'] ) {
			self::add( $facts['material'], self::words( 'material', $found['summary'] ) );
		}
		if ( count( array_unique( $facts['material'] ) ) > 1 ) {
			// Silicone beside another material is the O-rings or backs ("Material: Acrylic | Silicone" on tapers).
			$facts['material'] = array_diff( $facts['material'], array( 'silicone' ) );
		}
		if ( $found['implied'] && ! $found['listed'] ) {
			$facts['colour'][] = 'clear';
		}
		foreach ( array( 'material', 'fitting' ) as $facet ) {
			if ( ! $facts[ $facet ] ) {
				self::add( $facts[ $facet ], self::category_facts( $facet, $categories ) );
			}
		}
		$facts['size'] = self::sizes( $facts['size'], $found, ! $found['stretching'] && self::is_top( $categories, $name ) );

		$result = array();
		foreach ( $facts as $facet => $values ) {
			$values = array_values( array_unique( array_map( 'strval', $values ) ) );
			if ( $values ) {
				sort( $values, SORT_NATURAL );
				$result[ $facet ] = $values;
			}
		}
		return $result;
	}

	/**
	 * Reads a name or tag: every facet.
	 *
	 * @param array  $found What's found so far (facts()).
	 * @param string $text  The name or tag.
	 */
	private static function read_name( array &$found, string $text ): void {
		foreach ( array( 'material', 'fitting', 'theme' ) as $facet ) {
			self::add( $found['facts'][ $facet ], self::words( $facet, $text, false ) );
		}
		self::add_colours( $found, self::words( 'colour', $text, false ), false );
		foreach ( self::measures( $text, $found['stretching'] ) as $measure ) {
			// A stretching jewellery's name sizes it: "Acrylic Tribal Wing Hanger Pairs 3-5mm".
			if ( $found['stretching'] || ( 'gauge' === $measure['kind'] && ! $measure['thick'] ) ) {
				$found['facts']['gauge'][] = $measure['mm'];
			} elseif ( 'size' === $measure['kind'] ) {
				$found['facts']['size'][] = $measure['mm'];
			}
		}
	}

	/**
	 * Reads a variation's value, e.g. "Gold/Clear, 16G, 8mm Bar".
	 *
	 * @param array  $found     What's found so far (facts()).
	 * @param string $value     The raw value.
	 * @param bool   $gauge_key Whether the attribute is named for gauges ("pa_mm-guage-size").
	 */
	private static function read_attribute( array &$found, string $value, bool $gauge_key ): void {
		self::add( $found['facts']['material'], self::words( 'material-list', $value ) );
		self::add( $found['facts']['fitting'], self::words( 'fitting', $value ) );
		self::add_colours( $found, self::words( 'colour-list', $value ), true );
		$measures = self::measures( $value, $found['stretching'] );
		$sized    = in_array( 'size', array_column( $measures, 'kind' ), true );
		$gauged   = false;
		foreach ( $measures as $index => $measure ) {
			$where  = self::attribute_measure( $measure, $index, count( $measures ), $found['stretching'], $gauge_key, $gauged, $sized );
			$gauged = $gauged || 'gauge' === $where;
			$sized  = $sized || 'size' === $where;
			if ( in_array( $where, array( 'gauge', 'size' ), true ) ) {
				$found['facts'][ $where ][] = $measure['mm'];
			} else {
				$found[ $where ][] = $measure['mm'];
			}
		}
	}

	/**
	 * What a mm in a variation is. A bare mm right after the gauge is the length ("16G, 8mm, 3*3mm"), later ones the
	 * ball's. A first bare thickness ("1.2mm, 8mm, 3mm") or a thin bare mm in a gauge attribute is the gauge. A thick
	 * gauge with its mm is one only before a length ("8G(3mm), 12mm"); alone ("Black 3mm (8G)") it's a ball's size.
	 * Other bare mm ("Black 12mm") are loose: a length if other sources have lengths around it, else a top's size.
	 *
	 * @param array $measure    The measure (measures()).
	 * @param int   $index      Its place in the value.
	 * @param int   $count      The value's number of measures.
	 * @param bool  $stretching Whether the product is stretching jewellery.
	 * @param bool  $gauge_key  Whether the attribute is named for gauges.
	 * @param bool  $gauged     Whether the value has had a gauge.
	 * @param bool  $sized      Whether the value has had a length.
	 * @return string 'gauge', 'size', 'loose' or 'tops'.
	 */
	private static function attribute_measure( array $measure, int $index, int $count, bool $stretching, bool $gauge_key, bool $gauged, bool $sized ): string {
		$kind = $measure['thick'] && $index + 1 === $count ? 'bare' : $measure['kind'];
		$thin = (float) $measure['mm'] <= OOBJ_SEARCH_Vocabulary::GAUGE_MAX_MM;
		if ( 'gauge' === $kind || ( $stretching && 'top' !== $kind ) ) {
			return 'gauge';
		}
		if ( 'size' === $kind ) {
			return 'size';
		}
		if ( 'bare' !== $kind ) {
			return 'tops';
		}
		if ( $measure['alone'] && $gauged && ! $sized ) {
			return 'size';
		}
		if ( $thin && ( $gauge_key || ( 0 === $index && $measure['alone'] && $count > 1 ) ) ) {
			return 'gauge';
		}
		return 'loose';
	}

	/**
	 * Reads a labelled description line.
	 *
	 * @param array    $found What's found so far (facts()).
	 * @param string[] $types The label's types (LABELS keys, and 'top').
	 * @param string   $text  The raw text after the label.
	 */
	private static function read_line( array &$found, array $types, string $text ): void {
		if ( in_array( 'material', $types, true ) ) {
			self::add( $found['facts']['material'], self::words( 'material', $text ) );
			self::add( $found['facts']['colour'], self::words( 'colour-finish', $text ) );
		}
		if ( in_array( 'colour', $types, true ) ) {
			self::add_colours( $found, self::words( 'colour-list', $text ), true );
		}
		if ( in_array( 'fitting', $types, true ) ) {
			self::add( $found['facts']['fitting'], self::words( 'fitting', $text ) );
		}
		if ( in_array( 'description', $types, true ) && '' === $found['summary'] ) {
			$found['summary'] = (string) preg_split( '/(?<! [a-z])[.!?]\s+(?=[A-Z])/i', $text )[0];
		}
		if ( array_intersect( $types, array( 'gauge', 'size', 'top' ) ) ) {
			foreach ( self::measures( $text, $found['stretching'] ) as $measure ) {
				$where = self::line_measure( $measure, $types, $found['stretching'] );
				if ( 'tops' === $where ) {
					$found['tops'][] = $measure['mm'];
				} elseif ( '' !== $where ) {
					$found['facts'][ $where ][] = $measure['mm'];
				}
			}
		}
	}

	/**
	 * What a mm in a labelled line is: under a gauge label a thickness, under a size label a length (a thin bare one
	 * reads as neither), under a top's label ("Ball Size") a top's size.
	 *
	 * @param array    $measure    The measure (measures()).
	 * @param string[] $types      The label's types.
	 * @param bool     $stretching Whether the product is stretching jewellery.
	 * @return string 'gauge', 'size', 'tops' or '' for none.
	 */
	private static function line_measure( array $measure, array $types, bool $stretching ): string {
		$gauge = in_array( 'gauge', $types, true );
		$kind  = $measure['thick'] && ! $gauge ? 'bare' : $measure['kind'];
		if ( in_array( 'top', $types, true ) || 'top' === $kind ) {
			return 'gauge' === $kind ? '' : 'tops';
		}
		if ( 'gauge' === $kind ) {
			return 'gauge';
		}
		if ( $stretching ) {
			return in_array( 'length', $types, true ) ? 'size' : 'gauge';
		}
		if ( $gauge && 'size' !== $kind && (float) $measure['mm'] <= OOBJ_SEARCH_Vocabulary::GAUGE_MAX_MM ) {
			return 'gauge';
		}
		return in_array( 'size', $types, true ) || 'size' === $kind ? 'size' : '';
	}

	/**
	 * The sizes: lengths over GAUGE_MAX_MM, with the loose variation mm between them ("Black 16mm" on a "12mm - 22mm
	 * bar"); or, for a top sold on its own with no length, its top sizes.
	 *
	 * @param string[] $sizes The lengths found.
	 * @param array    $found What's found (facts()).
	 * @param bool     $top   Whether the product is a top, ball or stud sold on its own.
	 * @return string[]
	 */
	private static function sizes( array $sizes, array $found, bool $top ): array {
		$over  = fn( $mm ) => (float) $mm > OOBJ_SEARCH_Vocabulary::GAUGE_MAX_MM;
		$sizes = array_filter( $sizes, $over );
		if ( ! $sizes ) {
			return $top ? array_filter( array_merge( $found['tops'], $found['loose'] ), $over ) : array();
		}
		$low  = min( array_map( 'floatval', $sizes ) );
		$high = max( array_map( 'floatval', $sizes ) );
		foreach ( $found['loose'] as $mm ) {
			if ( (float) $mm >= $low && (float) $mm <= $high ) {
				$sizes[] = $mm;
			}
		}
		return $sizes;
	}

	/**
	 * The facts the vocabulary gives every product in the categories.
	 *
	 * @param string   $facet      'material' or 'fitting'.
	 * @param string[] $categories The product's category slug paths.
	 * @return string[]
	 */
	private static function category_facts( string $facet, array $categories ): array {
		$values = array();
		foreach ( OOBJ_SEARCH_Vocabulary::CONCEPTS[ $facet ] as $value => $concept ) {
			if ( self::in_categories( $categories, $concept['categories'] ?? array() ) ) {
				$values[] = (string) $value;
			}
		}
		return $values;
	}

	/**
	 * Adds values to a facet's list.
	 *
	 * @param string[] $facet  The facet's values so far.
	 * @param string[] $values Values to add.
	 */
	private static function add( array &$facet, array $values ): void {
		array_push( $facet, ...$values );
	}

	/**
	 * Adds colours, keeping an implied clear aside.
	 *
	 * @param array    $found   What's found so far (facts()).
	 * @param string[] $colours Colours, maybe IMPLIED_CLEAR.
	 * @param bool     $listed  Whether they're from a list of the product's colours.
	 */
	private static function add_colours( array &$found, array $colours, bool $listed ): void {
		$named = array_diff( $colours, array( self::IMPLIED_CLEAR ) );
		self::add( $found['facts']['colour'], $named );
		$found['implied'] = $found['implied'] || in_array( self::IMPLIED_CLEAR, $colours, true );
		$found['listed']  = $found['listed'] || ( $listed && $named );
	}

	/**
	 * The values named in text, longest phrase first from the left: a phrase inside a longer one found there doesn't
	 * count. A list is read part by part ("Gold | Steel" isn't "gold steel"); a name as a whole ("Crescent/ Pincher").
	 *
	 * @param string $context  A facet, or 'material-list' and 'colour-list' (a variation, a list of colours) or
	 *                         'colour-finish' (a "Material:" line).
	 * @param string $text     Text.
	 * @param bool   $in_parts Whether the text is a list, read part by part.
	 * @return string[] Values, in the order found.
	 */
	private static function words( string $context, string $text, bool $in_parts = true ): array {
		$values = array();
		foreach ( $in_parts ? (array) preg_split( '/[|,;\r\n]|\/(?!\d)|(?<!\d)\//', $text ) : array( $text ) as $part ) {
			array_push( $values, ...self::part_words( $context, OOBJ_SEARCH_Text::normalise( (string) $part ) ) );
		}
		return $values;
	}

	/**
	 * The values named in a part of a text (words()).
	 *
	 * @param string $context As words().
	 * @param string $text    Normalised text.
	 * @return string[]
	 */
	private static function part_words( string $context, string $text ): array {
		$phrases = self::phrases( $context );
		$words   = '' === $text ? array() : explode( ' ', $text );
		$count   = count( $words );
		$values  = array();
		for ( $i = 0; $i < $count; ) {
			for ( $length = min( 4, $count - $i ); $length > 0; $length-- ) {
				$phrase = implode( ' ', array_slice( $words, $i, $length ) );
				if ( isset( $phrases[ $phrase ] ) ) {
					break;
				}
			}
			if ( 0 === $length ) {
				++$i;
				continue;
			}
			if ( '' !== $phrases[ $phrase ] ) {
				$values[] = $phrases[ $phrase ];
			}
			$i += $length;
		}
		return $values;
	}

	/**
	 * A context's phrases: phrase => value ('' for none).
	 *
	 * @param string $context As words().
	 * @return array<string, string>
	 */
	private static function phrases( string $context ): array {
		if ( ! isset( self::$phrases[ $context ] ) ) {
			$facet = explode( '-', $context )[0];
			// array_replace(), not array_merge(): phrases such as "925" are integer keys.
			$phrases = array_replace( OOBJ_SEARCH_Vocabulary::phrases( $facet ), self::PHRASES[ $facet ] );
			if ( 'material-list' === $context ) {
				$phrases = array_replace( $phrases, self::LIST_NOT_MATERIALS );
			}
			if ( 'material' === $facet ) {
				// "Gold Titanium", "Rhodium Titanium": a titanium finish, mostly over steel.
				foreach ( self::colours( self::phrases( 'colour' ) ) as $colour => $value ) {
					$phrases[ $colour . ' titanium' ] = '';
				}
			}
			if ( 'colour' === $facet ) {
				$phrases = self::colour_phrases( $context, $phrases );
			}
			self::$phrases[ $context ] = $phrases;
		}
		return self::$phrases[ $context ];
	}

	/**
	 * The colour phrases for a context: with a colour before a stone or finish, and a finish before a colour.
	 *
	 * @param string                $context 'colour', 'colour-list' or 'colour-finish'.
	 * @param array<string, string> $phrases The vocabulary's and PHRASES' colour phrases.
	 * @return array<string, string>
	 */
	private static function colour_phrases( string $context, array $phrases ): array {
		if ( 'colour-list' === $context ) {
			$phrases = array_replace( $phrases, self::LIST_COLOURS );
		}
		$finishes = array();
		foreach ( self::colours( $phrases ) as $colour => $value ) {
			foreach ( array_merge( self::FINISHED, self::GEMS ) as $word ) {
				$finishes[ $colour . ' ' . $word ] = $value;
			}
			foreach ( self::FINISHES as $word ) {
				$finishes[ $word . ' ' . $colour ] = $value;
			}
			foreach ( self::NOT_COLOURED as $word ) {
				$finishes[ $colour . ' ' . $word ] = '';
			}
			// "3PCS Bioplast Bar, No Pink".
			$finishes[ 'no ' . $colour ] = '';
		}
		// A "Material:" line names colours only through a finish ("Gold PVD"), not as stones or parts.
		// The shop's own wording wins over a made-up finish ("ip yellow" is gold).
		$finishes = array_replace( $finishes, array_intersect_key( self::PHRASES['colour'], $finishes ) );
		return 'colour-finish' === $context ? $finishes : array_replace( $phrases, $finishes );
	}

	/**
	 * The phrases that name a colour.
	 *
	 * @param array<string, string> $phrases Colour phrases.
	 * @return array<string, string>
	 */
	private static function colours( array $phrases ): array {
		return array_filter( $phrases, fn( $value ) => '' !== $value && self::IMPLIED_CLEAR !== $value );
	}

	/**
	 * The measurements in a text, in order.
	 *
	 * @param string $text       Raw or normalised text.
	 * @param bool   $stretching Whether the product is stretching jewellery.
	 * @return array<int, array{kind: string, mm: string, alone: bool, thick: bool}> kind: 'gauge' (a thickness),
	 *         'size' (a length or diameter), 'top' (something else's size) or 'bare' (unsaid); alone: nothing else in
	 *         its part of the list; thick: a gauge over GAUGE_MAX_MM written with its mm, maybe a ball's size.
	 */
	private static function measures( string $text, bool $stretching ): array {
		$measures = array();
		foreach ( self::parts( $text ) as $part ) {
			$tokens = self::pair( self::tokens( OOBJ_SEARCH_Text::normalise( $part ) ), $stretching );
			$alone  = 1 === count( array_filter( $tokens, array( self::class, 'counts' ) ) );
			// A dangle's "Full Length 25mm" or "10mm total" isn't the bar's.
			$whole = (bool) array_intersect( array_column( $tokens, 1 ), self::WHOLE_WORDS );
			foreach ( array_keys( $tokens ) as $i ) {
				$measure = self::measure( $tokens, $i, $alone );
				if ( $measure && $whole && in_array( $measure['kind'], array( 'size', 'bare' ), true ) ) {
					$measure['kind'] = 'top';
				}
				if ( $measure ) {
					$measures[] = $measure;
				}
			}
		}
		return $measures;
	}

	/**
	 * The measurement a token is, if it is one.
	 *
	 * @param array<int, array> $tokens The part's tokens.
	 * @param int               $i      The token's index.
	 * @param bool              $alone  Whether it's alone in its part.
	 * @return array{kind: string, mm: string, alone: bool, thick: bool}|null As measures().
	 */
	private static function measure( array $tokens, int $i, bool $alone ): ?array {
		$token = $tokens[ $i ];
		if ( 'gauge' === $token[0] && null !== $token[1] ) {
			$kind = 'gauge';
		} elseif ( 'dim' === $token[0] ) {
			$kind = 'top';
		} elseif ( 'mm' === $token[0] ) {
			$kind = self::kind( $tokens, $i );
		} else {
			return null;
		}
		if ( 'thick' === $kind ) {
			$kind = (float) $token[1] <= OOBJ_SEARCH_Vocabulary::GAUGE_MAX_MM ? 'gauge' : 'top';
		}
		return array(
			'kind'  => $kind,
			'mm'    => $token[1],
			'alone' => $alone,
			'thick' => ! empty( $token[2] ),
		);
	}


	/**
	 * Whether a token counts against a measurement being alone in its part: a measurement or a word that says
	 * something (a colour does, "pair" doesn't).
	 *
	 * @param array $token A token (tokens()).
	 */
	private static function counts( array $token ): bool {
		return in_array( $token[0], array( 'mm', 'gauge', 'dim' ), true ) || ! ( in_array( $token[1], self::NEUTRAL_WORDS, true ) || preg_match( '/^\d/', $token[1] ) );
	}

	/**
	 * A list's parts: split at "|", ",", ";", line breaks, full stops and spaced slashes. Plain numbers take the unit
	 * and words of the part after them ("12, 14, 16mm bar"), and the words before them ("Gems 3/ 4/ 5mm").
	 *
	 * @param string $text Raw or normalised text.
	 * @return string[]
	 */
	private static function parts( string $text ): array {
		$parts = array_map( 'trim', (array) preg_split( '/\s*(?:[|,;\r\n]|\.\s|\/\s|\s\/)\s*/', $text ) );
		$count = count( $parts );
		$plain = fn( $part ) => 1 === preg_match( '/^\d+(?:\.\d+)?$/', $part );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! $plain( $parts[ $i ] ) ) {
				continue;
			}
			$last = $i;
			while ( $last < $count && $plain( $parts[ $last ] ) ) {
				++$last;
			}
			if ( $last < $count && preg_match( '/^\d+(?:\.\d+)?(\s?mm\b.*)$/i', $parts[ $last ], $unit ) ) {
				$before = $i > 0 && preg_match( '/^(.*\D)\d+(?:\.\d+)?$/', $parts[ $i - 1 ], $words ) ? $words[1] : '';
				if ( '' !== $before ) {
					$parts[ $i - 1 ] .= $unit[1];
					$parts[ $last ]   = $before . $parts[ $last ];
				}
				for ( $j = $i; $j < $last; $j++ ) {
					$parts[ $j ] = $before . $parts[ $j ] . $unit[1];
				}
			}
			$i = $last;
		}
		return $parts;
	}

	/**
	 * What a mm measures, from the first word after it (past other mm and neutral words: "6mm 12mm rings") or else
	 * the one before it ("hoop pairs 10mm", "dragon 20mm").
	 *
	 * @param array<int, array> $tokens The part's tokens.
	 * @param int               $i      The mm's index.
	 * @return string 'size', 'thick', 'top' or 'bare'.
	 */
	private static function kind( array $tokens, int $i ): string {
		$skip = array( 'mm', 'neutral' );
		$next = $i + 1;
		while ( isset( $tokens[ $next ] ) && in_array( $tokens[ $next ][0], $skip, true ) ) {
			++$next;
		}
		$word = $tokens[ $next ] ?? array( 'gauge' );
		if ( 'gauge' === $word[0] ) {
			$previous = $i - 1;
			while ( isset( $tokens[ $previous ] ) && in_array( $tokens[ $previous ][0], $skip, true ) ) {
				--$previous;
			}
			$word = $tokens[ $previous ] ?? array( 'gauge' );
		}
		if ( 'gauge' === $word[0] ) {
			return 'bare';
		}
		return 'dim' === $word[0] ? 'top' : $word[0];
	}

	/**
	 * Splits normalised text into tokens: array{0: 'gauge', 1: mm or null} for "16g" or "16 gauge" (null for a gauge
	 * GAUGES doesn't know), array{0: 'mm', 1: mm} for "8mm" (a range or list, "6-12mm", "6/8/10mm", gives each),
	 * array{0: 'dim', 1: mm} for a ball or top's "3*3mm" or "5mm x 7mm", and words: array{0: 'size'|'thick'|
	 * 'neutral'|'top', 1: word}.
	 *
	 * @param string $text Normalised text.
	 * @return array<int, array>
	 */
	private static function tokens( string $text ): array {
		// "8 mm", "20 guage" and "5mm x 7mm" are one word each.
		$text   = (string) preg_replace( '/(?<![\d.])(\d+(?:\.\d+)?) (mm|g|ga|gauge|guage)\b/', '$1$2', $text );
		$text   = (string) preg_replace( '/(?<![\d.])(\d+(?:\.\d+)?mm) x (\d+(?:\.\d+)?mm)\b/', '$1x$2', $text );
		$tokens = array();
		foreach ( '' === $text ? array() : explode( ' ', $text ) as $word ) {
			array_push( $tokens, ...self::token( $word ) );
		}
		return $tokens;
	}

	/**
	 * A word's tokens (tokens()).
	 *
	 * @param string $word A normalised word.
	 * @return array<int, array>
	 */
	private static function token( string $word ): array {
		if ( preg_match( '/^(\d{1,2})(?:g|ga|gauge|guage)$/', $word, $match ) ) {
			return array( array( 'gauge', OOBJ_SEARCH_Vocabulary::GAUGES[ $match[1] ] ?? null ) );
		}
		if ( preg_match( '/^(\d+(?:\.\d+)?)(?:mm)?[x*]\d+(?:\.\d+)?mm$/', $word, $match ) ) {
			return array( array( 'dim', OOBJ_SEARCH_Vocabulary::mm( $match[1] ) ) );
		}
		if ( preg_match( '/^((?:\d+(?:\.\d+)?[\/-])*\d+(?:\.\d+)?)mm$/', $word, $match ) ) {
			return array_map( fn( $mm ) => array( 'mm', OOBJ_SEARCH_Vocabulary::mm( $mm ) ), explode( '/', strtr( $match[1], '-', '/' ) ) );
		}
		return array( array( self::word_kind( $word ), $word ) );
	}


	/**
	 * What a word says about a mm next to it.
	 *
	 * @param string $word A normalised word.
	 * @return string 'size', 'thick', 'neutral' (incl. colours and materials, but not stones) or 'top' (anything
	 *                else: a ball, gem, charm).
	 */
	private static function word_kind( string $word ): string {
		if ( in_array( $word, self::SIZE_WORDS, true ) ) {
			return 'size';
		}
		if ( in_array( $word, self::THICKNESS_WORDS, true ) ) {
			return 'thick';
		}
		if ( in_array( $word, self::NEUTRAL_WORDS, true ) || preg_match( '/^\d+(?:[.\/]\d+)?(?:pc|pcs|pk)?$/', $word ) ) {
			return 'neutral';
		}
		$named = ! empty( self::phrases( 'colour-list' )[ $word ] ) || ! empty( self::phrases( 'material' )[ $word ] );
		return $named && ! in_array( $word, self::GEMS, true ) ? 'neutral' : 'top';
	}

	/**
	 * Joins a mm and a gauge next to each other that are one thickness ("1.2mm 16g", "10mm 00g"): the gauge's mm,
	 * or on stretching jewellery the mm itself. A thicker pair on other jewellery is flagged: it can be a ball's size
	 * ("Black 3mm (8G)" on a ball stud) as well as a thick bar ("8G(3mm), 12mm").
	 *
	 * @param array<int, array> $tokens     Tokens.
	 * @param bool              $stretching Whether the product is stretching jewellery.
	 * @return array<int, array> The tokens, pairs joined.
	 */
	private static function pair( array $tokens, bool $stretching ): array {
		for ( $i = 0; isset( $tokens[ $i + 1 ] ); $i++ ) {
			$types = $tokens[ $i ][0] . ' ' . $tokens[ $i + 1 ][0];
			if ( 'mm gauge' !== $types && 'gauge mm' !== $types ) {
				continue;
			}
			$mm    = 'mm' === $tokens[ $i ][0] ? $tokens[ $i ][1] : $tokens[ $i + 1 ][1];
			$gauge = 'mm' === $tokens[ $i ][0] ? $tokens[ $i + 1 ][1] : $tokens[ $i ][1];
			$thin  = (float) $mm <= OOBJ_SEARCH_Vocabulary::GAUGE_MAX_MM;
			if ( ! $thin && ! $stretching && abs( (float) $gauge - (float) $mm ) > 0.05 ) {
				continue;
			}
			$joined = $stretching || null === $gauge ? array( 'gauge', $mm ) : array( 'gauge', $gauge, ! $thin );
			array_splice( $tokens, $i, 2, array( $joined ) );
		}
		return $tokens;
	}

	/**
	 * A description's labelled lines ("Gauge/ Bar Thickness: 16G(1.2mm)", "• Materials 316L Surgical Steel") whose
	 * label LABELS knows. A label with nothing after it heads the lines under it, up to a blank line or the next
	 * label ("Sizes:" then "16GA(1.2mm), 8mm or 10mm").
	 *
	 * @param string $description The description, maybe HTML.
	 * @return array<int, array{0: string[], 1: string}> The label's types (label_types()) and the text after it.
	 */
	private static function lines( string $description ): array {
		$text  = (string) preg_replace( '/<br\s*\/?>|<\/(?:p|li|div|h\d)>/i', "\n", $description );
		$text  = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- plain PHP, no WordPress.
		$lines = array();
		$open  = null;
		foreach ( (array) preg_split( '/\r\n|\r|\n/', str_replace( "\u{00A0}", ' ', $text ) ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				$open = null;
			} elseif ( self::label( $line, $parts ) ) {
				$types = self::label_types( $parts[1] );
				$open  = null;
				// "Materials: We offer our labrets in three materials..." is prose, not the product's list.
				if ( $types && ( array( 'description' ) === $types || ! preg_match( '/\b(?:we|our|you|your)\b/i', $parts[2] ) ) ) {
					$lines[] = array( $types, $parts[2] );
					$open    = '' === $parts[2] ? count( $lines ) - 1 : null;
				}
			} elseif ( null !== $open ) {
				$lines[ $open ][1] .= "\n" . $line;
			}
		}
		return $lines;
	}

	/**
	 * Whether a line starts with a label: "Label: text", "Label - text", "• Label: text", "Materials text". A first
	 * label LABELS doesn't know gives way to a second ("Screws into - Dermal Anchor Gauge : 14GA").
	 *
	 * @param string   $line  A trimmed line.
	 * @param string[] $parts Set to the line, its label and the text after it.
	 */
	private static function label( string $line, ?array &$parts ): bool {
		$pattern = '/^(?:[•*·-]\s*)?([a-z][a-z \/&()]{0,40}?)\s*(?::|\s[-–]|[-–]\s)\s*(.*)$/iu';
		if ( ! preg_match( $pattern, $line, $parts ) ) {
			return 1 === preg_match( '/^(materials?)\s+(\S.*)$/i', $line, $parts );
		}
		if ( ! self::label_types( $parts[1] ) && preg_match( $pattern, $parts[2], $second ) && self::label_types( $second[1] ) ) {
			$parts = $second;
		}
		return true;
	}

	/**
	 * What a description label's line is read for.
	 *
	 * @param string $label A label, e.g. "Gauge /Bar Thickness".
	 * @return string[] Its types: LABELS keys, and 'top' for a size label of something else ("Ball Size").
	 */
	private static function label_types( string $label ): array {
		$words = explode( ' ', OOBJ_SEARCH_Text::normalise( $label ) );
		$types = array();
		foreach ( self::LABELS as $type => $label_words ) {
			if ( array_intersect( $words, $label_words ) ) {
				$types[] = $type;
			}
		}
		if ( in_array( 'gauge', $types, true ) && ! array_intersect( $words, array( 'size', 'sizes', 'length', 'lengths' ) ) ) {
			// "Gauge/ Bar Thickness" is a thickness; "Gauge/ Size" also a size.
			$types = array_diff( $types, array( 'size', 'length' ) );
		}
		$known = array_merge( self::SIZE_WORDS, self::SIZE_LABEL_WORDS, self::LABELS['gauge'], self::LABELS['colour'] );
		if ( array_intersect( $words, array( 'bar', 'bars' ) ) ) {
			// An industrial's "Bars Length Total", not a dangle's "Total Length".
			array_push( $known, 'total', 'overall' );
		}
		if ( in_array( 'size', $types, true ) && array_diff( $words, $known ) ) {
			$types   = array_diff( $types, array( 'size', 'length' ) );
			$types[] = 'top';
		}
		return array_values( $types );
	}

	/**
	 * Whether a product is sold as a top, ball or stud, so its top's size is its size.
	 *
	 * @param string[] $categories Its category slug paths.
	 * @param string   $name       Its normalised name.
	 */
	private static function is_top( array $categories, string $name ): bool {
		return self::in_categories( $categories, self::TOP_CATEGORIES ) || array_intersect( explode( ' ', $name ), self::TOP_NAME_WORDS );
	}

	/**
	 * Whether a product is in one of the categories, or their subcategories. A category is its slug path
	 * ("septum-daith-clickers-and-rings/clicker-rings") or last slug ("tunnels-plugs-tapers").
	 *
	 * @param string[] $categories The product's category slug paths.
	 * @param string[] $wanted     Categories.
	 */
	private static function in_categories( array $categories, array $wanted ): bool {
		foreach ( $categories as $category ) {
			foreach ( $wanted as $slug ) {
				if ( $category === $slug || 0 === strpos( $category, $slug . '/' ) || str_ends_with( $category, '/' . $slug ) || false !== strpos( $category, '/' . $slug . '/' ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
