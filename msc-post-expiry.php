<?php
/**
 * Plugin Name: MSC Post Expiry
 * Plugin URI: https://github.com/djm56/msc-post-expiry
 * Description: Automatically expire posts and pages on a scheduled date. Set expiration dates in the post editor and let the plugin handle the rest.
 * Version: 1.7.4
 * Author: Anomalous Developers
 * Author URI: https://anomalous.co.za
 * Text Domain: msc-post-expiry
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 7.1
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
define( 'MSCPE_PLUGIN_VERSION', '1.7.4' );

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
		// WordPress auto-loads plugin translations since 4.6 (Requires at least
		// is 5.9), so __() is sufficient for cron schedule labels shown later.
		if ( ! isset( $schedules['mscpe_5min'] ) ) {
			$schedules['mscpe_5min'] = array(
				'interval' => 300,
				'display'  => __( 'Every 5 minutes', 'msc-post-expiry' ),
			);
		}
		if ( ! isset( $schedules['mscpe_15min'] ) ) {
			$schedules['mscpe_15min'] = array(
				'interval' => 900,
				'display'  => __( 'Every 15 minutes', 'msc-post-expiry' ),
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

		// Compare the stored UTC epoch directly; formatting → parsing round-trips
		// drift by the site offset when WordPress forces PHP's timezone to UTC.
		$timestamp = get_post_meta( $post_id, '_mscpe_expiry_timestamp', true );
		if ( is_numeric( $timestamp ) && (int) $timestamp > 0 ) {
			return time() >= (int) $timestamp;
		}

		// Fallback for legacy date/time meta (pre-1.6.0).
		$expiry = mscpe_get_expiry_datetime( $post_id );
		if ( ! $expiry ) {
			return false;
		}

		$dt = date_create_immutable( $expiry['date'] . ' ' . $expiry['time'], wp_timezone() );
		if ( false === $dt ) {
			return false;
		}

		return time() >= $dt->getTimestamp();
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

		// Use the stored UTC epoch directly to avoid timezone round-trip drift.
		$timestamp = get_post_meta( $post_id, '_mscpe_expiry_timestamp', true );
		if ( is_numeric( $timestamp ) && (int) $timestamp > 0 ) {
			$expiry_datetime = (int) $timestamp;
		} else {
			// Fallback for legacy date/time meta (pre-1.6.0).
			$expiry = mscpe_get_expiry_datetime( $post_id );
			if ( ! $expiry ) {
				return __( 'No expiry set', 'msc-post-expiry' );
			}

			$dt = date_create_immutable( $expiry['date'] . ' ' . $expiry['time'], wp_timezone() );
			if ( false === $dt ) {
				return __( 'No expiry set', 'msc-post-expiry' );
			}
			$expiry_datetime = $dt->getTimestamp();
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

		// Expiry date/time inputs are site-local wall-clock values; parse them in
		// the site's timezone so the displayed instant matches what the user sees.
		$dt = date_create_immutable( $date . ' ' . $time, wp_timezone() );
		if ( false === $dt ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dt->getTimestamp() );
	}
}
