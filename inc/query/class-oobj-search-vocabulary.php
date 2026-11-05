<?php
/**
 * Class OOBJ_SEARCH_Vocabulary.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

/**
 * What words mean, for both the query reader (OOBJ_SEARCH_Query_Parser) and the product facts
 * (OOBJ_SEARCH_Product_Facts): each facet's canonical values, their labels, and the phrases that name them. Plain PHP.
 *
 * Built from what shoppers actually type: the site's searches (Google Analytics, the server's logs) and 16 months of
 * Google queries that found the shop (Search Console), November 2026. Phrases are normalised (OOBJ_SEARCH_Text).
 * Within a facet the longest match wins and the shorter phrases inside it don't count: "rose gold" is rose gold, not
 * also gold. Across facets a phrase can count for each ("gold plated" is a material, and gold a colour).
 *
 * Facets:
 * - category: product categories (with their subcategories), as their slug paths ("navel/belly-rings": the
 *   last part is the slug); several, any of them, joined by "|" (daith is in two). Categories are WooCommerce's
 *   own; products aren't given category facts.
 * - material, fitting, colour, theme: facts a product has, from its name, description, attributes and categories
 *   ('categories' below: every product in one of those has the fact).
 * - gauge: a thickness in mm ("1.2"), from 16g, 16ga, 16 gauge, 1.2mm. Only thicknesses up to GAUGE_MAX_MM are
 *   gauges in plain text; larger ones are sizes, except for stretching jewellery (tunnels, plugs).
 * - size: a length or diameter in mm ("8"), from "8mm", "8mm bar".
 */
class OOBJ_SEARCH_Vocabulary {

	const FACETS = array( 'category', 'material', 'fitting', 'colour', 'theme', 'gauge', 'size' );

	/**
	 * The facets a product can have facts for (categories are WooCommerce's).
	 */
	const FACT_FACETS = array( 'material', 'fitting', 'colour', 'theme', 'gauge', 'size' );

	/**
	 * Gauge to thickness in mm, as the shop writes them (8g is sold as 3mm).
	 */
	const GAUGES = array(
		'22' => '0.6',
		'20' => '0.8',
		'18' => '1',
		'16' => '1.2',
		'14' => '1.6',
		'12' => '2',
		'10' => '2.5',
		'8'  => '3',
		'6'  => '4',
		'4'  => '5',
		'2'  => '6',
		'0'  => '8',
		'00' => '10',
	);

	/**
	 * Above this many mm, a plain "Nmm" is a length or diameter (size), not a thickness, unless it's stretching
	 * jewellery.
	 */
	const GAUGE_MAX_MM = 2.5;

	/**
	 * Categories whose jewellery is sized by thickness: "10mm" there is a gauge.
	 */
	const STRETCHING_CATEGORIES = array( 'tunnels-plugs-tapers' );

