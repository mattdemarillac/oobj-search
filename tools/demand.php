<?php
/**
 * Rebuilds OOBJ_SEARCH_Vocabulary::DEMAND from real searches. See docs/demand.md.
 *
 * Usage: php tools/demand.php <queries.txt>
 *
 * Prints the DEMAND constant, ready to paste over the old one, and a summary on stderr.
 *
 * @package oobj-search
 */

declare( strict_types=1 );

// Each section's weight per row: a site search counts once per search, a Google query once per 50 impressions (an
// impression is a weaker signal than a search on the site), a server-log search once.
const WEIGHTS = array(
	'site-search-ga4'       => 1,
	'site-search-logs'      => 1,
	'google-search-console' => 1 / 50,
);

// The facets DEMAND covers. Categories and sizes are left out: categories are only suggested in season, and gauges'
// and sizes' buttons go smallest first.
const FACETS = array( 'material', 'fitting', 'colour', 'theme', 'gauge' );

if ( ! isset( $argv[1] ) || ! is_readable( $argv[1] ) ) {
	fwrite( STDERR, "Usage: php tools/demand.php <queries.txt>\n" );
	exit( 1 );
}

foreach ( glob( dirname( __DIR__ ) . '/inc/query/*.php' ) as $file ) {
	require_once $file;
}

$parser  = new OOBJ_SEARCH_Query_Parser();
$demand  = array();
$section = null;
foreach ( file( $argv[1], FILE_IGNORE_NEW_LINES ) as $number => $line ) {
	if ( str_starts_with( $line, '## ' ) ) {
		$section = trim( substr( $line, 3 ) );
		if ( ! isset( WEIGHTS[ $section ] ) ) {
			fwrite( STDERR, 'Unknown section "' . $section . '" on line ' . ( $number + 1 ) . "\n" );
			exit( 1 );
		}
		continue;
	}
	if ( '' === trim( $line ) || str_starts_with( $line, '#' ) || null === $section ) {
		continue;
	}
	// "count<TAB>query", or a bare query counting 1.
	$parts                 = explode( "\t", $line, 2 );
	list( $count, $query ) = 2 === count( $parts ) ? array( (float) $parts[0], $parts[1] ) : array( 1.0, $parts[0] );
	foreach ( $parser->parse( $query )->facets as $facet => $values ) {
		foreach ( $values as $value ) {
			$demand[ $facet ][ $value ] = ( $demand[ $facet ][ $value ] ?? 0 ) + $count * WEIGHTS[ $section ];
		}
	}
}//end foreach

echo "\tconst DEMAND = array(\n";
foreach ( FACETS as $facet ) {
	$values = array_filter( array_map( 'round', $demand[ $facet ] ?? array() ) );
	if ( array() === $values ) {
		continue;
	}
	arsort( $values );
	echo "\t\t'$facet' => array(\n";
	foreach ( $values as $value => $weight ) {
		echo "\t\t\t'" . addslashes( (string) $value ) . "' => " . (int) $weight . ",\n";
	}
	echo "\t\t),\n";
	fwrite( STDERR, $facet . ': ' . count( $values ) . " values\n" );
}
echo "\t);\n";
