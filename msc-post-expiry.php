<?php
/**
 * Plugin Name: MSC Post Expiry
 * Plugin URI: https://github.com/djm56/msc-post-expiry
 * Description: Automatically expire posts and pages on a scheduled date. Set expiration dates in the post editor and let the plugin handle the rest.
 * Version: 1.5.2
 * Author: Anomalous Developers
 * Author URI: https://anomalous.co.za
 * Text Domain: msc-post-expiry
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 7.0.2
 * Requires PHP: 7.4
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package MSCPE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current plugin version.
 */
define( 'MSCPE_PLUGIN_VERSION', '1.5.2' );

/**
 * Absolute path to the main plugin file.
 */
define( 'MSCPE_PLUGIN_FILE', __FILE__ );

/**
 * Absolute path to the plugin directory.
 */
define( 'MSCPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory.
 */
define( 'MSCPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MSCPE_PLUGIN_DIR . 'includes/class-mscpe-cron.php';
require_once MSCPE_PLUGIN_DIR . 'includes/class-mscpe-migrations.php';
require_once MSCPE_PLUGIN_DIR . 'includes/class-mscpe-seo.php';
require_once MSCPE_PLUGIN_DIR . 'includes/class-mscpe-rules.php';
require_once MSCPE_PLUGIN_DIR . 'includes/class-mscpe-analytics.php';
require_once MSCPE_PLUGIN_DIR . 'includes/class-mscpe-module.php';
require_once MSCPE_PLUGIN_DIR . 'includes/class-mscpe-settings.php';
require_once MSCPE_PLUGIN_DIR . 'includes/class-mscpe-plugin.php';

register_activation_hook(
	__FILE__,
	array( 'MSCPE\Plugin', 'activate' )
);

register_deactivation_hook(
	__FILE__,
	array( 'MSCPE\Plugin', 'deactivate' )
);

add_action(
	'plugins_loaded',
	static function () {
		MSCPE\Plugin::instance();
	}
);

/**
 * Register custom cron interval.
 *
 * @param array<string,array> $schedules WordPress cron schedules.
 * @return array<string,array>
 */
add_filter(
	'cron_schedules',
	static function ( $schedules ) {
		if ( ! isset( $schedules['mscpe_5min'] ) ) {
			$schedules['mscpe_5min'] = array(
				'interval' => 300,
				'display'  => 'Every 5 minutes',
			);
		}
		if ( ! isset( $schedules['mscpe_15min'] ) ) {
			$schedules['mscpe_15min'] = array(
				'interval' => 900,
				'display'  => 'Every 15 minutes',
			);
		}
		return $schedules;
	}
);

if ( ! function_exists( 'mscpe_get_expiry_datetime' ) ) {
	/**
	 * Get expiry date and time for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,string>|false Array with 'date' and 'time' keys, or false if not set.
	 */
	function mscpe_get_expiry_datetime( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		// Read unified timestamp first (preferred).
		$timestamp = get_post_meta( $post_id, '_mscpe_expiry_timestamp', true );
		if ( is_numeric( $timestamp ) && (int) $timestamp > 0 ) {
			$ts = (int) $timestamp;
			return array(
				'date' => wp_date( 'Y-m-d', $ts ),
				'time' => wp_date( 'H:i', $ts ),
			);
		}

		// Fallback to separate date/time fields.
		$expiry_date = get_post_meta( $post_id, 'mscpe_expiry_date', true );
		if ( ! $expiry_date ) {
			return false;
		}

		$expiry_time = get_post_meta( $post_id, 'mscpe_expiry_time', true );

		return array(
			'date' => $expiry_date,
			'time' => $expiry_time ? $expiry_time : '00:00',
		);
	}
}

if ( ! function_exists( 'mscpe_is_post_expired' ) ) {
	/**
	 * Check if a post is expired.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if post is expired, false otherwise.
	 */
	function mscpe_is_post_expired( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$expiry = mscpe_get_expiry_datetime( $post_id );
		if ( ! $expiry ) {
			return false;
		}

		$expiry_datetime = strtotime( $expiry['date'] . ' ' . $expiry['time'] );
		if ( false === $expiry_datetime ) {
			return false;
		}

		return time() >= $expiry_datetime;
	}
}

if ( ! function_exists( 'mscpe_get_expiry_status' ) ) {
	/**
	 * Get human-readable expiry status for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Expiry status text.
	 */
	function mscpe_get_expiry_status( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$expiry = mscpe_get_expiry_datetime( $post_id );
		if ( ! $expiry ) {
			return __( 'No expiry set', 'msc-post-expiry' );
		}

		$expiry_datetime = strtotime( $expiry['date'] . ' ' . $expiry['time'] );
		if ( false === $expiry_datetime ) {
			return __( 'No expiry set', 'msc-post-expiry' );
		}
		$current_datetime = time();

		if ( $current_datetime >= $expiry_datetime ) {
			return __( 'Expired', 'msc-post-expiry' );
		}

		$time_remaining = $expiry_datetime - $current_datetime;
		$days           = floor( $time_remaining / DAY_IN_SECONDS );
		$hours          = floor( ( $time_remaining % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );

		if ( $days > 0 ) {
			return sprintf(
				/* translators: %d is number of days */
				_n( '%d day remaining', '%d days remaining', $days, 'msc-post-expiry' ),
				$days
			);
		}

		if ( $hours > 0 ) {
			return sprintf(
				/* translators: %d is number of hours */
				_n( '%d hour remaining', '%d hours remaining', $hours, 'msc-post-expiry' ),
				$hours
			);
		}

		return __( 'Expires soon', 'msc-post-expiry' );
	}
}

if ( ! function_exists( 'mscpe_format_expiry_datetime' ) ) {
	/**
	 * Format expiry date and time for display.
	 *
	 * @param string $date Expiry date (YYYY-MM-DD).
	 * @param string $time Expiry time (HH:MM).
	 * @return string Formatted datetime string.
	 */
	function mscpe_format_expiry_datetime( $date, $time = '00:00' ) {
		if ( ! $date ) {
			return '';
		}

		$datetime = strtotime( $date . ' ' . $time );
		if ( ! $datetime ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $datetime );
	}
}