	/**
	 * Concepts by facet: value => label, phrases (as a shopper or a product says it) and, for facts, categories whose
	 * products all have it.
	 *
	 * @var array<string, array<string, array{label: string, phrases: string[], categories?: string[]}>>
	 */
	const CONCEPTS = array(
		'category' => array(
			'nose'                                        => array(
				'label'   => 'Nose',
				'phrases' => array( 'nose', 'nostril', 'nose ring', 'nose rings', 'nose jewellery', 'nose jewelry', 'high nostril' ),
			),
			'septum-daith-clickers-and-rings'             => array(
				'label'   => 'Septum',
				'phrases' => array( 'septum', 'septum ring', 'septum rings', 'septum jewellery', 'septum jewelry' ),
			),
			'septum-daith-clickers-and-rings|eyebrow-rook-daith' => array(
				'label'   => 'Daith',
				'phrases' => array( 'daith', 'daith ring', 'daith piercing' ),
			),
			'eyebrow-rook-daith'                          => array(
				'label'   => 'Eyebrow and rook',
				'phrases' => array( 'eyebrow', 'eyebrow ring', 'eyebrow bar', 'rook', 'rook ring', 'rook piercing' ),
			),
			'navel'                                       => array(
				'label'   => 'Belly',
				'phrases' => array( 'belly', 'navel', 'naval', 'belly button', 'belly ring', 'belly rings', 'belly bar', 'belly bars', 'belly button ring', 'belly button rings', 'belly button bar', 'belly piercing', 'navel ring', 'navel rings', 'belly jewellery', 'belly jewelry' ),
			),
			'navel/dangle-belly-rings'                    => array(
				'label'   => 'Dangle belly rings',
				'phrases' => array( 'dangle belly', 'dangle belly ring', 'dangle belly rings', 'dangly belly ring', 'dangling belly ring' ),
			),
			'navel/cheater-fake-belly-rings'              => array(
				'label'   => 'Fake belly rings',
				'phrases' => array( 'fake belly', 'fake belly ring', 'fake belly rings', 'clip on belly ring', 'cheater belly ring' ),
			),
			'nipple'                                      => array(
				'label'   => 'Nipple',
				'phrases' => array( 'nipple', 'nipples', 'nipple ring', 'nipple rings', 'nipple bar', 'nipple bars', 'nipple barbell', 'nipple jewellery', 'nipple jewelry', 'nipple piercing' ),
			),
			'tongue'                                      => array(
				'label'   => 'Tongue',
				'phrases' => array( 'tongue', 'tongu', 'tounge', 'tongue ring', 'tongue rings', 'tongue bar', 'tongue bars', 'tongue barbell', 'tongue piercing' ),
			),
			'lip-chin'                                    => array(
				'label'   => 'Lip and chin',
				'phrases' => array( 'lip', 'lips', 'lip ring', 'lip rings', 'chin', 'monroe', 'medusa', 'philtrum', 'snake bites', 'spider bites', 'vertical labret', 'lip piercing' ),
			),
			'ear-cartilage'                               => array(
				'label'   => 'Ear and cartilage',
				'phrases' => array( 'ear', 'ears', 'cartilage', 'helix', 'forward helix', 'tragus', 'anti tragus', 'conch', 'lobe', 'lobes', 'flat piercing', 'snug', 'orbital', 'ear piercing', 'cartilage piercing', 'helix piercing', 'tragus piercing', 'conch piercing' ),
			),
			'ear-cartilage/industrials'                   => array(
				'label'   => 'Industrial barbells',
				'phrases' => array( 'industrial', 'industrials', 'industrial bar', 'industrial barbell', 'industrial barbells', 'industrial piercing', 'scaffold' ),
			),
			'ear-cartilage/tunnels-plugs-tapers'          => array(
				'label'   => 'Tunnels, plugs and tapers',
				'phrases' => array( 'tunnel', 'tunnels', 'plug', 'plugs', 'taper', 'tapers', 'stretcher', 'stretchers', 'gauges', 'gauge plugs', 'ear gauges', 'ear stretchers', 'flesh tunnel', 'flesh tunnels', 'ear plugs', 'ear tunnels' ),
			),
			'ear-cartilage/labret-flat-base'              => array(
				'label'   => 'Flat back studs',
				'phrases' => array( 'flat back', 'flatback', 'flat backs', 'flat back stud', 'flat back studs', 'flat back earring', 'flat back earrings', 'flat back labret' ),
			),
			'ear-cartilage/barbells-straight-ball-cone'   => array(
				'label'   => 'Straight barbells',
				'phrases' => array( 'straight barbell', 'straight barbells', 'straight bar' ),
			),
			'ear-cartilage/front-facing-vertical-labrets' => array(
				'label'   => 'Front facing labrets',
				'phrases' => array( 'front facing', 'front facing labret', 'front facing labrets', 'forward facing', 'forward facing labret' ),
			),
			'ear-cartilage|earrings-post-and-hoops'       => array(
				'label'   => 'Earrings',
				'phrases' => array( 'earring', 'earrings', 'earing', 'earings', 'stud earrings' ),
			),
			'earrings-post-and-hoops'                     => array(
				'label'   => 'Post earrings and hoops',
				'phrases' => array( 'huggie', 'huggies', 'sleeper', 'sleepers', 'hoop earrings', 'post earrings' ),
			),
			'dermal-tops'                                 => array(
				'label'   => 'Dermal tops',
				'phrases' => array( 'dermal', 'dermals', 'dermal top', 'dermal tops', 'microdermal', 'micro dermal', 'dermal anchor' ),
			),
			'retainers-hiders'                            => array(
				'label'   => 'Retainers and hiders',
				'phrases' => array( 'retainer', 'retainers', 'hider', 'hiders', 'septum retainer', 'septum retainers', 'pregnancy' ),
			),
			'fake-and-non-piercing'                       => array(
				'label'   => 'Fake and non-piercing',
				'phrases' => array( 'fake', 'non piercing', 'clip on', 'clip ons', 'magnetic', 'faux', 'cheater', 'no piercing', 'fake septum', 'fake septum ring', 'fake nose', 'fake nose ring', 'fake lip ring', 'fake plug', 'fake plugs', 'fake tunnel', 'fake tunnels', 'fake gauges' ),
			),
			'bracelets-leather-and-pendants'              => array(
				'label'   => 'Bracelets and pendants',
				'phrases' => array( 'bracelet', 'bracelets', 'pendant', 'pendants', 'necklace', 'necklaces', 'leather' ),
			),
			'intimate-jewellery'                          => array(
				'label'   => 'Intimate jewellery',
				'phrases' => array( 'intimate', 'genital', 'genitals', 'vch', 'prince albert', 'christina', 'clit', 'clitoral' ),
			),
			'cbr-horseshoe-segment-twist'                 => array(
				'label'   => 'Captive, horseshoe, segment and twist',
				'phrases' => array( 'cbr', 'captive', 'captive bead', 'captive bead ring', 'horseshoe', 'horseshoes', 'horse shoe', 'circular barbell', 'circular barbells', 'twist', 'twists' ),
			),
			'hinged-segment-rings'                        => array(
				'label'   => 'Hinged segment rings',
				'phrases' => array( 'segment ring', 'segment rings', 'hinged segment', 'hinged segment ring', 'hinged segment rings', 'seamless ring' ),
			),
			'christmas-jewellery'                         => array(
				'label'   => 'Holiday',
				'phrases' => array( 'christmas', 'xmas', 'holiday', 'festive' ),
			),
			'sale-items'                                  => array(
				'label'   => 'On sale',
				'phrases' => array( 'sale', 'on sale', 'sale items', 'clearance', 'discount', 'discounted', 'specials', 'special' ),
			),
			'tools'                                       => array(
				'label'   => 'Tools',
				'phrases' => array( 'tool', 'tools', 'pliers', 'forceps', 'clamp', 'clamps' ),
			),
		),
		'material' => array(
			'titanium'        => array(
				'label'      => 'Titanium',
				'phrases'    => array( 'titanium', 'titanuim', 'titainum', 'ti', 'implant', 'implant grade', 'implant grade titanium', 'implant titanium', 'g23', 'g23 titanium', 'grade 23', 'grade 23 titanium', 'astm', 'astm titanium', 'astm f136', 'astm f-136', 'astm f136 titanium', 'f136', 'f-136', 'f136 titanium' ),
				'categories' => array( 'labrets-rings-barbells' ),
			),
			'surgical-steel'  => array(
				'label'   => 'Surgical steel',
				'phrases' => array( 'surgical steel', 'stainless steel', 'steel', '316l', '316l steel', '316 steel', 'surgical stainless steel' ),
			),
			'gold-plated'     => array(
				'label'   => 'Gold plated',
				'phrases' => array( 'gold plated', 'gold plating', '18k gold plated', '18kt gold plated', '18k plated', '14k gold plated', 'pvd gold', 'gold pvd' ),
			),
			'sterling-silver' => array(
				'label'   => 'Sterling silver',
				'phrases' => array( 'sterling silver', '925', '925 silver', '925 sterling', '925 sterling silver', 'sterling' ),
			),
			'bioplast'        => array(
				'label'   => 'Bioplast and bioflex',
				'phrases' => array( 'bioplast', 'bio plast', 'bioflex', 'bio flex', 'bio', 'ptfe', 'plastic', 'flexible' ),
			),
			'acrylic'         => array(
				'label'   => 'Acrylic',
				'phrases' => array( 'acrylic', 'uv acrylic' ),
			),
			'silicone'        => array(
				'label'   => 'Silicone',
				'phrases' => array( 'silicone', 'silicon' ),
			),
			'glass'           => array(
				'label'   => 'Glass',
				'phrases' => array( 'glass', 'pyrex' ),
			),
			'wood'            => array(
				'label'   => 'Wood',
				'phrases' => array( 'wood', 'wooden', 'organic wood' ),
			),
			'stone'           => array(
				'label'   => 'Stone',
				'phrases' => array( 'stone', 'stones', 'natural stone', 'gemstone', 'gemstones', 'crystal quartz', 'quartz', 'jade', 'obsidian', 'tigers eye', 'agate' ),
			),
		),
		'fitting'  => array(
			'threadless'          => array(
				'label'      => 'Threadless (push pin)',
				'phrases'    => array( 'threadless', 'thread less', 'push', 'push pin', 'push pins', 'pushpin', 'push fit', 'push in', 'pin top', 'pin tops', 'pin base', 'pin bases', 'bend pin' ),
				'categories' => array( 'pin-tops-pin-bases' ),
			),
			'internally-threaded' => array(
				'label'   => 'Internally threaded',
				'phrases' => array( 'internally threaded', 'internal thread', 'internal threaded', 'internally thread', 'internal threading', 'internally' ),
			),
			'externally-threaded' => array(
				'label'   => 'Externally threaded',
				'phrases' => array( 'externally threaded', 'external thread', 'external threaded', 'externally' ),
			),
			'clicker'             => array(
				'label'      => 'Clicker',
				'phrases'    => array( 'clicker', 'clickers', 'clicker ring', 'clicker rings', 'hinged clicker', 'hinged clickers' ),
				'categories' => array( 'septum-daith-clickers-and-rings/clicker-rings' ),
			),
			'hinged'              => array(
				'label'      => 'Hinged',
				'phrases'    => array( 'hinged', 'hinge', 'hinged ring', 'hinged rings', 'hinged hoop', 'hinged hoops' ),
				'categories' => array( 'hinged-segment-rings' ),
			),
		),
		'colour'   => array(
			'black'     => array(
				'label'   => 'Black',
				'phrases' => array( 'black', 'jet', 'onyx' ),
			),
			'white'     => array(
				'label'   => 'White',
				'phrases' => array( 'white', 'pearl', 'pearls' ),
			),
			'gold'      => array(
				'label'   => 'Gold',
				'phrases' => array( 'gold', 'golden', 'yellow gold', 'gold tone', 'gold colour', 'gold color' ),
			),
			'rose-gold' => array(
				'label'   => 'Rose gold',
				'phrases' => array( 'rose gold', 'rosegold', 'rose gold plated' ),
			),
			'silver'    => array(
				'label'   => 'Silver',
				'phrases' => array( 'silver', 'silver tone', 'silver colour', 'silver color', 'steel colour', 'chrome', 'rhodium', 'platinum' ),
			),
			'grey'      => array(
				'label'   => 'Grey',
				'phrases' => array( 'grey', 'gray', 'gunmetal', 'hematite', 'charcoal' ),
			),
			'brown'     => array(
				'label'   => 'Brown',
				'phrases' => array( 'brown', 'bronze', 'chocolate', 'coffee', 'tan' ),
			),
			'blue'      => array(
				'label'   => 'Blue',
				'phrases' => array( 'blue', 'navy', 'sapphire', 'cobalt', 'royal blue', 'light blue', 'dark blue' ),
			),
			'aqua'      => array(
				'label'   => 'Aqua and turquoise',
				'phrases' => array( 'aqua', 'turquoise', 'teal', 'aquamarine' ),
			),
			'green'     => array(
				'label'   => 'Green',
				'phrases' => array( 'green', 'emerald', 'peridot', 'mint', 'olive', 'lime' ),
			),
			'red'       => array(
				'label'   => 'Red',
				'phrases' => array( 'red', 'ruby', 'garnet', 'crimson', 'siam' ),
			),
			'pink'      => array(
				'label'   => 'Pink',
				'phrases' => array( 'pink', 'rose quartz', 'blush', 'fuchsia', 'hot pink' ),
			),
			'purple'    => array(
				'label'   => 'Purple',
				'phrases' => array( 'purple', 'violet', 'lilac', 'lavender', 'tanzanite', 'amethyst' ),
			),
			'yellow'    => array(
				'label'   => 'Yellow',
				'phrases' => array( 'yellow', 'citrine' ),
			),
			'orange'    => array(
				'label'   => 'Orange',
				'phrases' => array( 'orange', 'peach', 'amber' ),
			),
			'clear'     => array(
				'label'   => 'Clear',
				'phrases' => array( 'clear', 'transparent', 'crystal', 'cz', 'cubic zirconia', 'diamante' ),
			),
			'ab'        => array(
				'label'   => 'AB (iridescent)',
				'phrases' => array( 'ab', 'aurora borealis', 'iridescent', 'holographic' ),
			),
			'rainbow'   => array(
				'label'   => 'Rainbow',
				'phrases' => array( 'rainbow', 'multicolour', 'multicolor', 'multi colour', 'multi color', 'pride' ),
			),
			'opal'      => array(
				'label'   => 'Opal',
				'phrases' => array( 'opal', 'opals', 'fire opal', 'synthetic opal' ),
			),
		),
		'theme'    => array(
			'spooky'    => array(
				'label'   => 'Spooky',
				'phrases' => array( 'spooky', 'halloween', 'horror', 'evil', 'skull', 'skulls', 'skeleton', 'bat', 'bats', 'spider', 'spiders', 'ghost', 'ghosts', 'pumpkin', 'witch', 'gothic', 'goth', 'creepy', 'coffin', 'nightmare before christmas', 'demon', 'devil', 'vampire' ),
			),
			'celestial' => array(
				'label'   => 'Celestial',
				'phrases' => array( 'celestial', 'space', 'galaxy', 'cosmic', 'star', 'stars', 'moon', 'moons', 'crescent', 'sun', 'planet', 'saturn' ),
			),
			'floral'    => array(
				'label'   => 'Floral',
				'phrases' => array( 'floral', 'flower', 'flowers', 'daisy', 'daisies', 'blossom', 'lotus', 'sunflower', 'cherry blossom', 'leaf', 'leaves', 'vine', 'botanical' ),
			),
			'animal'    => array(
				'label'   => 'Animals',
				'phrases' => array( 'animal', 'animals', 'cat', 'cats', 'paw', 'paws', 'butterfly', 'butterflies', 'bee', 'bees', 'snake', 'snakes', 'dragon', 'dragonfly', 'bird', 'birds', 'fish', 'frog', 'owl', 'fox', 'bunny', 'rabbit', 'scorpion', 'lizard', 'dolphin', 'turtle' ),
			),
			'heart'     => array(
				'label'   => 'Hearts',
				'phrases' => array( 'heart', 'hearts', 'love' ),
			),
		),
	);

