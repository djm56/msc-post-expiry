<?php
/**
 * Tests for the Migrations class.
 *
 * @package MSCPE\Tests
 */

namespace MSCPE\Tests;

require_once __DIR__ . '/class-mscpe-test-case.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-migrations.php';

use MSCPE\Migrations;

/**
 * Test the Migrations class.
 */
class Test_Migrations extends MSCPE_Test_Case {

	/**
	 * Test MIGRATION_VERSION constant.
	 */
	public function test_migration_version() {
		$this->assertSame( '1.6.0', Migrations::MIGRATION_VERSION );
	}

	/**
	 * Test VERSION_OPTION constant.
	 */
	public function test_version_option() {
		$this->assertSame( 'mscpe_db_version', Migrations::VERSION_OPTION );
	}

	/**
	 * Test Migrations class does not have create_workflows_table method.
	 */
	public function test_no_workflows_table_method() {
		$reflection = new \ReflectionClass( Migrations::class );
		$methods    = array_map(
			function ( $method ) { return $method->getName(); },
			$reflection->getMethods()
		);

		$this->assertNotContains( 'create_workflows_table', $methods );
		$this->assertNotContains( 'create_workflow_steps_table', $methods );
	}

	/**
	 * Test Migrations class has expected table creation methods.
	 */
	public function test_has_expected_methods() {
		$reflection = new \ReflectionClass( Migrations::class );
		$methods    = array_map(
			function ( $method ) { return $method->getName(); },
			$reflection->getMethods()
		);

		$this->assertContains( 'create_analytics_table', $methods );
		$this->assertContains( 'drop_unused_rules_table', $methods );

		// The unused rules table is no longer created (removed in 1.6.0).
		$this->assertNotContains( 'create_rules_table', $methods );
	}

	/**
	 * Test run_migrations is idempotent (skips if version matches).
	 */
	public function test_run_migrations_idempotent() {
		$this->set_option( 'mscpe_db_version', '1.6.0' );

		// Should not error and should return early.
		Migrations::run_migrations();

		// Version should be unchanged.
		$this->assertSame( '1.6.0', get_option( 'mscpe_db_version' ) );
	}
}
