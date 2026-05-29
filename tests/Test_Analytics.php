<?php
/**
 * Tests for the Analytics class.
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

use MSCPE\Analytics;
use MSCPE\Plugin;

/**
 * Test the Analytics class.
 */
class Test_Analytics extends MSCPE_Test_Case {

	/**
	 * Analytics instance.
	 *
	 * @var Analytics
	 */
	private $analytics;

	/**
	 * Set up.
	 */
	protected function set_up() {
		parent::set_up();
		$this->analytics = new Analytics( Plugin::instance() );
	}

	/**
	 * Test admin_enqueue_scripts hook is registered.
	 */
	public function test_registers_enqueue_hook() {
		$this->assertTrue( $this->has_action( 'admin_enqueue_scripts' ) );
	}

	/**
	 * Test enqueue_assets does nothing without screen context.
	 */
	public function test_enqueue_assets_no_screen() {
		$this->analytics->enqueue_assets();
		$calls = $this->get_calls( 'wp_enqueue_script' );
		$this->assertEmpty( $calls );
	}

	/**
	 * Test Analytics class is instantiable.
	 */
	public function test_is_instantiable() {
		$this->assertInstanceOf( Analytics::class, $this->analytics );
	}
}