	/**
	 * Phrases that mean "sort by price, lowest first" (or highest, for PRICE_HIGH_PHRASES).
	 */
	const PRICE_LOW_PHRASES = array( 'cheap', 'cheaper', 'cheapest', 'budget', 'affordable', 'inexpensive', 'low price', 'low cost', 'lowest price', 'bargain', 'bargains', 'cheap as', 'low to high', 'price low to high', 'cheapest first' );

	const PRICE_HIGH_PHRASES = array( 'expensive', 'premium', 'luxury', 'high end', 'most expensive', 'high to low', 'price high to low', 'priciest' );

	/**
	 * Words that carry no meaning for a product search once the rest is understood.
	 */
	const STOP_WORDS = array( 'a', 'an', 'the', 'and', 'or', 'for', 'with', 'in', 'of', 'to', 'on', 'my', 'me', 'i', 'im', 'want', 'need', 'looking', 'show', 'find', 'some', 'any', 'options', 'option', 'item', 'items', 'product', 'products', 'jewellery', 'jewelry', 'jewelery', 'jewlery', 'jewllery', 'jewerly', 'body', 'piercing', 'piercings', 'pierced', 'buy', 'shop', 'online', 'australia', 'aus', 'au', 'please', 'that', 'are', 'is', 'which', 'what', 'good', 'nice', 'new', 'one', 'ones', 'pair', 'pairs', 'set', 'sets', 'type', 'style', 'styles', 'this', 'it', 'its', 'w', 'x', 'price', 'prices' );

