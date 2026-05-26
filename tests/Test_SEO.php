<?php
/**
 * Tests for the SEO class.
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
require_once dirname( __DIR__ ) . '/includes/class-msc-post-expiry-module.php';
require_once dirname( __DIR__ ) . '/includes/class-msc-post-expiry-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-msc-post-expiry.php';

use MSCPE\SEO;
use MSCPE\Plugin;

/**
 * Test the SEO class.
 */
class Test_SEO extends MSCPE_Test_Case {

	/**
	 * SEO instance.
	 *
	 * @var SEO
	 */
	private $seo;

	/**
	 * Set up.
	 */
	protected function set_up() {
		parent::set_up();
		$this->seo = new SEO( Plugin::instance() );
	}

	/**
	 * Test wp_head hook is registered.
	 */
	public function test_hooks_wp_head() {
		$this->assertTrue( $this->has_action( 'wp_head' ) );
	}

	/**
	 * Test meta key constants.
	 */
	public function test_meta_key_constants() {
		$this->assertSame( '_mscpe_seo_noindex', SEO::META_KEY_NOINDEX );
		$this->assertSame( '_mscpe_seo_nofollow', SEO::META_KEY_NOFOLLOW );
		$this->assertSame( '_mscpe_seo_canonical', SEO::META_KEY_CANONICAL );
		$this->assertSame( '_mscpe_seo_status_code', SEO::META_KEY_STATUS_CODE );
	}

	/**
	 * Test OPTION_KEY constant.
	 */
	public function test_option_key() {
		$this->assertSame( 'mscpe_seo_options', SEO::OPTION_KEY );
	}

	/**
	 * Test get_default_options returns expected keys.
	 */
	public function test_default_options() {
		$defaults = SEO::get_default_options();
		$this->assertArrayHasKey( 'noindex_enabled', $defaults );
		$this->assertArrayHasKey( 'nofollow_enabled', $defaults );
		$this->assertArrayHasKey( 'canonical_strategy', $defaults );
		$this->assertArrayHasKey( 'status_code', $defaults );
	}

	/**
	 * Test default option values.
	 */
	public function test_default_option_values() {
		$defaults = SEO::get_default_options();
		$this->assertSame( 1, $defaults['noindex_enabled'] );
		$this->assertSame( 0, $defaults['nofollow_enabled'] );
		$this->assertSame( 'none', $defaults['canonical_strategy'] );
		$this->assertSame( '200', $defaults['status_code'] );
	}

	/**
	 * Test get_options returns merged defaults.
	 */
	public function test_get_options_merges_defaults() {
		$options = $this->seo->get_options();
		$this->assertArrayHasKey( 'noindex_enabled', $options );
		$this->assertArrayHasKey( 'nofollow_enabled', $options );
	}

	/**
	 * Test get_options returns stored values.
	 */
	public function test_get_options_stored() {
		$this->set_option( 'mscpe_seo_options', array( 'noindex_enabled' => 0 ) );
		$options = $this->seo->get_options();
		$this->assertSame( 0, $options['noindex_enabled'] );
	}

	/**
	 * Test save_options stores values.
	 */
	public function test_save_options() {
		$this->seo->save_options( array(
			'noindex_enabled'    => 0,
			'nofollow_enabled'   => 1,
			'canonical_strategy' => 'homepage',
		) );

		$saved = get_option( 'mscpe_seo_options' );
		$this->assertSame( 0, $saved['noindex_enabled'] );
		$this->assertSame( 1, $saved['nofollow_enabled'] );
		$this->assertSame( 'homepage', $saved['canonical_strategy'] );
	}

	/**
	 * Test save_options rejects invalid canonical strategy.
	 */
	public function test_save_options_rejects_invalid_strategy() {
		$this->seo->save_options( array(
			'canonical_strategy' => 'invalid_value',
		) );

		$saved = get_option( 'mscpe_seo_options' );
		$this->assertSame( 'none', $saved['canonical_strategy'] );
	}

	/**
	 * Test is_post_expired checks meta key.
	 */
	public function test_is_post_expired() {
		$this->assertFalse( $this->seo->is_post_expired( 1 ) );

		$this->set_post_meta( 1, '_mscpe_expiry_processed', 1 );
		$this->assertTrue( $this->seo->is_post_expired( 1 ) );
	}
}
