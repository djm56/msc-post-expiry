<?php
/**
 * Tests for the Rules class (Smart Expiry Rules).
 *
 * @package MSCPE\Tests
 */

namespace MSCPE\Tests;

require_once __DIR__ . '/class-mscpe-test-case.php';
require_once dirname( __DIR__ ) . '/includes/class-mscpe-rules.php';

use MSCPE\Rules;
use MSCPE\Rule_Evaluator;
use MSCPE\Plugin;

/**
 * Test the Rules class.
 */
class Test_Rules extends MSCPE_Test_Case {

	/**
	 * Rules instance.
	 *
	 * @var Rules
	 */
	private $rules;

	/**
	 * Set up.
	 */
	protected function set_up() {
		parent::set_up();

		// Stub a minimal plugin for Rules constructor.
		require_once dirname( __DIR__ ) . '/includes/class-mscpe-cron.php';
		require_once dirname( __DIR__ ) . '/includes/class-mscpe-migrations.php';
		require_once dirname( __DIR__ ) . '/includes/class-mscpe-seo.php';
		require_once dirname( __DIR__ ) . '/includes/class-mscpe-analytics.php';
		require_once dirname( __DIR__ ) . '/includes/class-mscpe-module.php';
		require_once dirname( __DIR__ ) . '/includes/class-mscpe-settings.php';
		require_once dirname( __DIR__ ) . '/includes/class-mscpe-plugin.php';

		$this->rules = new Rules( Plugin::instance() );
	}

	/**
	 * Test get_rules returns empty array when none exist.
	 */
	public function test_get_rules_empty() {
		$this->assertSame( array(), $this->rules->get_rules() );
	}

	/**
	 * Test save_rule creates a rule.
	 */
	public function test_save_rule_creates() {
		$rule_id = $this->rules->save_rule( array(
			'name'             => 'Test Rule',
			'description'      => 'A test rule',
			'enabled'          => 1,
			'condition_type'   => 'category',
			'condition_config' => array( 'category_ids' => array( 5 ) ),
			'action_type'      => 'draft',
			'action_config'    => array(),
		) );

		$this->assertIsInt( $rule_id );
		$rules = $this->rules->get_rules();
		$this->assertCount( 1, $rules );
	}

	/**
	 * Test get_rule retrieves saved rule.
	 */
	public function test_get_rule_retrieves() {
		$rule_id = $this->rules->save_rule( array(
			'name'             => 'My Rule',
			'description'      => '',
			'enabled'          => 1,
			'condition_type'   => 'author',
			'condition_config' => array( 'author_ids' => array( 1 ) ),
			'action_type'      => 'trash',
			'action_config'    => array(),
		) );

		$rule = $this->rules->get_rule( $rule_id );
		$this->assertNotNull( $rule );
		$this->assertSame( 'My Rule', $rule['name'] );
		$this->assertSame( 'author', $rule['condition_type'] );
	}

	/**
	 * Test get_rule returns null for nonexistent rule.
	 */
	public function test_get_rule_nonexistent() {
		$this->assertNull( $this->rules->get_rule( 999 ) );
	}

	/**
	 * Test delete_rule removes a rule.
	 */
	public function test_delete_rule() {
		$rule_id = $this->rules->save_rule( array(
			'name'             => 'Delete Me',
			'description'      => '',
			'enabled'          => 1,
			'condition_type'   => 'tag',
			'condition_config' => array( 'tag_ids' => array( 1 ) ),
			'action_type'      => 'draft',
			'action_config'    => array(),
		) );

		$result = $this->rules->delete_rule( $rule_id );
		$this->assertTrue( $result );
		$this->assertNull( $this->rules->get_rule( $rule_id ) );
	}

	/**
	 * Test delete_rule returns false for nonexistent rule.
	 */
	public function test_delete_rule_nonexistent() {
		$this->assertFalse( $this->rules->delete_rule( 999 ) );
	}

	/**
	 * Test evaluate_rules returns null when no rules exist.
	 */
	public function test_evaluate_rules_no_rules() {
		$this->assertNull( $this->rules->evaluate_rules( 1 ) );
	}

	/**
	 * Test evaluate_rules returns null when no rules match.
	 */
	public function test_evaluate_rules_no_match() {
		// Save a rule that requires category 99.
		$this->rules->save_rule( array(
			'name'             => 'Category Rule',
			'description'      => '',
			'enabled'          => 1,
			'condition_type'   => 'category',
			'condition_config' => array( 'category_ids' => array( 99 ) ),
			'action_type'      => 'draft',
			'action_config'    => array(),
		) );

		// Post 1 has no categories (get_the_terms returns false).
		$this->assertNull( $this->rules->evaluate_rules( 1 ) );
	}