	/**
	 * How much shoppers search for each fact value: site searches by count, Google searches by impressions / 50,
	 * read with OOBJ_SEARCH_Query_Parser (November 2026; rebuilt with tools/demand.php, see docs/demand.md). Suggestions favour what's asked for (titanium over steel,
	 * threadless over externally threaded); values not listed score 0.
	 */
	const DEMAND = array(
		'material' => array(
			'titanium'        => 263,
			'bioplast'        => 93,
			'acrylic'         => 45,
			'silicone'        => 26,
			'surgical-steel'  => 8,
			'sterling-silver' => 1,
			'glass'           => 1,
		),
		'fitting'  => array(
			'threadless'          => 561,
			'internally-threaded' => 73,
			'clicker'             => 48,
			'hinged'              => 10,
		),
		'colour'   => array(
			'opal'      => 68,
			'rose-gold' => 61,
			'black'     => 57,
			'pink'      => 32,
			'gold'      => 28,
			'green'     => 24,
			'rainbow'   => 21,
			'aqua'      => 12,
			'blue'      => 6,
			'white'     => 5,
			'clear'     => 2,
		),
		'theme'    => array(
			'celestial' => 140,
			'animal'    => 89,
			'floral'    => 34,
			'heart'     => 23,
			'spooky'    => 22,
		),
		'gauge'    => array(
			'2'   => 41,
			'1.2' => 27,
			'0.8' => 26,
			'1'   => 24,
			'1.6' => 15,
			'1.5' => 7,
			'2.5' => 4,
		),
	);

