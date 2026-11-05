<?php
/**
 * OOBJ_SEARCH_Product_Facts: which source counts for which facet, rule by rule, with the shop's own products'
 * wording.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class ProductFactsTest extends TestCase {

	/**
	 * One facet's values for a product.
	 *
	 * @param array  $product As OOBJ_SEARCH_Product_Facts::facts().
	 * @param string $facet   A facet.
	 * @return string[]
	 */
	private static function facet( array $product, string $facet ): array {
		return OOBJ_SEARCH_Product_Facts::facts( $product )[ $facet ] ?? array();
	}

	public function test_nothing_but_a_colour_gives_only_that() {
		$this->assertSame( array(), OOBJ_SEARCH_Product_Facts::facts( array() ) );
		$this->assertSame(
			array( 'colour' => array( 'brown' ) ),
			OOBJ_SEARCH_Product_Facts::facts(
				array(
					'name'        => 'Brown Leather Plaited Wristband',
					'attributes'  => array(),
					'tags'        => array(),
					'description' => 'Description: This simple but attractive wristband features dark brown plaited leather',
				)
			)
		);
	}

	public function test_facts_are_sorted_unique_and_only_known_facets() {
		$facts = OOBJ_SEARCH_Product_Facts::facts(
			array(
				'name'        => 'Black Acrylic Gear Tunnels with Heart Internal Threaded Pairs',
				'categories'  => array( 'ear-cartilage/tunnels-plugs-tapers' ),
				'attributes'  => array( 'pa_mm-guage-size' => array( '10mm', '12mm', '14mm', '8mm' ) ),
				'description' => "Size: 8mm to 14mm.\nMaterials: Acrylic.",
			)
		);
		$this->assertSame(
			array(
				'material' => array( 'acrylic' ),
				'fitting'  => array( 'internally-threaded' ),
				'colour'   => array( 'black' ),
				'theme'    => array( 'heart' ),
				'gauge'    => array( '8', '10', '12', '14' ),
			),
			$facts
		);
	}

	/**
	 * @dataProvider sources
	 */
	public function test_sources( string $facet, array $product, array $values ) {
		$this->assertSame( $values, self::facet( $product, $facet ) );
	}

	public static function sources(): array {
		return array(
			'material from the name'                      => array( 'material', array( 'name' => '1.5mm Micro Ball Top Titanium Nose Bone Stud' ), array( 'titanium' ) ),
			'theme from a tag'                            => array( 'theme', array( 'tags' => array( 'Skull Nipple Ring' ) ), array( 'spooky' ) ),
			'colour from a variation'                     => array( 'colour', array( 'attributes' => array( 'pa_colour-size' => array( 'Gold/Clear, 16G, 8mm Bar' ) ) ), array( 'clear', 'gold' ) ),
			'fitting from a variation'                    => array( 'fitting', array( 'attributes' => array( 'pa_colours' => array( 'Gold/Clear, Threadless Pin Top' ) ) ), array( 'threadless' ) ),
			'material from a "Material:" line'            => array( 'material', array( 'description' => 'Material: UV Acrylic' ), array( 'acrylic' ) ),
			'"Materials" without a colon'                 => array( 'material', array( 'description' => 'Materials  316L Surgical Steel belly ring, crystal.' ), array( 'surgical-steel' ) ),
			'a bulleted label'                            => array( 'material', array( 'description' => '• Material: Barbell- 316L Stainless Steel, CZ' ), array( 'surgical-steel' ) ),
			'fitting from a "Style:" line'                => array( 'fitting', array( 'description' => 'Style: Threadless Push pin top and base' ), array( 'threadless' ) ),
			'fitting from a "Threading:" line'            => array( 'fitting', array( 'description' => 'Threading: Internally Threaded' ), array( 'internally-threaded' ) ),
			'a label that gives way to a second'          => array( 'gauge', array( 'description' => 'Screws into - Dermal Anchor Gauge : 14GA(1.6mm)' ), array( '1.6' ) ),
			'a label heading the lines under it'          => array( 'size', array( 'description' => "Sizes:\n16GA(1.2mm), 8mm or 10mm\n14GA(1.6mm), 10mm\n\nBioFlex bar is flexible" ), array( '8', '10' ) ),
			'a heading ends at the next label'            => array( 'size', array( 'description' => "Bar Length:\nHead Size: 8mm" ), array() ),
			'HTML descriptions'                           => array( 'material', array( 'description' => '<p>Description: Our tunnels.</p><p>Materials: Acrylic</p>' ), array( 'acrylic' ) ),
			'the short description too'                   => array( 'material', array( 'short_description' => 'Material: Silicone' ), array( 'silicone' ) ),
			// Free text mentions other fittings and materials in passing.
			'no fitting from free text'                   => array( 'fitting', array( 'description' => "Description: A labret.\nPush-in jewellery is easier to use than internally threaded jewellery." ), array() ),
			'no theme from the description'               => array( 'theme', array( 'description' => 'Description: A belly ring with a skull, a bat and a heart' ), array() ),
			'the first sentence, when nothing else said'  => array( 'material', array( 'description' => 'Description: These Black screw-fit flat-face tunnel pairs are crafted from Black anodised steel with external threading.' ), array( 'surgical-steel' ) ),
			'only the first sentence'                     => array( 'material', array( 'description' => 'Description: Our fun glossy black silicone horseshoe plugs are flexible and lightweight. They resemble heavy-gauge steel horseshoes.' ), array( 'silicone' ) ),
			'"S. Steel" doesn\'t end the sentence'        => array( 'material', array( 'description' => 'Description: Pairs of 316L S. Steel ball studs. Unlike titanium ones.' ), array( 'surgical-steel' ) ),
			'not the first sentence over a material said' => array( 'material', array( 'description' => "Description: Our acrylic plugs.\nMaterial: Silicone" ), array( 'silicone' ) ),
			'prose under a label isn\'t a list'           => array( 'material', array( 'description' => 'Materials: We offer our threadless labrets in three materials (to suit all budgets) - Stainless Steel, 316L Surgical Steel, and ASTM Titanium.' ), array() ),
			'the first sentence can say "your"'           => array( 'material', array( 'description' => 'Description: This Horseshoe barbell features vibrant Ion Plated Titanium colours over pure 316L Surgical Steel. Horseshoes are a must have for your collection.' ), array( 'surgical-steel' ) ),
		);
	}

	/**
	 * @dataProvider categories
	 */
	public function test_categories_fill_an_empty_facet( string $facet, array $product, array $values ) {
		$this->assertSame( $values, self::facet( $product, $facet ) );
	}

	public static function categories(): array {
		return array(
			'titanium category'                => array( 'material', array( 'categories' => array( 'labrets-rings-barbells', 'lip-chin' ) ), array( 'titanium' ) ),
			'threadless category'              => array( 'fitting', array( 'categories' => array( 'pin-tops-pin-bases' ) ), array( 'threadless' ) ),
			'clicker subcategory'              => array( 'fitting', array( 'categories' => array( 'septum-daith-clickers-and-rings/clicker-rings' ) ), array( 'clicker' ) ),
			'a category\'s subcategory'        => array( 'fitting', array( 'categories' => array( 'hinged-segment-rings/extra' ) ), array( 'hinged' ) ),
			'the product\'s own material wins' => array(
				'material',
				array(
					'name'       => 'Silicone Healing Non Pull Discs 10PC Pack for Piercing Bumps',
					'categories' => array( 'labrets-rings-barbells', 'pin-tops-pin-bases' ),
				),
				array( 'silicone' ),
			),
			'the product\'s own fitting wins'  => array(
				'fitting',
				array(
					'name'       => 'Titanium Labret with Water-drop Opal Top Internally Threaded',
					'categories' => array( 'pin-tops-pin-bases' ),
				),
				array( 'internally-threaded' ),
			),
			'other categories give nothing'    => array( 'material', array( 'categories' => array( 'nose', 'sale-items' ) ), array() ),
		);
	}

	/**
	 * @dataProvider materials
	 */
	public function test_material( string $text, array $values ) {
		$this->assertSame( $values, self::facet( array( 'description' => 'Material: ' . $text ), 'material' ) );
	}

	public static function materials(): array {
		return array(
			'implant titanium'               => array( 'TITANIUM 6AL-4VELI ASTM F-136 Implant Grade | PVD', array( 'titanium' ) ),
			'"Titanium Steel" is titanium'   => array( 'Titanium Steel (Implant Grade)', array( 'titanium' ) ),
			'grade 23'                       => array( 'Grade 23 Ti6Al4V ELI. | Gold Titanium Anodised | Synthetic Opal cones', array( 'titanium' ) ),
			'"implant grade" steel'          => array( '316L Surgical Stainless Steel (implant grade), Cubic Zirconia.', array( 'surgical-steel' ) ),
			'IP titanium over steel'         => array( '316L Surgical Steel | IP Titanium | Cubic Zirconia', array( 'surgical-steel' ) ),
			'anodised titanium over steel'   => array( 'Titanium Anodised colours over Stainless Steel', array( 'surgical-steel' ) ),
			'a coloured titanium finish'     => array( 'Bar 316L Surgical Steel | Rhodium/ Gold/ Rose Gold Titanium | Cubic Zirconia', array( 'surgical-steel' ) ),
			'titanium plating'               => array( 'Bar 316L Surgical Steel | Stainless Steel, Titanium plating.', array( 'surgical-steel' ) ),
			'gold PVD over steel'            => array( '316L Surgical Steel/Brass | CZ | Gold PVD', array( 'gold-plated', 'surgical-steel' ) ),
			'gold ion plating'               => array( '316L Surgical Steel, CZ, Rose Gold Ion Plating', array( 'gold-plated', 'surgical-steel' ) ),
			'gold plate'                     => array( '14KT Rose Gold Plated over 316L Surgical Steel |  Stone', array( 'gold-plated', 'stone', 'surgical-steel' ) ),
			'O-rings aren\'t the material'   => array( 'Acrylic | Silicone', array( 'acrylic' ) ),
			'silicone on its own'            => array( 'Silicone', array( 'silicone' ) ),
			'silicone O-rings'               => array( 'Bioflex | Silicone O-Rings', array( 'bioplast' ) ),
			'sterling silver'                => array( '.925 Sterling Silver', array( 'sterling-silver' ) ),
			'a synthetic opal isn\'t stone'  => array( '316L Steel | synthetic Opal Stone', array( 'surgical-steel' ) ),
			'opalite isn\'t stone'           => array( 'Opalite Stone', array() ),
			'semi precious stone'            => array( 'Semi Precious Hematite', array( 'stone' ) ),
			'a "gemstone" is a crystal here' => array( 'Gemstone', array() ),
			'"flexible" is not bioplast'     => array( 'Flexible Silicone', array( 'silicone' ) ),
			'a glass shape isn\'t glass'     => array( 'Martini Glass charm, 316L Steel', array( 'surgical-steel' ) ),
			'pyrex glass'                    => array( 'Pyrex Glass', array( 'glass' ) ),
		);
	}

	public function test_steel_in_a_variation_is_its_colour() {
		$product = array(
			'name'       => 'Implant Grade Titanium Threadless Push In Bezal Set Flat Opal or CZ',
			'attributes' => array( 'pa_colour-size' => array( 'Steel/Aurora Borealis, 3mm Top', 'Gold/Clear, 4mm Top' ) ),
		);
		$this->assertSame( array( 'titanium' ), self::facet( $product, 'material' ) );
		$this->assertSame( array( 'ab', 'clear', 'gold', 'opal', 'silver' ), self::facet( $product, 'colour' ) );
		$this->assertSame( array( 'titanium' ), self::facet( array( 'attributes' => array( 'pa_colour-size' => array( 'Titanium, 16G, 8mm Bar' ) ) ), 'material' ) );
	}

	/**
	 * @dataProvider fittings
	 */
	public function test_fitting( string $name, array $values ) {
		$this->assertSame( $values, self::facet( array( 'name' => $name ), 'fitting' ) );
	}

	public static function fittings(): array {
		return array(
			'push in'                    => array( 'Bioplast Push In Top Labret', array( 'threadless' ) ),
			'push fit'                   => array( 'BioFlex Labret Attachment, Heart Push Fit Threadless Top', array( 'threadless' ) ),
			'press fit is a gem setting' => array( 'Press Fit Round Coloured Gem Dermal Ball Top 316L Surgical Steel', array() ),
			'internal thread'            => array( 'Labret with Casted Steel Butterfly Top Internal Threaded', array( 'internally-threaded' ) ),
			'external thread'            => array( '316L Steel Curved Barbells w/ Balls External Thread, Eyebrow Cartilage', array( 'externally-threaded' ) ),
			'a hinged clicker is both'   => array( 'Septum Clicker Hinged Plain U Shape Nose Ring', array( 'clicker', 'hinged' ) ),
		);
	}

	/**
	 * @dataProvider colours
	 */
	public function test_colour( array $product, array $values ) {
		$this->assertSame( $values, self::facet( $product, 'colour' ) );
	}

	public static function colours(): array {
		return array(
			'rose gold isn\'t also gold'        => array( array( 'name' => 'Ear Cartilage Flat Back Labret Stud with Ball Top in Rose Gold' ), array( 'rose-gold' ) ),
			'gold plated is gold'               => array( array( 'name' => '18K Gold Plated CZ Cherry Dangle Belly Ring' ), array( 'clear', 'gold' ) ),
			'"Steel" in a list is silver'       => array( array( 'description' => 'Colours: Gold | Steel' ), array( 'gold', 'silver' ) ),
			'"Titanium" in a list is silver'    => array( array( 'attributes' => array( 'pa_colours' => array( 'Titanium/Clear, Threadless Pin Top' ) ) ), array( 'clear', 'silver' ) ),
			'"Steel" in a name isn\'t a colour' => array( array( 'name' => '316L Steel Curved Barbell' ), array() ),
			'"Rose" in a list is pink'          => array( array( 'attributes' => array( 'pa_colours' => array( 'Rose', 'Light Rose', 'Rose Gold' ) ) ), array( 'pink', 'rose-gold' ) ),
			'"Rose" in a name isn\'t a colour'  => array( array( 'name' => 'Single Rose Belly Ring' ), array() ),
			'anodised gold is gold'             => array( array( 'name' => 'Labret with Micro Gem Top in Anodised Gold Internally Threaded' ), array( 'gold' ) ),
			'"Anodised" alone isn\'t rainbow'   => array( array( 'name' => 'Double Spike Barbell Stud in Anodised Colours' ), array() ),
			'a colour before a stone'           => array( array( 'name' => 'Blue Crystal Crescent Moon Love Heart Nipple Ring Pairs' ), array( 'blue' ) ),
			'a stone and another colour'        => array( array( 'name' => 'Crystal Set Red Spider Curved Barbells' ), array( 'clear', 'red' ) ),
			'pink CZ isn\'t also clear'         => array( array( 'name' => 'Pink CZ Heart Labret' ), array( 'pink' ) ),
			'AB crystal'                        => array( array( 'attributes' => array( 'pa_colours' => array( 'AB Crystal', 'Clear Crystal' ) ) ), array( 'ab', 'clear' ) ),
			'a CZ is clear'                     => array( array( 'name' => 'CZ Septum Ring' ), array( 'clear' ) ),
			'not when the colours are listed'   => array(
				array(
					'name'       => 'Labret Cartilage Stud With Threadless Push In Pin Colourful Prong CZ',
					'attributes' => array( 'pa_colours' => array( 'Pink', 'Aqua' ) ),
				),
				array( 'aqua', 'pink' ),
			),
			'unless clear is listed'            => array(
				array(
					'name'        => 'Triple Row CZ Flower Top Labret',
					'description' => 'Colour : Rhodium/Clear | Rhodium/AB | Gold/Clear',
				),
				array( 'ab', 'clear', 'gold', 'silver' ),
			),
			'a list without colours leaves it'  => array(
				array(
					'name'       => 'Cartilage/ Tragus Barbell W/ Prong Set CZ Top In Mix Shapes',
					'attributes' => array( 'pa_size' => array( '16G, 6mm, 5mm Heart CZ' ) ),
				),
				array( 'clear' ),
			),
			'siam, sapphire, tanzanite'         => array( array( 'description' => 'Colours:  Rose ,Lt Sapphire, Sapphire. AB Crystal, Siam, Tanzanite' ), array( 'ab', 'blue', 'pink', 'purple', 'red' ) ),
			'rhodium and platinum are silver'   => array( array( 'description' => 'Colours: Rhodium | Platinum Plated | Silver Tone' ), array( 'silver' ) ),
			'IP yellow gold'                    => array( array( 'name' => '18G/ 20G Press Fit CZ Nose Bone IP Yellow/ Rose Gold' ), array( 'clear', 'gold', 'rose-gold' ) ),
			'black O-rings aren\'t the colour'  => array( array( 'name' => 'Crescent Pincher Taper Pairs with Black O-Rings' ), array() ),
			'"No Pink"'                         => array( array( 'attributes' => array( 'pa_colour-size' => array( '3PCS Bioplast Bar, No Pink' ) ) ), array() ),
			'a great white shark'               => array( array( 'name' => '316L Surgical Steel Great White Shark Industrial Barbell' ), array() ),
			'a "Material:" line\'s finish'      => array( array( 'description' => 'Material: 316L Surgical Steel | CZ | Gold PVD' ), array( 'gold' ) ),
			'not a "Material:" line\'s stones'  => array( array( 'description' => 'Material: 316L Surgical Steel | Crystal | Turquoise' ), array() ),
			'turquoise is aqua, onyx black'     => array( array( 'name' => 'Captive Bead Ring with Turquoise and Onyx Balls' ), array( 'aqua', 'black' ) ),
		);
	}

	/**
	 * @dataProvider themes
	 */
	public function test_theme( string $name, array $values ) {
		$this->assertSame( $values, self::facet( array( 'name' => $name ), 'theme' ) );
	}

	public static function themes(): array {
		return array(
			'skull'              => array( '100% 316L Surgical Steel Labret with Micro Grinning Skull Top', array( 'spooky' ) ),
			'moon and star'      => array( 'Celestial Triple Chain Industrial Barbell with Moon and Star/ O-Rings', array( 'celestial' ) ),
			'several'            => array( 'Horseshoe Barbell with Gem Set Flower and Star Ends', array( 'celestial', 'floral' ) ),
			'an evil eye'        => array( 'Cartilage/ Tragus Barbell with Small Crystal Set Evil Eye Earring', array() ),
			'a crescent pincher' => array( 'Crescent/ Pincher Taper Pairs with O-Rings for Septum, Earrings', array() ),
			'a crescent moon'    => array( 'Crescent Moon CZ Labret with Round CZ Dangle Internally Threaded', array( 'celestial' ) ),
			'snake bites'        => array( 'Labret Studs for Snake Bites', array() ),
			'a snake'            => array( 'ASTM Titanium Labret with Snake Top Internally Threaded', array( 'animal' ) ),
			'a cat\'s eye'       => array( 'Cats Eye Stone Belly Ring', array() ),
		);
	}

	/**
	 * @dataProvider gauges
	 */
	public function test_gauge( array $product, array $values ) {
		$this->assertSame( $values, self::facet( $product, 'gauge' ) );
	}

	public static function gauges(): array {
		$tunnel = array( 'categories' => array( 'ear-cartilage/tunnels-plugs-tapers' ) );
		return array(
			'16G in a name'                     => array( array( 'name' => '16G Barbell Pink Heart Center' ), array( '1.2' ) ),
			'"18G/ 20G"'                        => array( array( 'name' => '18G/ 20G Half Ball Dome Top Nose Stud' ), array( '0.8', '1' ) ),
			'"20 Guage"'                        => array( array( 'tags' => array( '20 Guage Flower Nose Screw' ) ), array( '0.8' ) ),
			'"16 gauge"'                        => array( array( 'name' => 'Labret in 16 gauge' ), array( '1.2' ) ),
			'"Gauge approx: 16GA(1.2mm)"'       => array( array( 'description' => 'Gauge approx: 16GA(1.2mm)' ), array( '1.2' ) ),
			'"1.2mm (16G)"'                     => array( array( 'description' => 'Size: 1.2mm (16G), 6mm bar, 4*4mm ball.' ), array( '1.2' ) ),
			'"1.6mm/ 14GA"'                     => array( array( 'attributes' => array( 'pa_mm-guage-size' => array( '1.6mm/ 14GA', '14GA/ 1.6mm' ) ) ), array( '1.6' ) ),
			'"14GA l 1.6mm"'                    => array( array( 'description' => '• Bar Thickness: 14GA l 1.6mm' ), array( '1.6' ) ),
			'the gauge wins a disagreement'     => array( array( 'description' => 'Gauge: 18GA(1.2mm)' ), array( '1' ) ),
			'a thickness in mm under its label' => array( array( 'description' => 'Thickness: 1.2mm | 1.6mm' ), array( '1.2', '1.6' ) ),
			'"2mm Thick"'                       => array( array( 'name' => 'Pair of Hinged 316L Stainless Steel 2mm Thick Round Hoop Earrings' ), array( '2' ) ),
			'a thin mm in a gauge attribute'    => array( array( 'attributes' => array( 'pa_mm-guage-size' => array( '1.6mm' ) ) ), array( '1.6' ) ),
			'a first thin mm before a length'   => array( array( 'attributes' => array( 'pa_size' => array( '1.2mm, 8mm, 3mm' ) ) ), array( '1.2' ) ),
			'a mm in a name is no gauge'        => array( array( 'name' => '1.5mm Micro Ball Top Titanium Nose Bone Stud' ), array() ),
			'a gem\'s mm is no gauge'           => array( array( 'attributes' => array( 'pa_colour-size' => array( 'Steel/Clear 2mm, 16G, 6mm Bar' ) ) ), array( '1.2' ) ),
			'22g, the thinnest gauge'           => array( array( 'name' => '22G Nose Screw' ), array( '0.6' ) ),
			'a gauge GAUGES doesn\'t know'      => array( array( 'name' => '24G Nose Screw' ), array() ),
			'a thick gauge on its own'          => array( array( 'name' => '6G Septum Retainer' ), array( '4' ) ),
			'a thick gauge before a length'     => array( array( 'attributes' => array( 'pa_size' => array( '8G(3mm), 12mm, 6*6mm' ) ) ), array( '3' ) ),
			'a ball stud\'s "3mm (8G)"'         => array( array( 'attributes' => array( 'pa_colour-size' => array( 'Black 3mm (8G)' ) ) ), array() ),
			'a thick gauge under a gauge label' => array( array( 'description' => 'Gauge Sizes: 8GA(3mm) | 6GA(4mm)' ), array( '3', '4' ) ),
			'stretching: mm are gauges'         => array( $tunnel + array( 'attributes' => array( 'pa_mm-guage-size' => array( '10mm(00G) Pair', '12mm Pair', '6mm(2G) Pair' ) ) ), array( '6', '10', '12' ) ),
			'stretching: the mm wins'           => array( $tunnel + array( 'attributes' => array( 'pa_mm-guage-size' => array( '3mm(10G) Pair' ) ) ), array( '3' ) ),
			'stretching: a range\'s ends'       => array( $tunnel + array( 'description' => 'Size: 8mm to 14mm.' ), array( '8', '14' ) ),
			'stretching: in the name'           => array( $tunnel + array( 'name' => 'Acrylic Tribal Wing Hanger Pairs 3-5mm' ), array( '3', '5' ) ),
			'stretching: a gauge GAUGES lacks'  => array( $tunnel + array( 'description' => 'Sizes: 1.6mm(14G) | 2mm(12G)mm | 25mm(1G)' ), array( '1.6', '2', '25' ) ),
			'stretching: not a top\'s'          => array( $tunnel + array( 'description' => 'Top Size: 16mm' ), array() ),
		);
	}

	/**
	 * @dataProvider sizes
	 */
	public function test_size( array $product, array $values ) {
		$this->assertSame( $values, self::facet( $product, 'size' ) );
	}

	public static function sizes(): array {
		$attribute = fn( $value ) => array( 'attributes' => array( 'pa_size' => array( $value ) ) );
		$line      = fn( $text ) => array( 'description' => $text );
		return array(
			'"8mm Ring"'                           => array( $attribute( 'Gold, 16G, 8mm Ring' ), array( '8' ) ),
			'the bare mm after the gauge'          => array( $attribute( '16G, 8mm, 3*3mm' ), array( '8' ) ),
			'not the ball after it'                => array( $attribute( '16G, 8mm, 3mm' ), array( '8' ) ),
			'"16G/ 8mm"'                           => array( $attribute( '16G/ 8mm' ), array( '8' ) ),
			'not a gem'                            => array( $attribute( '16G, 6mm, 5mm Round CZ' ), array( '6' ) ),
			'not a coloured gem after the gauge'   => array( $attribute( '18G/ Steel/ Clear 3mm' ), array() ),
			'not a spike'                          => array( $attribute( '16G, 10mm Bar, 4x9mm Spike' ), array( '10' ) ),
			'bars named once'                      => array( $attribute( '16GA, 6/8/10mm Bars, AB Gem 2mm' ), array( '6', '8', '10' ) ),
			'"Bar Length: 6mm"'                    => array( $line( 'Bar Length: 6mm' ), array( '6' ) ),
			'"Ring Inner Diameter"'                => array( $line( 'Ring Inner Diameter: 6mm | 8mm | 10mm' ), array( '6', '8', '10' ) ),
			'"(inner diam)"'                       => array( $line( 'Sizes: 8mm | 10mm (inner diam)' ), array( '8', '10' ) ),
			'"Size:" with a ball'                  => array( $line( 'Size: 1.2mm(16G), 6mm bar, 4*4mm ball.' ), array( '6' ) ),
			'"Size approx" with a ball'            => array( $line( 'Size approx: 20GA(0.8mm) | 7mm | 1.5mm ball' ), array( '7' ) ),
			'not a charm'                          => array( $line( 'Size:  1.6mm(14GA), 10mm bar, Dragon 20mm.' ), array( '10' ) ),
			'not "Ball Size"'                      => array( $line( 'Ball Size: 5mm' ), array() ),
			'not "Butterfly Size"'                 => array( $line( 'Butterfly Size: 10.8mm' ), array() ),
			'not a dangle\'s "Total Length"'       => array( $line( 'Total Length: 23mm' ), array() ),
			'not a "Full Length"'                  => array( $line( 'Size: 14GA(1.6mm) | Bar 10mm | Full Length 25mm' ), array( '10' ) ),
			'an industrial\'s "Bars Length Total"' => array( $line( 'Bars Length Total: 45mm' ), array( '45' ) ),
			'usable length'                        => array( $line( 'Usable Length: 33mm Usable bar' ), array( '33' ) ),
			'"Sizing"'                             => array( $line( 'Sizing : 8mm (inner-bar to base).' ), array( '8' ) ),
			'"12, 14, 16mm bar"'                   => array( $line( "Size approx:\n14GA(1.6mm), 12, 14, 16mm bar, 5*5mm balls." ), array( '12', '14', '16' ) ),
			'"Gems 3/ 4/ 5mm"'                     => array( $line( 'Size:  16GA(1.2mm), Bar length 6mm, Gems 3/ 4/ 5mm, ball 3mm.' ), array( '6' ) ),
			'"5mm x 7mm" is one top'               => array( $line( 'Top Size: 5mm x 7mm' ), array() ),
			'a range in a name'                    => array( array( 'name' => '1.6mm Narrow Hinged Hoop Earring Pairs, 6mm - 12mm Rings' ), array( '6', '12' ) ),
			'not a mm before a stone in a name'    => array( array( 'name' => '8mm Opal Prong Set Belly Navel Ring' ), array() ),
			'not a thin one'                       => array( $line( 'Bar Length: 2mm' ), array() ),
			'"Black 16mm" between the bar lengths' => array(
				array(
					'attributes'  => array( 'pa_colour-size' => array( 'Black 12mm', 'Black 16mm', 'Purple 18mm', 'Blue 30mm' ) ),
					'description' => "Size:\n14GA(1.6mm),12mm - 22mm bar, 5mm ball, 5mm dice",
				),
				array( '12', '16', '18', '22' ),
			),
			'a top sold on its own: its size'      => array(
				array(
					'categories' => array( 'dermal-tops' ),
					'attributes' => array( 'pa_colour-size' => array( 'Clear 3mm', 'Clear 4mm', 'Black 4mm (6G)' ) ),
				),
				array( '3', '4' ),
			),
			'a ball stud: its ball'                => array(
				array(
					'name'        => '316L S. Steel Pairs Of Sand Blasted Ball Stud Earrings',
					'description' => 'Ball Top Sizes: 3mm | 4mm |  5mm |  6mm',
				),
				array( '3', '4', '5', '6' ),
			),
			'a top with a bar: the bar'            => array(
				array(
					'categories'  => array( 'pin-tops-pin-bases' ),
					'description' => "Bar Lengths: 6mm | 8mm\nTop Sizes: 3mm",
				),
				array( '6', '8' ),
			),
			'not a top on other jewellery'         => array(
				array(
					'name'        => 'Curved Barbell with Gem',
					'description' => 'Gem Size: 4mm',
				),
				array(),
			),
			'stretching: a length is a size'       => array(
				array(
					'categories'  => array( 'ear-cartilage/tunnels-plugs-tapers' ),
					'description' => "Wearable Length: 10mm\nSize: 8mm",
				),
				array( '10' ),
			),
		);
	}
}
