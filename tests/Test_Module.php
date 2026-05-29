<?php
/**
 * Tests for the Module class.
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

use MSCPE\Module;
use MSCPE\Plugin;

/**
 * Test the Module class.
 */
class Test_Module extends MSCPE_Test_Case {

	/**
	 * Module instance.
	 *
	 * @var Module
	 */
	private $module;

	/**
	 * Set up.
	 */
	protected function set_up() {
		parent::set_up();
		$this->module = new Module( Plugin::instance() );
	}

	/**
	 * Test META_KEY constants.
	 */
	public function test_meta_key_constants() {
		$this->assertSame( '_mscpe_expiry_timestamp', Module::META_KEY_EXPIRY );
		$this->assertSame( '_mscpe_expiry_processed', Module::META_KEY_PROCESSED );
		$this->assertSame( '_mscpe_expiry_action', Module::META_KEY_ACTION );
		$this->assertSame( '_mscpe_expiry_redirect_url', Module::META_KEY_REDIRECT_URL );
		$this->assertSame( '_mscpe_notify_sent', Module::META_KEY_NOTIFY_SENT );
		$this->assertSame( '_mscpe_expiry_category', Module::META_KEY_EXPIRY_CAT );
	}

	/**
	 * Test CRON_HOOK constant.
	 */
	public function test_cron_hook() {
		$this->assertSame( 'mscpe_process_expiry_advanced', Module::CRON_HOOK );
	}

	/**
	 * Test init hook is registered for meta registration.
	 */
	public function test_registers_init_hook() {
		$this->assertTrue( $this->has_action( 'init' ) );
	}

	/**
	 * Test cron hook is registered.
	 */
	public function test_registers_cron_hook() {
		$this->assertTrue( $this->has_action( Module::CRON_HOOK ) );
	}

	/**
	 * Test template_redirect is registered.
	 */
	public function test_registers_redirect_hook() {
		$this->assertTrue( $this->has_action( 'template_redirect' ) );
	}

	/**
	 * Test register_bulk_action adds expiry action.
	 */
	public function test_register_bulk_action() {
		$actions = $this->module->register_bulk_action( array() );
		$this->assertArrayHasKey( 'mscpe_set_expiry_default', $actions );
	}

	/**
	 * Test log_action stores entry in action log.
	 */
	public function test_log_action() {
		$this->set_option( 'mscpe_options', array( 'log_enabled' => 1 ) );

		$this->module->log_action( 42, 'trash' );

		$log = get_option( 'mscpe_action_log', array() );
		$this->assertNotEmpty( $log );
		$this->assertSame( 42, $log[0]['post_id'] );
		$this->assertSame( 'trash', $log[0]['action'] );
	}

	/**
	 * Test log_action skips when logging is disabled.
	 */
	public function test_log_action_disabled() {
		$this->set_option( 'mscpe_options', array( 'log_enabled' => 0 ) );

		$this->module->log_action( 42, 'trash' );

		$log = get_option( 'mscpe_action_log', array() );
		$this->assertEmpty( $log );
	}

	/**
	 * Test module does NOT reference workflows in its class.
	 */
	public function test_no_workflow_reference_in_source() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-mscpe-module.php' );
		$this->assertStringNotContainsString( 'get_workflows', $source );
		$this->assertStringNotContainsString( 'workflow_id', $source );
	}
}
