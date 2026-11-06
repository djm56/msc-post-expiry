<?php
/**
 * Base test case for MSC Post Expiry.
 *
 * @package MSCPE\Tests
 */

namespace MSCPE\Tests;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Base class for all MSCPE test cases.
 *
 * Resets global state before each test.
 */
class MSCPE_Test_Case extends TestCase {

	/**
	 * Reset global state before each test.
	 */
	protected function set_up() {
		parent::set_up();

		$GLOBALS['mscpe_test_calls']           = array();
		$GLOBALS['mscpe_test_options']         = array();
		$GLOBALS['mscpe_test_postmeta']        = array();
		$GLOBALS['mscpe_test_cron']            = array();
		$GLOBALS['mscpe_test_filters']         = array();
		$GLOBALS['mscpe_test_actions']         = array();
		$GLOBALS['mscpe_test_query_posts']     = array();
		$GLOBALS['mscpe_test_last_query_args'] = array();
		$GLOBALS['mscpe_test_timezone']        = 'UTC';
	}

	/**
	 * Helper: set a WordPress option.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Option value.
	 */
	protected function set_option( $key, $value ) {
		$GLOBALS['mscpe_test_options'][ $key ] = $value;
	}

	/**
	 * Helper: set post meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 */
	protected function set_post_meta( $post_id, $key, $value ) {
		if ( ! isset( $GLOBALS['mscpe_test_postmeta'][ $post_id ] ) ) {
			$GLOBALS['mscpe_test_postmeta'][ $post_id ] = array();
		}
		$GLOBALS['mscpe_test_postmeta'][ $post_id ][ $key ] = array( $value );
	}

	/**
	 * Helper: set a scheduled cron event.
	 *
	 * @param string $hook      Cron hook name.
	 * @param int    $timestamp Scheduled timestamp.
	 */
	protected function set_cron( $hook, $timestamp = 1000 ) {
		$GLOBALS['mscpe_test_cron'][ $hook ] = $timestamp;
	}

	/**
	 * Helper: get test calls for a specific function.
	 *
	 * @param string $function_name Function name to filter.
	 * @return array Matching calls.
	 */
	protected function get_calls( $function_name ) {
		return array_filter(
			$GLOBALS['mscpe_test_calls'],
			function ( $call ) use ( $function_name ) {
				return $call[0] === $function_name;
			}
		);
	}

	/**
	 * Helper: check if a specific action was hooked.
	 *
	 * @param string $tag Action tag.
	 * @return bool
	 */
	protected function has_action( $tag ) {
		return ! empty( $GLOBALS['mscpe_test_actions'][ $tag ] );
	}

	/**
	 * Helper: check if a specific filter was hooked.
	 *
	 * @param string $tag Filter tag.
	 * @return bool
	 */
	protected function has_filter( $tag ) {
		return ! empty( $GLOBALS['mscpe_test_filters'][ $tag ] );
	}
}
