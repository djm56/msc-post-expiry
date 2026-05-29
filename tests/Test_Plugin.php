<?php
/**
 * Tests for the main Plugin class.
 *
 * @package MSCPE\Tests
 */

namespace MSCPE\Tests;

require_once __DIR__ . '/class-mscpe-test-case.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-cron.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-migrations.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-seo.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-rules.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-analytics.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-module.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-plugin.php';

use MSCPE\Plugin;

/**
 * Test the main Plugin class.
 */
class Test_Plugin extends MSCPE_Test_Case {

	/**
	 * Test singleton instance returns same object.
	 */
	public function test_instance_returns_singleton() {
		$a = Plugin::instance();
		$b = Plugin::instance();
		$this->assertSame( $a, $b );
	}

	/**
	 * Test instance returns Plugin type.
	 */
	public function test_instance_returns_plugin() {
		$this->assertInstanceOf( Plugin::class, Plugin::instance() );
	}

	/**
	 * Test default_options returns expected keys.
	 */
	public function test_default_options_keys() {
		$defaults = Plugin::default_options();
		$expected = array(
			'module_enabled',
			'post_types',
			'post_type_mode',
			'expiry_action',
			'expiry_category',
			'redirect_enabled',
			'bulk_default_days',
			'notify_enabled',
			'notify_days_before',
			'notify_recipients',
			'log_enabled',
		);

		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, $defaults, "Missing default option key: {$key}" );
		}
	}

	/**
	 * Test default_options values.
	 */
	public function test_default_options_values() {
		$defaults = Plugin::default_options();
		$this->assertSame( 1, $defaults['module_enabled'] );
		$this->assertSame( array( 'post', 'page' ), $defaults['post_types'] );
		$this->assertSame( 'include', $defaults['post_type_mode'] );
		$this->assertSame( 'trash', $defaults['expiry_action'] );
		$this->assertSame( 30, $defaults['bulk_default_days'] );
	}

	/**
	 * Test get_option returns default when no options are set.
	 */
	public function test_get_option_returns_default() {
		$plugin = Plugin::instance();
		$this->assertSame( 'trash', $plugin->get_option( 'expiry_action', 'trash' ) );
	}

	/**
	 * Test get_option returns stored value.
	 */
	public function test_get_option_returns_stored_value() {
		$this->set_option( 'mscpe_options', array( 'expiry_action' => 'draft' ) );
		$plugin = Plugin::instance();
		$this->assertSame( 'draft', $plugin->get_option( 'expiry_action', 'trash' ) );
	}

	/**
	 * Test update_options merges with existing options.
	 */
	public function test_update_options_merges() {
		$this->set_option( 'mscpe_options', array( 'expiry_action' => 'trash', 'module_enabled' => 1 ) );
		$plugin = Plugin::instance();
		$plugin->update_options( array( 'expiry_action' => 'draft' ) );

		$saved = get_option( 'mscpe_options' );
		$this->assertSame( 'draft', $saved['expiry_action'] );
		$this->assertSame( 1, $saved['module_enabled'] );
	}

	/**
	 * Test get_seo returns SEO instance.
	 */
	public function test_get_seo_returns_instance() {
		$plugin = Plugin::instance();
		$this->assertInstanceOf( \MSCPE\SEO::class, $plugin->get_seo() );
	}

	/**
	 * Test get_rules returns Rules instance.
	 */
	public function test_get_rules_returns_instance() {
		$plugin = Plugin::instance();
		$this->assertInstanceOf( \MSCPE\Rules::class, $plugin->get_rules() );
	}

	/**
	 * Test get_analytics returns Analytics instance.
	 */
	public function test_get_analytics_returns_instance() {
		$plugin = Plugin::instance();
		$this->assertInstanceOf( \MSCPE\Analytics::class, $plugin->get_analytics() );
	}

	/**
	 * Test that workflows getter does not exist (removed feature).
	 */
	public function test_no_workflows_getter() {
		$this->assertFalse( method_exists( Plugin::class, 'get_workflows' ) );
	}

	/**
	 * Test that no Workflows class exists.
	 */
	public function test_no_workflows_class() {
		$this->assertFalse( class_exists( 'MSCPE\\Workflows' ) );
	}

	/**
	 * Test OPTION_KEY constant.
	 */
	public function test_option_key_constant() {
		$this->assertSame( 'mscpe_options', Plugin::OPTION_KEY );
	}

	/**
	 * Test activate schedules cron events.
	 */
	public function test_activate_schedules_cron() {
		// Set DB version to skip migrations (they require WP upgrade.php).
		$this->set_option( 'mscpe_db_version', \MSCPE\Migrations::MIGRATION_VERSION );

		Plugin::activate();

		$calls = $this->get_calls( 'wp_schedule_event' );
		$hooks = array_map( function ( $call ) { return $call[1]; }, $calls );

		$this->assertContains( \MSCPE\Cron::CRON_HOOK, $hooks );
		$this->assertContains( \MSCPE\Module::CRON_HOOK, $hooks );
	}

	/**
	 * Test deactivate unschedules cron events.
	 */
	public function test_deactivate_unschedules_cron() {
		$this->set_cron( \MSCPE\Cron::CRON_HOOK, 1000 );
		$this->set_cron( \MSCPE\Module::CRON_HOOK, 2000 );

		Plugin::deactivate();

		$calls = $this->get_calls( 'wp_unschedule_event' );
		$hooks = array_map( function ( $call ) { return $call[1]; }, $calls );

		$this->assertContains( \MSCPE\Cron::CRON_HOOK, $hooks );
		$this->assertContains( \MSCPE\Module::CRON_HOOK, $hooks );
	}

	/**
	 * Test deactivate does NOT unschedule workflow cron (removed).
	 */
	public function test_deactivate_no_workflow_cron() {
		$this->set_cron( 'mscpe_process_workflow_steps', 3000 );

		Plugin::deactivate();

		$calls = $this->get_calls( 'wp_unschedule_event' );
		$hooks = array_map( function ( $call ) { return $call[1]; }, $calls );

		$this->assertNotContains( 'mscpe_process_workflow_steps', $hooks );
	}
}