	/**
	 * Seasons, as month-day ranges in the shop's year (Australia): spooky things before Halloween, Christmas things
	 * before Christmas. In season, their facts and categories are favoured in suggestions and the search panel, and
	 * their examples shown first.
	 */
	const SEASONS = array(
		'halloween' => array(
			'from'     => '09-15',
			'to'       => '10-31',
			'facet'    => 'theme',
			'value'    => 'spooky',
			'examples' => array( 'spooky earrings under $10' ),
		),
		'christmas' => array(
			'from'     => '11-01',
			'to'       => '12-25',
			'facet'    => 'category',
			'value'    => 'christmas-jewellery',
			'examples' => array( 'christmas earrings' ),
		),
	);

	/**
	 * The seasons a date is in.
	 *
	 * @param DateTimeInterface $date A date.
	 * @return string[] Season names (keys of SEASONS).
	 */
	public static function seasons( DateTimeInterface $date ): array {
		$today = $date->format( 'm-d' );
		return array_keys( array_filter( self::SEASONS, fn( $season ) => $today >= $season['from'] && $today <= $season['to'] ) );
	}

	/**
	 * Every phrase of a facet with what it means, longest first.
	 *
	 * @param string $facet One of FACETS with concepts.
	 * @return array<string|int, string> Phrase => value (a numeric phrase, "925", is an int key).
	 */
	public static function phrases( string $facet ): array {
		$phrases = array();
		foreach ( self::CONCEPTS[ $facet ] ?? array() as $value => $concept ) {
			foreach ( $concept['phrases'] as $phrase ) {
				$phrases[ OOBJ_SEARCH_Text::normalise( $phrase ) ] = (string) $value;
			}
		}
		// PHP makes a numeric key ("925") an int.
		uksort( $phrases, fn( $a, $b ) => array( strlen( (string) $b ), (string) $a ) <=> array( strlen( (string) $a ), (string) $b ) );
		return $phrases;
	}

