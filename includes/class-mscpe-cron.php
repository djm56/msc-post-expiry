<?php
/**
 * Cron processing class for MSC Post Expiry.
 *
 * @package MSCPE
 */

namespace MSCPE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles scheduled post expiry processing via WordPress cron.
 */
class Cron {

	const CRON_HOOK          = 'mscpe_process_expired_posts';
	const CRON_INTERVAL      = 300; // 5 minutes in seconds.
	const LOG_DIR            = 'msc-post-expiry-logs';
	const LOG_RETENTION_DAYS = 30;
	const LOG_MAX_SIZE_BYTES = 1048576; // 1 MB.
	const LOG_MAX_ROTATIONS  = 3;

	/**
	 * Main plugin instance.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Hook the cron handler.
		add_action( self::CRON_HOOK, array( $this, 'process_expired_posts' ) );
	}

	/**
	 * Register the cron event on plugin activation.
	 */
	public function register_cron_event() {
		// Only register if not already scheduled.
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time(), 'mscpe_5min', self::CRON_HOOK );
	}

	/**
	 * Unregister the cron event on plugin deactivation.
	 */
	public function unregister_cron_event() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Cron handler (every 5 minutes).
	 *
	 * Since 1.6.0 the plugin uses a single expiry pipeline (Module). This
	 * handler migrates any remaining legacy `mscpe_expiry_date`/`mscpe_expiry_time`
	 * meta to the `_mscpe_expiry_timestamp` meta used by the pipeline, then
	 * delegates processing to the Module so Smart Rules, per-post overrides,
	 * SEO handling, notifications and analytics all apply on the 5-minute cadence.
	 */
	public function process_expired_posts() {
		$module_enabled = $this->plugin->get_option( 'module_enabled', 1 );
		if ( empty( $module_enabled ) ) {
			return;
		}

		$migrated = $this->migrate_legacy_meta();
		if ( $migrated > 0 ) {
			$this->log_event( sprintf( 'Migrated legacy expiry meta on %d posts to timestamp meta.', $migrated ) );
		}

		$module = $this->plugin->get_module();
		if ( $module ) {
			$module->process_expired_posts();
		}

		// Clean up old logs.
		$this->cleanup_old_logs();
	}

	/**
	 * Converts legacy date/time expiry meta to the timestamp meta.
	 *
	 * Parses legacy site-local date/time strings with wp_timezone() so the
	 * stored epoch matches the wall-clock time the user originally entered.
	 *
	 * @return int Number of posts migrated.
	 */
	private function migrate_legacy_meta() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'mscpe_expiry_date' LIMIT 200"
		);

		if ( empty( $post_ids ) ) {
			return 0;
		}

		$migrated = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id     = (int) $post_id;
			$expiry_date = (string) get_post_meta( $post_id, 'mscpe_expiry_date', true );
			$expiry_time = (string) get_post_meta( $post_id, 'mscpe_expiry_time', true );

			$existing_ts = (int) get_post_meta( $post_id, Module::META_KEY_EXPIRY, true );

			if ( $existing_ts <= 0 && '' !== $expiry_date ) {
				// Legacy date/time meta was entered in site-local time; parse it with
				// the site's timezone so the stored epoch matches the intended instant.
				$effective_time = ( '' !== $expiry_time ) ? $expiry_time : '00:00';
				$dt             = date_create_immutable( "$expiry_date $effective_time", wp_timezone() );
				$timestamp      = $dt ? $dt->getTimestamp() : false;
				if ( $timestamp ) {
					update_post_meta( $post_id, Module::META_KEY_EXPIRY, $timestamp );
				}
			}

			delete_post_meta( $post_id, 'mscpe_expiry_date' );
			delete_post_meta( $post_id, 'mscpe_expiry_time' );
			++$migrated;
		}

		return $migrated;
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions
	// Log management intentionally uses direct filesystem primitives for
	// append-only writes, size-based rotation and directory hardening.

	/**
	 * Log an event.
	 *
	 * Respects the plugin setting, rotates by size, appends only, and protects
	 * the log directory against direct web access (BUG 7).
	 *
	 * @param string $message Log message.
	 * @param mixed  $context Optional additional context to append to the message.
	 */
	private function log_event( $message, $context = '' ) {
		if ( ! (bool) apply_filters( 'mscpe_enable_logging', (bool) $this->plugin->get_option( 'log_enabled', 0 ) ) ) {
			return;
		}

		$log_dir  = wp_upload_dir();
		$log_path = $log_dir['basedir'] . '/' . self::LOG_DIR;
		$log_file = $log_path . '/msc-post-expiry.log';

		// Create log directory and harden it on first use.
		if ( ! is_dir( $log_path ) ) {
			wp_mkdir_p( $log_path );
		}
		$this->write_log_directory_guards( $log_path );

		// Rotate by size before appending (BUG 7).
		if ( file_exists( $log_file ) && filesize( $log_file ) > self::LOG_MAX_SIZE_BYTES ) {
			$this->rotate_log_file( $log_path );
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );

		// Append optional context to the message for richer debugging output.
		if ( '' !== $context ) {
			$message .= ' | Context: ' . $context;
		}

		$log_entry = sprintf( "[%s] %s\n", $timestamp, $message );

		// Append-only write: no whole-file read+rewrite (BUG 7).
		$handle = fopen( $log_file, 'a' );
		if ( false === $handle ) {
			return;
		}
		fwrite( $handle, $log_entry );
		fclose( $handle );
	}

	/**
	 * Write protective files into the log directory.
	 *
	 * Adds a blank index.php guard and an Apache .htaccess rule that denies
	 * direct access to .log files. The .htaccess is best-effort (Apache only)
	 * and is recreated if removed (BUG 7).
	 *
	 * @param string $log_path Absolute log directory path.
	 * @return void
	 */
	private function write_log_directory_guards( $log_path ) {
		// Blank index.php guard to stop directory listings on some servers.
		$index_source = MSCPE_PLUGIN_DIR . 'includes/index-log.php';
		$index_dest   = $log_path . '/index.php';
		if ( file_exists( $index_source ) && ! file_exists( $index_dest ) ) {
			copy( $index_source, $index_dest );
		}

		// Apache: deny direct download of .log files.
		$htaccess_file = $log_path . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$rules  = "# Block direct access to log files\n";
			$rules .= "<FilesMatch \"\\.log$\">\n";
			$rules .= "\t<IfModule mod_authz_core.c>\n";
			$rules .= "\t\tRequire all denied\n";
			$rules .= "\t</IfModule>\n";
			$rules .= "\t<IfModule !mod_authz_core.c>\n";
			$rules .= "\t\tOrder allow,deny\n";
			$rules .= "\t\tDeny from all\n";
			$rules .= "\t</IfModule>\n";
			$rules .= "</FilesMatch>\n";
			file_put_contents( $htaccess_file, $rules );
		}
	}

	/**
	 * Rotate the current log file when it exceeds the size limit.
	 *
	 * Renames the active log to a timestamped sibling and prunes older
	 * rotations so the directory does not grow without bound (BUG 7).
	 *
	 * @param string $log_path Absolute log directory path.
	 * @return void
	 */
	private function rotate_log_file( $log_path ) {
		$log_file = $log_path . '/msc-post-expiry.log';
		if ( ! file_exists( $log_file ) ) {
			return;
		}

		$rotated_file = $log_path . '/msc-post-expiry-' . gmdate( 'Ymd-His' ) . '.log';
		rename( $log_file, $rotated_file );

		// Keep only the newest LOG_MAX_ROTATIONS files.
		$rotated_files = glob( $log_path . '/msc-post-expiry-*.log' );
		if ( false === $rotated_files || count( $rotated_files ) <= self::LOG_MAX_ROTATIONS ) {
			return;
		}

		usort(
			$rotated_files,
			function ( $a, $b ) {
				$time_a = filemtime( $a );
				$time_b = filemtime( $b );
				if ( $time_a === $time_b ) {
					return 0;
				}
				return ( $time_a < $time_b ) ? 1 : -1;
			}
		);

		$to_delete = array_slice( $rotated_files, self::LOG_MAX_ROTATIONS );
		foreach ( $to_delete as $file ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Clean up old log files.
	 *
	 * Deletes rotated log files older than the retention period. The active log
	 * is rotated by size in {@see self::rotate_log_file()}, so cleanup is
	 * consistent with that scheme (BUG 7).
	 */
	private function cleanup_old_logs() {
		$log_dir  = wp_upload_dir();
		$log_path = $log_dir['basedir'] . '/' . self::LOG_DIR;

		if ( ! is_dir( $log_path ) ) {
			return;
		}

		$retention_days = (int) apply_filters( 'mscpe_cron_log_retention_days', self::LOG_RETENTION_DAYS );
		$cutoff_time    = time() - ( $retention_days * DAY_IN_SECONDS );

		$files = glob( $log_path . '/*.log' );
		if ( false === $files ) {
			return;
		}

		foreach ( $files as $file_path ) {
			// Keep non-rotated files; they are managed by size-based rotation.
			if ( ! is_file( $file_path ) ) {
				continue;
			}

			$file_name = basename( $file_path );
			if ( 'msc-post-expiry.log' === $file_name ) {
				continue;
			}

			$file_time = filemtime( $file_path );
			if ( $file_time && $file_time < $cutoff_time ) {
				wp_delete_file( $file_path );
			}
		}
	}

	// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_read
}
