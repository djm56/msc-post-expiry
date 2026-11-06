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
	 * Regression test: a freshly-expired post is still processed when 50+
	 * already-processed expired posts exist (BUG 3).
	 *
	 * The old loop bailed after skipping processed posts within the 50-post
	 * limit, so the fresh post never expired. The fix pushes the processed
	 * exclusion into the WP_Query meta_query so the fresh post is returned.
	 */
	public function test_fresh_expired_post_processed_despite_many_processed_posts() {
		$this->set_option( 'mscpe_options', array(
			'module_enabled' => 1,
			'post_types'     => array( 'post' ),
			'notify_enabled' => 0,
			'expiry_action'  => 'trash',
			'log_enabled'    => 0,
		) );

		$now = time();

		// Seed 50 already-processed expired posts.
		for ( $i = 1; $i <= 50; $i++ ) {
			$this->set_post_meta( $i, Module::META_KEY_EXPIRY, $now - 100 );
			$this->set_post_meta( $i, Module::META_KEY_PROCESSED, 1 );
		}

		// One fresh expired post that has not been processed yet.
		$fresh_post_id = 51;
		$this->set_post_meta( $fresh_post_id, Module::META_KEY_EXPIRY, $now - 100 );

		// Simulate the fixed query: it only returns the fresh post because the
		// NOT EXISTS / = '0' clause excludes all already-processed posts.
		$GLOBALS['mscpe_test_query_posts'] = array( $fresh_post_id );

		$this->module->process_expired_posts();

		// The fresh post must have been processed.
		$this->assertSame( 1, (int) get_post_meta( $fresh_post_id, Module::META_KEY_PROCESSED, true ) );

		// Already-processed posts must remain processed.
		for ( $i = 1; $i <= 50; $i++ ) {
			$this->assertSame( 1, (int) get_post_meta( $i, Module::META_KEY_PROCESSED, true ) );
		}

		// Verify the expiry query itself contains the processed exclusion.
		$query_args = $GLOBALS['mscpe_test_last_query_args'];
		$this->assertArrayHasKey( 'meta_query', $query_args );
		$this->assertHasProcessedExclusionClause( $query_args['meta_query'] );
	}

	/**
	 * Asserts a meta_query contains the NOT EXISTS / = '0' processed exclusion.
	 *
	 * @param array $meta_query Meta query array to inspect.
	 */
	private function assertHasProcessedExclusionClause( array $meta_query ) {
		$found = false;
		foreach ( $meta_query as $key => $clause ) {
			if ( ! is_array( $clause ) || 'relation' === $key ) {
				continue;
			}
			if ( isset( $clause['relation'] ) && 'OR' === $clause['relation'] ) {
				$has_not_exists = false;
				$has_zero       = false;
				foreach ( $clause as $sub_key => $sub_clause ) {
					if ( ! is_array( $sub_clause ) || 'relation' === $sub_key ) {
						continue;
					}
					if ( isset( $sub_clause['key'] ) && Module::META_KEY_PROCESSED === $sub_clause['key'] ) {
						if ( isset( $sub_clause['compare'] ) && 'NOT EXISTS' === $sub_clause['compare'] ) {
							$has_not_exists = true;
						}
						if ( isset( $sub_clause['compare'] ) && '=' === $sub_clause['compare'] && '0' === $sub_clause['value'] ) {
							$has_zero = true;
						}
					}
				}
				if ( $has_not_exists && $has_zero ) {
					$found = true;
					break;
				}
			}
		}
		$this->assertTrue( $found, 'Expiry query meta_query is missing the NOT EXISTS / = 0 processed exclusion.' );
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