	/**
	 * A value's label, e.g. "Titanium" for material titanium, "16g (1.2mm)" for gauge 1.2, "8mm" for size 8.
	 *
	 * @param string $facet A facet.
	 * @param string $value Its value.
	 */
	public static function label( string $facet, string $value ): string {
		if ( 'gauge' === $facet ) {
			$gauge = array_search( $value, self::GAUGES, true );
			return false === $gauge ? $value . 'mm' : sprintf( '%sg (%smm)', $gauge, $value );
		}
		if ( 'size' === $facet ) {
			return $value . 'mm';
		}
		return self::CONCEPTS[ $facet ][ $value ]['label'] ?? $value;
	}

	/**
	 * The words a shopper would type for a value, so a suggested search reads naturally: material titanium is
	 * "titanium", gauge 1.2 is "16g", size 8 is "8mm". A concept's first phrase.
	 *
	 * @param string $facet A facet.
	 * @param string $value Its value.
	 */
	public static function phrase( string $facet, string $value ): string {
		if ( 'gauge' === $facet ) {
			$gauge = array_search( $value, self::GAUGES, true );
			return false === $gauge ? $value . 'mm' : $gauge . 'g';
		}
		if ( 'size' === $facet ) {
			return $value . 'mm';
		}
		return (string) ( self::CONCEPTS[ $facet ][ $value ]['phrases'][0] ?? $value );
	}

	/**
	 * A thickness in mm as the vocabulary writes it: "1.20" and "1.2" are "1.2", "8.0" is "8".
	 *
	 * @param string $mm A number of mm.
	 */
	public static function mm( string $mm ): string {
		$number = rtrim( rtrim( number_format( (float) $mm, 2, '.', '' ), '0' ), '.' );
		return '' === $number ? '0' : $number;
	}
}
