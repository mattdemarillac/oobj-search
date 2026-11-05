<?php
/**
 * The real searches the query reader doesn't fully understand: what's left over as words to search for, most searched
 * first. The list of what to teach the vocabulary next. See docs/tuning.md.
 *
 * Usage: php tools/unread.php <queries.txt> [limit]
 *
 * Reads the same file as tools/demand.php (sections, "count<TAB>query" lines).
 *
 * @package oobj-search
 */

declare( strict_types=1 );

const WEIGHTS = array(
	'site-search-ga4'       => 1,
	'site-search-logs'      => 1,
	'google-search-console' => 1 / 50,
);

if ( ! isset( $argv[1] ) || ! is_readable( $argv[1] ) ) {
	fwrite( STDERR, "Usage: php tools/unread.php <queries.txt> [limit]\n" );
	exit( 1 );
}

foreach ( glob( dirname( __DIR__ ) . '/inc/query/*.php' ) as $file ) {
	require_once $file;
}

$parser  = new OOBJ_SEARCH_Query_Parser();
$words   = array();
$queries = 0;
$read    = 0;
$section = null;
foreach ( file( $argv[1], FILE_IGNORE_NEW_LINES ) as $line ) {
	if ( str_starts_with( $line, '## ' ) ) {
		$section = trim( substr( $line, 3 ) );
		continue;
	}
	if ( '' === trim( $line ) || str_starts_with( $line, '#' ) || ! isset( WEIGHTS[ $section ] ) ) {
		continue;
	}
	$parts                 = explode( "\t", $line, 2 );
	list( $count, $query ) = 2 === count( $parts ) ? array( (float) $parts[0], $parts[1] ) : array( 1.0, $parts[0] );
	$weight                = $count * WEIGHTS[ $section ];
	$parsed                = $parser->parse( $query );
	++$queries;
	if ( '' === $parsed->keywords ) {
		++$read;
		continue;
	}
	$words[ $parsed->keywords ]['weight']    = ( $words[ $parsed->keywords ]['weight'] ?? 0 ) + $weight;
	$words[ $parsed->keywords ]['example'] ??= $query;
}

uasort( $words, fn( $a, $b ) => $b['weight'] <=> $a['weight'] );
printf( "%d of %d queries fully understood. Left over, most searched first:\n", $read, $queries );
foreach ( array_slice( $words, 0, (int) ( $argv[2] ?? 50 ), true ) as $left => $row ) {
	printf( "%6.1f  %-30s  e.g. \"%s\"\n", $row['weight'], $left, $row['example'] );
}
