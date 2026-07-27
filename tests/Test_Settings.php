<?php
/**
 * Tests for the Settings class.
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
use MSCPE\Settings;

/**
 * Test the Settings class.
 */
class Test_Settings extends MSCPE_Test_Case {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Set up.
	 */
	protected function set_up() {
		parent::set_up();
		$this->settings = new Settings( Plugin::instance() );
	}

	/**
	 * Test that Settings registers admin_menu hook.
	 */
	public function test_registers_admin_menu_hook() {
		$this->assertTrue( $this->has_action( 'admin_menu' ) );
	}

	/**
	 * Test that Settings registers save handler.
	 */
	public function test_registers_save_handler() {
		$this->assertTrue( $this->has_action( 'admin_post_mscpe_save_settings' ) );
	}

	/**
	 * Test that Settings registers metabox hook.
	 */
	public function test_registers_metabox_hook() {
		$this->assertTrue( $this->has_action( 'add_meta_boxes' ) );
	}

	/**
	 * Test register_menu adds options page.
	 */
	public function test_register_menu() {
		$this->settings->register_menu();
		$calls = $this->get_calls( 'add_options_page' );
		$this->assertNotEmpty( $calls, 'add_options_page should be called' );
	}

	/**
	 * Test tabs do NOT contain workflows.
	 */
	public function test_tabs_exclude_workflows() {
		ob_start();
		$this->settings->render_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'tab=workflows', $output );
		$this->assertStringNotContainsString( '>Workflows<', $output );
	}

	/**
	 * Test tabs contain expected slugs.
	 */
	public function test_tabs_contain_expected_slugs() {
		ob_start();
		$this->settings->render_page();
		$output = ob_get_clean();

		$expected_tabs = array( 'settings', 'seo', 'rules', 'analytics', 'history', 'support' );
		foreach ( $expected_tabs as $slug ) {
			$this->assertStringContainsString( "tab={$slug}", $output, "Tab '{$slug}' should be present" );
		}
	}

	/**
	 * Test the rules tab label is "Smart Rules".
	 */
	public function test_rules_tab_label() {
		ob_start();
		$this->settings->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Smart Rules', $output );
	}

	/**
	 * Test render_workflows_tab method does not exist.
	 */
	public function test_no_render_workflows_tab() {
		$this->assertFalse( method_exists( $this->settings, 'render_workflows_tab' ) );
	}

	/**
	 * Test render_support_tab contains Smart Expiry Rules section.
	 */
	public function test_support_tab_has_smart_rules_section() {
		ob_start();
		$this->settings->render_support_tab();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Smart Expiry Rules', $output );
	}

	/**
	 * Test render_support_tab contains SEO section.
	 */
	public function test_support_tab_has_seo_section() {
		ob_start();
		$this->settings->render_support_tab();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'SEO Handling', $output );
	}

	/**
	 * Test render_support_tab contains Analytics section.
	 */
	public function test_support_tab_has_analytics_section() {
		ob_start();
		$this->settings->render_support_tab();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Analytics', $output );
	}

	/**
	 * Test render_support_tab does NOT mention workflows.
	 */
	public function test_support_tab_no_workflow_references() {
		ob_start();
		$this->settings->render_support_tab();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'workflow', strtolower( $output ) );
	}

	/**
	 * Test render_support_tab has support link.
	 */
	public function test_support_tab_has_support_link() {
		ob_start();
		$this->settings->render_support_tab();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'https://wordpress.org/support/plugin/msc-post-expiry/', $output );
	}
}
