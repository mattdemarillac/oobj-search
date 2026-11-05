<?php
/**
 * OOBJ_SEARCH_Text: queries and product text compared the same way.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class TextTest extends TestCase {

	/**
	 * @dataProvider texts
	 */
	public function test_normalise( string $text, string $normalised ) {
		$this->assertSame( $normalised, OOBJ_SEARCH_Text::normalise( $text ) );
	}

	public static function texts(): array {
		return array(
			'case and spaces'          => array( '  Titanium   Threadless ', 'titanium threadless' ),
			'symbols kept'             => array( 'Under $10!', 'under $10' ),
			'decimals and ranges kept' => array( '1.2mm, $5-10, 3*3mm', '1.2mm $5-10 3*3mm' ),
			'hyphenated words split'   => array( 'Push-pin top', 'push pin top' ),
			'sentence stops dropped'   => array( 'Gold. Silver/Black', 'gold silver black' ),
			'ampersand'                => array( 'Gold & Silver', 'gold and silver' ),
			'apostrophes'              => array( "Women's tiger’s eye", 'womens tigers eye' ),
			'markup and entities'      => array( '<b>18K</b>&nbsp;gold', '18k gold' ),
			'times sign'               => array( '3×6mm', '3*6mm' ),
			'dashes'                   => array( "Septum \u{2013} 16G", 'septum 16g' ),
			'fraction slash kept'      => array( '6/8/10mm Bars', '6/8/10mm bars' ),
			'plus after a number'      => array( '$20+ rings', '$20 plus rings' ),
			'plus between words'       => array( 'Gold+Silver', 'gold silver' ),
			'less-than sign kept'      => array( 'Studs <10, <$5 or < 3', 'studs <10 <$5 or < 3' ),
		);
	}

	public function test_whole_words_only() {
		$this->assertTrue( OOBJ_SEARCH_Text::has( 'titanium labret', 'titanium' ) );
		$this->assertFalse( OOBJ_SEARCH_Text::has( 'titanium labret', 'ti' ) );
		$this->assertTrue( OOBJ_SEARCH_Text::has( 'rose gold ring', 'rose gold' ) );
		$this->assertFalse( OOBJ_SEARCH_Text::has( 'golden', 'gold' ) );
	}
}
