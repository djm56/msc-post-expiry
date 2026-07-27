<?php
/**
 * Database migrations for MSC Post Expiry.
 *
 * Handles creation and versioning of custom tables.
 *
 * @package MSCPE
 */

namespace MSCPE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages database migrations for MSC Post Expiry.
 */
class Migrations {

	/**
	 * Current migration version.
	 */
	const MIGRATION_VERSION = '1.6.0';

	/**
	 * Option key for tracking migration version.
	 */
	const VERSION_OPTION = 'mscpe_db_version';

	/**
	 * Runs migrations if needed.
	 *
	 * @return void
	 */
	public static function run_migrations() {
		$current_version = get_option( self::VERSION_OPTION, '0.0.0' );

		if ( version_compare( $current_version, self::MIGRATION_VERSION, '>=' ) ) {
			return;
		}

		self::create_analytics_table();
		self::drop_unused_rules_table();

		update_option( self::VERSION_OPTION, self::MIGRATION_VERSION );
	}

	/**
	 * Drops the legacy rules table.
	 *
	 * Rules were always stored in the `mscpe_rules` option; the table created
	 * by earlier versions was never read or written and is removed in 1.6.0.
	 *
	 * @return void
	 */
	private static function drop_unused_rules_table() {
		global $wpdb;

		$table = esc_sql( $wpdb->prefix . 'mscpe_rules' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	/**
	 * Creates the analytics table.
	 *
	 * @return void
	 */
	private static function create_analytics_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'mscpe_analytics';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(50) NOT NULL DEFAULT '',
			category_id bigint(20) unsigned NOT NULL DEFAULT 0,
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			views_before_expiry int(11) NOT NULL DEFAULT 0,
			age_days int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'success',
			created_at bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY action (action),
			KEY category_id (category_id),
			KEY author_id (author_id),
			KEY created_at (created_at),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drops all custom tables (used on uninstall).
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'mscpe_rules',
			$wpdb->prefix . 'mscpe_analytics',
		);

		foreach ( $tables as $table ) {
			$table = esc_sql( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		delete_option( self::VERSION_OPTION );
	}
}
