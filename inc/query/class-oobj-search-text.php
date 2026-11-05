<?php
/**
 * Class OOBJ_SEARCH_Text.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

/**
 * Text as both a query and a product are compared: lower case, words separated by single spaces, and the few
 * symbols that carry meaning kept ($ < > . / - * and digits). Plain PHP, no WordPress.
 */
class OOBJ_SEARCH_Text {

	/**
	 * Normalises text: "Titanium  Threadless  —  16G, $10!" becomes "titanium threadless - 16g $10".
	 *
	 * @param string $text Any text.
	 */
	public static function normalise( string $text ): string {
		// A "<" that can't open a tag ("<10", "< $10") is a less-than sign, not markup to strip.
		$text = (string) preg_replace( '/<(?=[\d$\s=]|$)/', '&lt;', $text );
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- plain PHP, no WordPress.
		$text = mb_strtolower( $text, 'UTF-8' );
		// A plus after a number ("$20+") means "and over".
		$text = (string) preg_replace( '/(\d)\s*\+/', '$1 plus ', $text );
		$text = strtr(
			$text,
			array(
				'&'        => ' and ',
				"\u{00A0}" => ' ',
				'×'        => '*',
				"\u{2013}" => '-',
				"\u{2014}" => '-',
				"\u{2018}" => '',
				"\u{2019}" => '',
				"'"        => '',
				'"'        => ' ',
				'+'        => ' ',
			)
		);
		// Everything but letters, digits and the meaningful symbols separates words.
		$text = (string) preg_replace( '/[^\p{L}\p{N}$<>.\/*\-\s]+/u', ' ', $text );
		// A full stop or slash that isn't between digits (end of a sentence, "and/or") separates words too.
		$text = (string) preg_replace( '/(?<!\d)[.\/]|[.\/](?!\d)/', ' ', $text );
		// A hyphen joining words ("push-pin") is a space; a minus or range between numbers stays.
		$text = (string) preg_replace( '/(?<!\d)-|-(?!\d|\$)/', ' ', $text );
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Whether normalised text holds the phrase as whole words.
	 *
	 * @param string $text   Normalised text.
	 * @param string $phrase A normalised phrase.
	 */
	public static function has( string $text, string $phrase ): bool {
		return 1 === preg_match( self::pattern( $phrase ), $text );
	}

	/**
	 * The regular expression that finds a normalised phrase as whole words.
	 *
	 * @param string $phrase A normalised phrase.
	 */
	public static function pattern( string $phrase ): string {
		return '/(?<![\p{L}\p{N}])' . preg_quote( $phrase, '/' ) . '(?![\p{L}\p{N}])/u';
	}
}
