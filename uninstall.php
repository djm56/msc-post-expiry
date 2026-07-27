<?php
/**
 * Uninstall MSC Post Expiry.
 *
 * @package MSCPE
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mscpe_options' );
delete_option( 'mscpe_seo_options' );
delete_option( 'mscpe_rules' );
delete_option( 'mscpe_action_log' );
delete_option( 'mscpe_db_version' );
delete_option( 'mscpe_activated_time' );
delete_option( 'mscpe_review_dismissed' );

// Unschedule cron events.
$mscpe_hooks = array( 'mscpe_process_expired_posts', 'mscpe_process_expiry_advanced' );
foreach ( $mscpe_hooks as $mscpe_hook ) {
	$mscpe_next = wp_next_scheduled( $mscpe_hook );
	while ( $mscpe_next ) {
		wp_unschedule_event( $mscpe_next, $mscpe_hook );
		$mscpe_next = wp_next_scheduled( $mscpe_hook );
	}
}

// Drop custom tables.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mscpe_analytics" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mscpe_rules" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Delete post meta data (both mscpe_ and _mscpe_ patterns).
$mscpe_pattern = $wpdb->esc_like( 'mscpe_' ) . '%';
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$mscpe_pattern
	)
);

$mscpe_pattern2 = $wpdb->esc_like( '_mscpe_' ) . '%';
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$mscpe_pattern2
	)
);
