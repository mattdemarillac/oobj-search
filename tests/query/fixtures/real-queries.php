<?php
/**
 * Real queries (site search, server logs, Google; November 2026) and how they read: query, facets (values joined by
 * ","), keywords, and price ("min-max"), sort and product number where there are any. Checked by hand; a comment
 * explains a judgement call.
 *
 * @package oobj-search
 */

return array(
	// No labret category: labrets are spread over ear, lip and nose, so it's a word to search for.
	array( 'Labret', array(), 'labret' ),
	array( 'Star', array( 'theme' => 'celestial' ), '' ),
	array( 'threadless', array( 'fitting' => 'threadless' ), '' ),
	array( 'Horseshoe', array( 'category' => 'cbr-horseshoe-segment-twist' ), '' ),
	array( 'Septum', array( 'category' => 'septum-daith-clickers-and-rings' ), '' ),
	array( 'Rose gold', array( 'colour' => 'rose-gold' ), '' ),
	array( 'Spike', array(), 'spike' ),
	array( 'Flat back', array( 'category' => 'ear-cartilage/labret-flat-base' ), '' ),
	array( 'Bioflex', array( 'material' => 'bioplast' ), '' ),
	array( 'Tragus', array( 'category' => 'ear-cartilage' ), '' ),
	array( 'Taper', array( 'category' => 'ear-cartilage/tunnels-plugs-tapers' ), '' ),
	array( 'Opal', array( 'colour' => 'opal' ), '' ),
	array( 'Internally threaded', array( 'fitting' => 'internally-threaded' ), '' ),
	array( 'Clicker', array( 'fitting' => 'clicker' ), '' ),
	array( 'Pink', array( 'colour' => 'pink' ), '' ),
	// Helix is the ear; "vertical" narrows it by product text (vertical helix labrets say so).
	array( 'Vertical helix', array( 'category' => 'ear-cartilage' ), 'vertical' ),
	array( 'Push in labret', array( 'fitting' => 'threadless' ), 'labret' ),
	array( 'Chain', array(), 'chain' ),
	array( 'Acrylic', array( 'material' => 'acrylic' ), '' ),
	array( 'Nose ring', array( 'category' => 'nose' ), '' ),
	array( 'Curved barbell', array(), 'curved barbell' ),
	array( 'Heart', array( 'theme' => 'heart' ), '' ),
	array( 'Moon', array( 'theme' => 'celestial' ), '' ),
	array( 'Push pin', array( 'fitting' => 'threadless' ), '' ),
	// "new" is a stop word: there's no newest-first sort to map it to.
	array( 'New', array(), '' ),
	array( 'Snake', array( 'theme' => 'animal' ), '' ),
	// Two parts for threadless (threadless, push in); the facet has it once.
	array(
		'Titanium Threadless push in',
		array(
			'material' => 'titanium',
			'fitting'  => 'threadless',
		),
		'',
	),
	array( 'Rook', array( 'category' => 'eyebrow-rook-daith' ), '' ),
	array( 'Vch', array( 'category' => 'intimate-jewellery' ), '' ),
	array( 'Bioflex labret', array( 'material' => 'bioplast' ), 'labret' ),
	array( 'Micro', array(), 'micro' ),
	array( '18g', array( 'gauge' => '1' ), '' ),
	array( '6mm ring', array( 'size' => '6' ), 'ring' ),
	array( 'Hidden helix', array( 'category' => 'ear-cartilage' ), 'hidden' ),
	// Alone, rose is a flower or rose gold: left to product text.
	array( 'Rose', array(), 'rose' ),
	array( 'Turquoise', array( 'colour' => 'aqua' ), '' ),
	array( '20g', array( 'gauge' => '0.8' ), '' ),
	array( '7mm', array( 'size' => '7' ), '' ),
	array( 'Butterfly', array( 'theme' => 'animal' ), '' ),
	array( 'Pin tops', array( 'fitting' => 'threadless' ), '' ),
	array( 'Vitrail', array(), 'vitrail' ),
	array( 'Bat', array( 'theme' => 'spooky' ), '' ),
	// Up to GAUGE_MAX_MM a size in mm is a thickness, though here it's likely the opal's size.
	array(
		'Opal 2 mm',
		array(
			'colour' => 'opal',
			'gauge'  => '2',
		),
		'',
	),
	array( 'Saddle plug', array( 'category' => 'ear-cartilage/tunnels-plugs-tapers' ), 'saddle' ),
	array( 'Set', array(), '' ),
	// Pinchers are septum and stretching jewellery: not a category.
	array( 'pincher', array(), 'pincher' ),
	array( '10mm labret', array( 'size' => '10' ), 'labret' ),
	array(
		'Septum clicker',
		array(
			'category' => 'septum-daith-clickers-and-rings',
			'fitting'  => 'clicker',
		),
		'',
	),
	array(
		'Star push',
		array(
			'theme'   => 'celestial',
			'fitting' => 'threadless',
		),
		'',
	),
	array(
		'20g 6mm',
		array(
			'gauge' => '0.8',
			'size'  => '6',
		),
		'',
	),
	array( 'Push tops', array( 'fitting' => 'threadless' ), 'tops' ),
	array( '1.5 mm', array( 'gauge' => '1.5' ), '' ),
	array(
		'2 mm push pin',
		array(
			'gauge'   => '2',
			'fitting' => 'threadless',
		),
		'',
	),
	array( 'ASTM F-136', array( 'material' => 'titanium' ), '' ),
	// Flat back studs are inside ear and cartilage, so the category narrows to them.
	array( 'Tragus flat back', array( 'category' => 'ear-cartilage/labret-flat-base' ), '' ),
	// A septum retainer is a retainer, not septum jewellery.
	array( 'septum retainer', array( 'category' => 'retainers-hiders' ), '' ),
	array(
		'18g nose',
		array(
			'gauge'    => '1',
			'category' => 'nose',
		),
		'',
	),
	array(
		'2 mm push pin star',
		array(
			'gauge'   => '2',
			'fitting' => 'threadless',
			'theme'   => 'celestial',
		),
		'',
	),
	array( '35mm', array( 'size' => '35' ), '' ),
	array( '4mm curved barbell', array( 'size' => '4' ), 'curved barbell' ),
	array( 'Bio', array( 'material' => 'bioplast' ), '' ),
	array(
		'Helix black',
		array(
			'category' => 'ear-cartilage',
			'colour'   => 'black',
		),
		'',
	),
	array( 'Horse shoe', array( 'category' => 'cbr-horseshoe-segment-twist' ), '' ),
	array( 'Implant Grade Titanium', array( 'material' => 'titanium' ), '' ),
	array(
		'Titanium rose gold',
		array(
			'material' => 'titanium',
			'colour'   => 'rose-gold',
		),
		'',
	),
	array( 'astm titanium', array( 'material' => 'titanium' ), '' ),
	array(
		'opal 8mm',
		array(
			'colour' => 'opal',
			'size'   => '8',
		),
		'',
	),
	// Daith is sold under both septum and eyebrow/rook/daith.
	array( 'Daith', array( 'category' => 'septum-daith-clickers-and-rings|eyebrow-rook-daith' ), '' ),
	// Fake septum rings are fake and non-piercing jewellery, not septum jewellery.
	array( 'Fake septum', array( 'category' => 'fake-and-non-piercing' ), '' ),
	array( 'Forward facing', array( 'category' => 'ear-cartilage/front-facing-vertical-labrets' ), '' ),
	// Colours are alternatives, so this is wide: gold, white or opal.
	array( 'Gold white opal', array( 'colour' => 'gold,white,opal' ), '' ),
	// A product name pasted in: "w/" is a stop word.
	array(
		'Hoop Nose Ring W/ Small Heart Top In IP Colours - Gold',
		array(
			'category' => 'nose',
			'theme'    => 'heart',
			'colour'   => 'gold',
		),
		'hoop small top ip colours',
	),
	array( 'Open nose ring', array( 'category' => 'nose' ), 'open' ),
	array( 'Spiral taper', array( 'category' => 'ear-cartilage/tunnels-plugs-tapers' ), 'spiral' ),
	// "flat" here is a shape (flat tops), not the flat piercing.
	array( 'Tops push in flat', array( 'fitting' => 'threadless' ), 'tops flat' ),
	array(
		'Tunnel silicone',
		array(
			'category' => 'ear-cartilage/tunnels-plugs-tapers',
			'material' => 'silicone',
		),
		'',
	),
	array( 'd ring', array(), 'd ring' ),
	array(
		'10mm titanium labret threadless',
		array(
			'size'     => '10',
			'material' => 'titanium',
			'fitting'  => 'threadless',
		),
		'labret',
	),
	array(
		'Acrylic Balls 316L Surgical Steel Tongue Barbell',
		array(
			'material' => 'acrylic,surgical-steel',
			'category' => 'tongue',
		),
		'balls',
	),
	array(
		'Black ear piercings',
		array(
			'colour'   => 'black',
			'category' => 'ear-cartilage',
		),
		'',
	),
	array( 'Click ball captive ring', array( 'category' => 'cbr-horseshoe-segment-twist' ), 'click ball ring' ),
	array(
		'Eyebrow barbell with acrylic ball',
		array(
			'category' => 'eyebrow-rook-daith',
			'material' => 'acrylic',
		),
		'barbell ball',
	),
	// Two categories neither inside the other stay alternatives (wide).
	array( 'Flat back nose', array( 'category' => 'ear-cartilage/labret-flat-base,nose' ), '' ),
	array(
		'Rainbow nose ring',
		array(
			'colour'   => 'rainbow',
			'category' => 'nose',
		),
		'',
	),
	// Apostrophes are dropped: shell.
	array( 'She’ll', array(), 'shell' ),
	// Ear and cartilage is dropped: straight barbells are inside it.
	array( 'Straight Barbell with Balls for Cartilage, Tongue, Nipple', array( 'category' => 'ear-cartilage/barbells-straight-ball-cone,tongue,nipple' ), 'balls' ),
	array( 'Threaded', array(), 'threaded' ),
	array(
		'Titanium 2mm ear',
		array(
			'material' => 'titanium',
			'gauge'    => '2',
			'category' => 'ear-cartilage',
		),
		'',
	),
	array( 'flat back small', array( 'category' => 'ear-cartilage/labret-flat-base' ), 'small' ),
	array( 'implant titanium threaded', array( 'material' => 'titanium' ), 'threaded' ),
	array( '12 gauge', array( 'gauge' => '2' ), '' ),
	array( '2.5 mm', array( 'gauge' => '2.5' ), '' ),
	array( '20mm', array( 'size' => '20' ), '' ),
	// Thickness, not size: 2mm is under GAUGE_MAX_MM.
	array( '2mm', array( 'gauge' => '2' ), '' ),
	array(
		'6mm gold hoop',
		array(
			'size'   => '6',
			'colour' => 'gold',
		),
		'hoop',
	),
	array( '18ga', array( 'gauge' => '1' ), '' ),
	array( 'tongu', array( 'category' => 'tongue' ), '' ),
	// A SKU.
	array( '966', array(), '', array( 'number' => '966' ) ),
	array( '7mm bar', array( 'size' => '7' ), 'bar' ),
	array( 'flat back set', array( 'category' => 'ear-cartilage/labret-flat-base' ), '' ),
	array(
		'push pin glass',
		array(
			'fitting'  => 'threadless',
			'material' => 'glass',
		),
		'',
	),
	array( '20ga', array( 'gauge' => '0.8' ), '' ),
	array( 'nightmare before christmas', array( 'theme' => 'spooky' ), '' ),
	array( '4019', array(), '', array( 'number' => '4019' ) ),
	array( 'sleeper', array( 'category' => 'earrings-post-and-hoops' ), '' ),
	array( 'twist', array( 'category' => 'cbr-horseshoe-segment-twist' ), '' ),
	// Probably "gold hoop", but golf isn't a known misspelling: left as typed.
	array( 'golf hoop', array(), 'golf hoop' ),
	array(
		'16 guage gold hoop',
		array(
			'gauge'  => '1.2',
			'colour' => 'gold',
		),
		'hoop',
	),
	// Most earrings are ear jewellery, so earrings is ear and cartilage or post earrings and hoops.
	array(
		'threadless earrings',
		array(
			'fitting'  => 'threadless',
			'category' => 'ear-cartilage|earrings-post-and-hoops',
		),
		'',
	),
	// "flat back earrings" is one phrase: flat back studs.
	array(
		'threadless flat back earrings',
		array(
			'fitting'  => 'threadless',
			'category' => 'ear-cartilage/labret-flat-base',
		),
		'',
	),
	array(
		'push pin earrings',
		array(
			'fitting'  => 'threadless',
			'category' => 'ear-cartilage|earrings-post-and-hoops',
		),
		'',
	),
	array( 'titanium jewellery', array( 'material' => 'titanium' ), '' ),
	array(
		'titanium septum pincher',
		array(
			'material' => 'titanium',
			'category' => 'septum-daith-clickers-and-rings',
		),
		// The type of jewellery stays a word to search for.
		'pincher',
	),
	array( 'internally threaded jewellery', array( 'fitting' => 'internally-threaded' ), '' ),
	array(
		'titanium flat back earrings australia',
		array(
			'material' => 'titanium',
			'category' => 'ear-cartilage/labret-flat-base',
		),
		'',
	),
	array( 'implant-grade titanium', array( 'material' => 'titanium' ), '' ),
	array(
		'septum pincher 16g',
		array(
			'category' => 'septum-daith-clickers-and-rings',
			'gauge'    => '1.2',
		),
		'pincher',
	),
	array(
		'16g septum pincher',
		array(
			'gauge'    => '1.2',
			'category' => 'septum-daith-clickers-and-rings',
		),
		'pincher',
	),
	array( 'f136 titanium', array( 'material' => 'titanium' ), '' ),
	array( 'threadless jewllery', array( 'fitting' => 'threadless' ), '' ),
	array(
		'gold tongue bar',
		array(
			'colour'   => 'gold',
			'category' => 'tongue',
		),
		'',
	),
	array( 'ear gauges australia', array( 'category' => 'ear-cartilage/tunnels-plugs-tapers' ), '' ),
	// See pincher.
	array( '16g pincher', array( 'gauge' => '1.2' ), 'pincher' ),
	array( 'hinged segment ring', array( 'category' => 'hinged-segment-rings' ), '' ),
	// Not a product search: left as typed.
	array( 'blackxxx', array(), 'blackxxx' ),
	array(
		'navel clicker ring',
		array(
			'category' => 'navel',
			'fitting'  => 'clicker',
		),
		'',
	),
	array(
		'clicker belly ring',
		array(
			'fitting'  => 'clicker',
			'category' => 'navel',
		),
		'',
	),
	array(
		'clear septum retainer',
		array(
			'colour'   => 'clear',
			'category' => 'retainers-hiders',
		),
		'',
	),
	array(
		'pincher septum ring 16g',
		array(
			'category' => 'septum-daith-clickers-and-rings',
			'gauge'    => '1.2',
		),
		'pincher',
	),
	array(
		'14g dermal tops',
		array(
			'gauge'    => '1.6',
			'category' => 'dermal-tops',
		),
		'',
	),
	array(
		'16g industrial barbell',
		array(
			'gauge'    => '1.2',
			'category' => 'ear-cartilage/industrials',
		),
		'',
	),
	// Horseshoes and nose are alternatives (wide): the category facet mixes kind and placement.
	array(
		'6mm horseshoe nose ring',
		array(
			'size'     => '6',
			'category' => 'cbr-horseshoe-segment-twist,nose',
		),
		'',
	),
	// Earrings is dropped: flat back studs are inside ear and cartilage.
	array(
		'flat back push pin earrings',
		array(
			'category' => 'ear-cartilage/labret-flat-base',
			'fitting'  => 'threadless',
		),
		'',
	),
	array(
		'vertical helix jewelry titanium',
		array(
			'category' => 'ear-cartilage',
			'material' => 'titanium',
		),
		'vertical',
	),
	array(
		'14 gauge internally threaded tops',
		array(
			'gauge'   => '1.6',
			'fitting' => 'internally-threaded',
		),
		'tops',
	),
	// "gauges" alone is stretching jewellery (AU usage).
	array( 'o rings for gauges', array( 'category' => 'ear-cartilage/tunnels-plugs-tapers' ), 'o rings' ),
	// Gold Coast is a place: dropped, not gold.
	array( 'body piercing gold coast', array(), '' ),
	array(
		'titanium fake plugs',
		array(
			'material' => 'titanium',
			'category' => 'fake-and-non-piercing',
		),
		'',
	),
	array(
		'2mm dermal top',
		array(
			'gauge'    => '2',
			'category' => 'dermal-tops',
		),
		'',
	),
	// Nipple or retainers (wide): see 6mm horseshoe nose ring.
	array( 'nipple piercing retainer', array( 'category' => 'nipple,retainers-hiders' ), '' ),
	// Earrings narrows to ear and cartilage (helix).
	array(
		'threadless helix earrings',
		array(
			'fitting'  => 'threadless',
			'category' => 'ear-cartilage',
		),
		'',
	),
	array(
		'daith clicker',
		array(
			'category' => 'septum-daith-clickers-and-rings|eyebrow-rook-daith',
			'fitting'  => 'clicker',
		),
		'',
	),
	array(
		'naval clicker',
		array(
			'category' => 'navel',
			'fitting'  => 'clicker',
		),
		'',
	),
	// A butterfly back is a backing, not an animal: kept as words.
	array(
		'titanium butterfly back earrings',
		array(
			'material' => 'titanium',
			'category' => 'ear-cartilage|earrings-post-and-hoops',
		),
		'butterfly back',
	),
	// "push" alone is push pin (threadless) in this shop.
	array(
		'push earrings',
		array(
			'fitting'  => 'threadless',
			'category' => 'ear-cartilage|earrings-post-and-hoops',
		),
		'',
	),
	array(
		'black fake plug earrings',
		array(
			'colour'   => 'black',
			'category' => 'fake-and-non-piercing,ear-cartilage|earrings-post-and-hoops',
		),
		'',
	),
	array(
		'10g captive bead ring',
		array(
			'gauge'    => '2.5',
			'category' => 'cbr-horseshoe-segment-twist',
		),
		'',
	),
	array( 'hinged clicker ring', array( 'fitting' => 'clicker' ), 'ring' ),
	// 18k stays a word: the shop's 18k is gold plated, which product text says.
	array(
		'18k gold belly piercing',
		array(
			'colour'   => 'gold',
			'category' => 'navel',
		),
		'18k',
	),
	// Not one phrase, so septum or retainers (wide).
	array( 'septum staple retainer', array( 'category' => 'septum-daith-clickers-and-rings,retainers-hiders' ), 'staple' ),
	array(
		'plastic septum retainer',
		array(
			'material' => 'bioplast',
			'category' => 'retainers-hiders',
		),
		'',
	),
	array(
		'purple opal earrings',
		array(
			'colour'   => 'purple,opal',
			'category' => 'ear-cartilage|earrings-post-and-hoops',
		),
		'',
	),
	array(
		'push pin earings',
		array(
			'fitting'  => 'threadless',
			'category' => 'ear-cartilage|earrings-post-and-hoops',
		),
		'',
	),
	array( 'opalite plugs', array( 'category' => 'ear-cartilage/tunnels-plugs-tapers' ), 'opalite' ),
	array(
		'20g 6mm horseshoe nose ring',
		array(
			'gauge'    => '0.8',
			'size'     => '6',
			'category' => 'cbr-horseshoe-segment-twist,nose',
		),
		'',
	),
	array(
		'18g 8mm',
		array(
			'gauge' => '1',
			'size'  => '8',
		),
		'',
	),
	array( 'septum retainers', array( 'category' => 'retainers-hiders' ), '' ),
	// "septum retainer" is a phrase here: retainers only.
	array( 'staple septum retainer', array( 'category' => 'retainers-hiders' ), 'staple' ),
	array(
		'implant grade titanium vertical labret jewelry',
		array(
			'material' => 'titanium',
			'category' => 'lip-chin',
		),
		'',
	),
	array( 'body jewelry sale', array( 'category' => 'sale-items' ), '' ),
	array(
		'implant grade titanium astm f136 belly ring',
		array(
			'material' => 'titanium',
			'category' => 'navel',
		),
		'',
	),
	array( '1.5 mm labret stud', array( 'gauge' => '1.5' ), 'labret stud' ),
	array(
		'18 gauge l shaped nose stud',
		array(
			'gauge'    => '1',
			'category' => 'nose',
		),
		'l shaped stud',
	),
	array(
		'clear tongue retainer',
		array(
			'colour'   => 'clear',
			'category' => 'tongue,retainers-hiders',
		),
		'',
	),
	// Fake and belly apart: alternatives (wide).
	array(
		'fake gold belly button piercing',
		array(
			'category' => 'fake-and-non-piercing,navel',
			'colour'   => 'gold',
		),
		'',
	),
	array(
		'threadless taper tool',
		array(
			'fitting'  => 'threadless',
			'category' => 'ear-cartilage/tunnels-plugs-tapers,tools',
		),
		'',
	),
	array(
		'925 sterling silver nose stud',
		array(
			'material' => 'sterling-silver',
			'category' => 'nose',
		),
		'stud',
	),
	array(
		'g23 titanium nose ring',
		array(
			'material' => 'titanium',
			'category' => 'nose',
		),
		'',
	),
	array(
		'titanium earring posts and backs',
		array(
			'material' => 'titanium',
			'category' => 'ear-cartilage|earrings-post-and-hoops',
		),
		'posts backs',
	),
	array(
		'5mm post flat back earrings',
		array(
			'size'     => '5',
			'category' => 'ear-cartilage/labret-flat-base',
		),
		'post',
	),
	array(
		'bioflex push-fit labret',
		array(
			'material' => 'bioplast',
			'fitting'  => 'threadless',
		),
		'labret',
	),
	array( '1.6mm', array( 'gauge' => '1.6' ), '' ),
	array(
		'10g hoop earrings',
		array(
			'gauge'    => '2.5',
			'category' => 'earrings-post-and-hoops',
		),
		'',
	),
	// Stretching jewellery: 10mm is a gauge (00g).
	array(
		'10mm tunnels',
		array(
			'gauge'    => '10',
			'category' => 'ear-cartilage/tunnels-plugs-tapers',
		),
		'',
	),
	array( '6g barbell', array( 'gauge' => '4' ), 'barbell' ),
	// Over GAUGE_MAX_MM and no stretching jewellery: a size.
	array(
		'6mm steel',
		array(
			'size'     => '6',
			'material' => 'surgical-steel',
		),
		'',
	),
	// "cheaper" alone is a sort, not a price.
	array( 'any cheaper options', array(), '', array( 'sort' => 'price' ) ),
	array( 'any silver ones', array( 'colour' => 'silver' ), '' ),
	array(
		'black silicone ear plugs',
		array(
			'colour'   => 'black',
			'material' => 'silicone',
			'category' => 'ear-cartilage/tunnels-plugs-tapers',
		),
		'',
	),
	array( 'cheap', array(), '', array( 'sort' => 'price' ) ),
	array( 'cheapest with delivery', array(), 'delivery', array( 'sort' => 'price' ) ),
	array( 'find this in gold', array( 'colour' => 'gold' ), '' ),
	// "for sale" means buyable, not on sale.
	array( 'genital jewelry for sale', array( 'category' => 'intimate-jewellery' ), '' ),
	// "its" is a stop word.
	array( 'it’s silver', array( 'colour' => 'silver' ), '' ),
	// "threaded" alone doesn't say internal or external.
	array( 'titanium threaded curved barbell', array( 'material' => 'titanium' ), 'threaded curved barbell' ),
);
