<?php
/**
 * Deleting the plugin deletes the product facts it kept (its taxonomy's terms and their links to products).
 *
 * @package oobj-search
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$oobj_search_tt = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'oobj_search_fact' ) );
if ( $oobj_search_tt ) {
	$oobj_search_in = implode( ',', array_map( 'intval', $oobj_search_tt ) );
	$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($oobj_search_in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DELETE t FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.term_taxonomy_id IN ($oobj_search_in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ($oobj_search_in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
delete_option( 'oobj_search_facts_version' );
// phpcs:enable WordPress.DB.DirectDatabaseQuery
