<?php
/**
 * Main bootstrap class for MSC Post Expiry.
 *
 * @package MSCPE
 */

namespace MSCPE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Plugin {

	const OPTION_KEY = 'mscpe_options';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Module instance.
	 *
	 * @var Module|null
	 */
	private $module = null;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Cron instance.
	 *
	 * @var Cron
	 */
	private $cron;

	/**
	 * SEO instance.
	 *
	 * @var SEO|null
	 */
	private $seo = null;

	/**
	 * Rules instance.
	 *
	 * @var Rules|null
	 */
	private $rules = null;

	/**
	 * Analytics instance.
	 *
	 * @var Analytics|null
	 */
	private $analytics = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Activate plugin.
	 */
	public static function activate() {
		$options = get_option( self::OPTION_KEY );
		if ( ! is_array( $options ) ) {
			update_option( self::OPTION_KEY, self::default_options() );
		}

		// Register cron events.
		if ( ! wp_next_scheduled( Cron::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'mscpe_5min', Cron::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( Module::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'mscpe_15min', Module::CRON_HOOK );
		}

		// Run DB migrations.
		Migrations::run_migrations();
	}

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( Cron::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, Cron::CRON_HOOK );
		}

		$timestamp_adv = wp_next_scheduled( Module::CRON_HOOK );
		if ( $timestamp_adv ) {
			wp_unschedule_event( $timestamp_adv, Module::CRON_HOOK );
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings  = new Settings( $this );
		$this->seo       = new SEO( $this );
		$this->rules     = new Rules( $this );
		$this->analytics = new Analytics( $this );
		$this->module    = new Module( $this );
		$this->cron      = new Cron( $this );

		// Hook into the existing cron's expiry to log analytics and apply SEO.
		add_action( 'mscpe_after_expire_post', array( $this, 'on_post_expired' ), 10, 2 );

		// Schedule module cron if not set.
		if ( ! wp_next_scheduled( Module::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'mscpe_15min', Module::CRON_HOOK );
		}
	}

	/**
	 * Default options.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_options() {
		return array(
			'module_enabled'     => 1,
			'post_types'         => array( 'post', 'page' ),
			'post_type_mode'     => 'include',
			'expiry_action'      => 'trash',
			'expiry_category'    => 0,
			'redirect_enabled'   => 0,
			'bulk_default_days'  => 30,
			'notify_enabled'     => 0,
			'notify_days_before' => 3,
			'notify_recipients'  => 'author',
			'log_enabled'        => 1,
		);
	}

	/**
	 * Option getter.
	 *
	 * @param string $key Key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	public function get_option( $key, $default_value = null ) {
		$options = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::default_options() );
		return array_key_exists( $key, $options ) ? $options[ $key ] : $default_value;
	}

	/**
	 * Save merged options.
	 *
	 * @param array<string,mixed> $new_options New values.
	 * @return bool
	 */
	public function update_options( $new_options ) {
		$current = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::default_options() );
		$merged  = array_merge( $current, $new_options );
		return (bool) update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * Get SEO instance.
	 *
	 * @return SEO|null
	 */
	public function get_seo() {
		return $this->seo;
	}

	/**
	 * Get Rules instance.
	 *
	 * @return Rules|null
	 */
	public function get_rules() {
		return $this->rules;
	}

	/**
	 * Get Analytics instance.
	 *
	 * @return Analytics|null
	 */
	public function get_analytics() {
		return $this->analytics;
	}

	/**
	 * Get Module instance.
	 *
	 * @return Module|null
	 */
	public function get_module() {
		return $this->module;
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Callback when the legacy cron expires a post.
	 * Logs analytics and applies SEO.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $action  Expiry action.
	 */
	public function on_post_expired( $post_id, $action ) {
		if ( $this->analytics ) {
			$this->analytics->log_expiry( $post_id, $action );
		}
		if ( $this->seo ) {
			$this->seo->apply_seo_on_expiry( $post_id );
		}
		if ( $this->module ) {
			$this->module->log_action( $post_id, $action );
		}
	}
}
