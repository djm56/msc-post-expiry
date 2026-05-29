<?php
/**
 * Tests for the Cron class.
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

use MSCPE\Cron;
use MSCPE\Plugin;

/**
 * Test the Cron class.
 */
class Test_Cron extends MSCPE_Test_Case {

	/**
	 * Cron instance.
	 *
	 * @var Cron
	 */
	private $cron;

	/**
	 * Set up.
	 */
	protected function set_up() {
		parent::set_up();
		$this->cron = new Cron( Plugin::instance() );
	}

	/**
	 * Test CRON_HOOK constant.
	 */
	public function test_cron_hook_constant() {
		$this->assertSame( 'mscpe_process_expired_posts', Cron::CRON_HOOK );
	}

	/**
	 * Test CRON_INTERVAL constant.
	 */
	public function test_cron_interval() {
		$this->assertSame( 300, Cron::CRON_INTERVAL );
	}

	/**
	 * Test LOG_RETENTION_DAYS constant.
	 */
	public function test_log_retention() {
		$this->assertSame( 30, Cron::LOG_RETENTION_DAYS );
	}

	/**
	 * Test cron hook is registered.
	 */
	public function test_registers_cron_hook() {
		$this->assertTrue( $this->has_action( Cron::CRON_HOOK ) );
	}

	/**
	 * Test register_cron_event schedules event.
	 */
	public function test_register_cron_event() {
		$this->cron->register_cron_event();

		$calls = $this->get_calls( 'wp_schedule_event' );
		$this->assertNotEmpty( $calls );

		$hooks = array_map( function ( $call ) { return $call[1]; }, $calls );
		$this->assertContains( Cron::CRON_HOOK, $hooks );
	}

	/**
	 * Test register_cron_event skips if already scheduled.
	 */
	public function test_register_cron_event_skip_if_scheduled() {
		$this->set_cron( Cron::CRON_HOOK, 1000 );

		$this->cron->register_cron_event();

		$calls = $this->get_calls( 'wp_schedule_event' );
		$this->assertEmpty( $calls );
	}

	/**
	 * Test unregister_cron_event removes event.
	 */
	public function test_unregister_cron_event() {
		$this->set_cron( Cron::CRON_HOOK, 1000 );

		$this->cron->unregister_cron_event();

		$calls = $this->get_calls( 'wp_unschedule_event' );
		$this->assertNotEmpty( $calls );
	}

	/**
	 * Test unregister_cron_event does nothing if not scheduled.
	 */
	public function test_unregister_cron_event_noop() {
		$this->cron->unregister_cron_event();

		$calls = $this->get_calls( 'wp_unschedule_event' );
		$this->assertEmpty( $calls );
	}
}