	/**
	 * Test evaluate_rules matches author condition.
	 */
	public function test_evaluate_rules_matches_author() {
		$this->rules->save_rule( array(
			'name'             => 'Author Rule',
			'description'      => '',
			'enabled'          => 1,
			'condition_type'   => 'author',
			'condition_config' => array( 'author_ids' => array( 1 ) ),
			'action_type'      => 'trash',
			'action_config'    => array(),
		) );

		// get_post stub returns post_author = 1.
		$result = $this->rules->evaluate_rules( 1 );
		$this->assertNotNull( $result );
		$this->assertSame( 'trash', $result['action_type'] );
	}

	/**
	 * Test evaluate_rules skips disabled rules.
	 */
	public function test_evaluate_rules_skips_disabled() {
		$this->rules->save_rule( array(
			'name'             => 'Disabled Rule',
			'description'      => '',
			'enabled'          => 0,
			'condition_type'   => 'author',
			'condition_config' => array( 'author_ids' => array( 1 ) ),
			'action_type'      => 'trash',
			'action_config'    => array(),
		) );

		$this->assertNull( $this->rules->evaluate_rules( 1 ) );
	}

	/**
	 * Test evaluate_rules matches custom_field condition.
	 */
	public function test_evaluate_rules_matches_custom_field() {
		$this->rules->save_rule( array(
			'name'             => 'Custom Field Rule',
			'description'      => '',
			'enabled'          => 1,
			'condition_type'   => 'custom_field',
			'condition_config' => array(
				'field_name'  => 'is_promo',
				'field_value' => '1',
				'compare'     => 'equals',
			),
			'action_type'      => 'delete',
			'action_config'    => array(),
		) );

		// Set the custom field on post 42.
		$this->set_post_meta( 42, 'is_promo', '1' );

		$result = $this->rules->evaluate_rules( 42 );
		$this->assertNotNull( $result );
		$this->assertSame( 'delete', $result['action_type'] );
	}

	/**
	 * Test apply_rule_action for draft action.
	 */
	public function test_apply_rule_action_draft() {
		$result = $this->rules->apply_rule_action( 1, array(
			'action_type'   => 'draft',
			'action_config' => array(),
		) );

		$this->assertTrue( $result );
		$calls = $this->get_calls( 'wp_update_post' );
		$this->assertNotEmpty( $calls );
	}

	/**
	 * Test apply_rule_action for trash action.
	 */
	public function test_apply_rule_action_trash() {
		$result = $this->rules->apply_rule_action( 1, array(
			'action_type'   => 'trash',
			'action_config' => array(),
		) );

		$this->assertTrue( $result );
		$calls = $this->get_calls( 'wp_trash_post' );
		$this->assertNotEmpty( $calls );
	}

	/**
	 * Test apply_rule_action for delete action.
	 */
	public function test_apply_rule_action_delete() {
		$result = $this->rules->apply_rule_action( 1, array(
			'action_type'   => 'delete',
			'action_config' => array(),
		) );

		$this->assertTrue( $result );
		$calls = $this->get_calls( 'wp_delete_post' );
		$this->assertNotEmpty( $calls );
	}

	/**
	 * Test apply_rule_action for redirect action.
	 */
	public function test_apply_rule_action_redirect() {
		$result = $this->rules->apply_rule_action( 1, array(
			'action_type'   => 'redirect',
			'action_config' => array( 'redirect_url' => 'https://example.com' ),
		) );

		$this->assertTrue( $result );
		$meta = get_post_meta( 1, '_mscpe_expiry_redirect_url', true );
		$this->assertSame( 'https://example.com', $meta );
	}

	/**
	 * Test get_action_types returns expected actions.
	 */
	public function test_get_action_types() {
		$types = Rules::get_action_types();
		$this->assertArrayHasKey( 'draft', $types );
		$this->assertArrayHasKey( 'trash', $types );
		$this->assertArrayHasKey( 'delete', $types );
		$this->assertArrayHasKey( 'private', $types );
		$this->assertArrayHasKey( 'category', $types );
		$this->assertArrayHasKey( 'redirect', $types );
	}

	/**
	 * Test Rule_Evaluator condition types.
	 */
	public function test_condition_types() {
		$types = Rule_Evaluator::get_condition_types();
		$this->assertArrayHasKey( 'category', $types );
		$this->assertArrayHasKey( 'tag', $types );
		$this->assertArrayHasKey( 'author', $types );
		$this->assertArrayHasKey( 'age', $types );
		$this->assertArrayHasKey( 'custom_field', $types );
	}

	/**
	 * Test RULES_OPTION constant.
	 */
	public function test_rules_option_constant() {
		$this->assertSame( 'mscpe_rules', Rules::RULES_OPTION );
	}
}
