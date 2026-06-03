<?php
/**
 * PHPUnit bootstrap for MSC Post Expiry tests.
 *
 * Provides WordPress function stubs so tests can run without a full
 * WordPress installation.  Each stub stores its calls in a global
 * array so assertions can inspect them.
 *
 * @package MSCPE\Tests
 */

// Prevent double-load.
if ( defined( 'MSCPE_TESTS_LOADED' ) ) {
	return;
}
define( 'MSCPE_TESTS_LOADED', true );

// WordPress constants the plugin expects.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

// Global call tracker used by stubs.
$GLOBALS['mscpe_test_calls'] = array();
// Global options store.
$GLOBALS['mscpe_test_options'] = array();
// Global post meta store.
$GLOBALS['mscpe_test_postmeta'] = array();
// Global cron store.
$GLOBALS['mscpe_test_cron'] = array();
// Global filters store.
$GLOBALS['mscpe_test_filters'] = array();
// Global actions store.
$GLOBALS['mscpe_test_actions'] = array();

// Mock $wpdb for migrations.
$GLOBALS['wpdb'] = new class {
	public $prefix = 'wp_';
	public $postmeta = 'wp_postmeta';
	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
	public function query( $sql ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wpdb_query', $sql );
		return true;
	}
	public function prepare( $query, ...$args ) {
		return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
	}
};

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'dbDelta', $sql );
		return array();
	}
}

/*
 * ---------------------------------------------------------------
 *  WordPress function stubs
 * ---------------------------------------------------------------
 */

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['mscpe_test_options'] )
			? $GLOBALS['mscpe_test_options'][ $key ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['mscpe_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['mscpe_test_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( '' === $key ) {
			return $GLOBALS['mscpe_test_postmeta'][ $post_id ] ?? array();
		}
		$value = $GLOBALS['mscpe_test_postmeta'][ $post_id ][ $key ] ?? array();
		if ( $single ) {
			return is_array( $value ) && ! empty( $value ) ? $value[0] : '';
		}
		return $value;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		if ( ! isset( $GLOBALS['mscpe_test_postmeta'][ $post_id ] ) ) {
			$GLOBALS['mscpe_test_postmeta'][ $post_id ] = array();
		}
		$GLOBALS['mscpe_test_postmeta'][ $post_id ][ $key ] = array( $value );
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['mscpe_test_postmeta'][ $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( is_string( $args ) ) {
			parse_str( $args, $args );
		}
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) {
		return $GLOBALS['mscpe_test_cron'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook ) {
		$GLOBALS['mscpe_test_cron'][ $hook ] = $timestamp;
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_schedule_event', $hook, $recurrence );
		return true;
	}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	function wp_unschedule_event( $timestamp, $hook ) {
		unset( $GLOBALS['mscpe_test_cron'][ $hook ] );
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_unschedule_event', $hook );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mscpe_test_actions'][ $tag ][] = array(
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mscpe_test_filters'][ $tag ][] = array(
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		if ( ! empty( $GLOBALS['mscpe_test_filters'][ $tag ] ) ) {
			foreach ( $GLOBALS['mscpe_test_filters'][ $tag ] as $filter ) {
				$value = call_user_func_array( $filter['callback'], array_merge( array( $value ), $args ) );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		$GLOBALS['mscpe_test_calls'][] = array_merge( array( 'do_action', $tag ), $args );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.com/wp-content/plugins/msc-post-expiry/';
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( $list, $field ) {
		return array_map(
			function ( $item ) use ( $field ) {
				return is_object( $item ) ? $item->$field : $item[ $field ];
			},
			$list
		);
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'register_activation_hook', $file );
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'register_deactivation_hook', $file );
	}
}

if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'add_options_page', $menu_slug );
		return 'hook_suffix';
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'add_meta_box', $id );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) {
		return '<input type="hidden" name="' . $name . '" value="nonce_value" />';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		return 1;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return true;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		$result = ( (string) $checked === (string) $current ) ? " checked='checked'" : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) {
		$result = ( (string) $selected === (string) $current ) ? " selected='selected'" : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = 'Save Changes' ) {
		echo '<input type="submit" value="' . esc_attr( $text ) . '" />';
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		if ( is_string( $args ) ) {
			return $url . '?' . $args;
		}
		$separator = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
		return $url . $separator . http_build_query( $args );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_safe_redirect', $location );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '' ) {
		throw new \RuntimeException( $message );
	}
}

if ( ! function_exists( 'wp_reset_postdata' ) ) {
	function wp_reset_postdata() {
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( $args = array(), $output = 'names' ) {
		$types = array( 'post', 'page' );
		if ( 'objects' === $output ) {
			$objects = array();
			foreach ( $types as $type ) {
				$obj        = new \stdClass();
				$obj->name  = $type;
				$obj->label = ucfirst( $type );
				$labels = new \stdClass();
				$labels->singular_name = ucfirst( $type );
				$obj->labels = $labels;
				$objects[ $type ] = $obj;
			}
			return $objects;
		}
		return $types;
	}
}

if ( ! function_exists( 'get_categories' ) ) {
	function get_categories( $args = array() ) {
		return array();
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action ) {
		return $url . '&_wpnonce=nonce_value';
	}
}

if ( ! function_exists( 'wp_trash_post' ) ) {
	function wp_trash_post( $post_id ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_trash_post', $post_id );
		return true;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $post_id, $force_delete = false ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_delete_post', $post_id, $force_delete );
		return true;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $args ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_update_post', $args );
		return $args['ID'] ?? 0;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		$post              = new \stdClass();
		$post->ID          = $post_id;
		$post->post_type   = 'post';
		$post->post_status = 'publish';
		$post->post_author = 1;
		$post->post_date   = '2026-01-01 00:00:00';
		$post->post_title  = 'Test Post ' . $post_id;
		return $post;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		$user              = new \stdClass();
		$user->ID          = $user_id;
		$user->user_login  = 'admin';
		$user->user_email  = 'admin@example.com';
		return $user;
	}
}

if ( ! function_exists( 'has_category' ) ) {
	function has_category( $category, $post = null ) {
		return false;
	}
}

if ( ! function_exists( 'has_tag' ) ) {
	function has_tag( $tag, $post = null ) {
		return false;
	}
}

if ( ! function_exists( 'wp_set_post_categories' ) ) {
	function wp_set_post_categories( $post_id, $categories ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_set_post_categories', $post_id, $categories );
		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}

/*
 * ---------------------------------------------------------------
 *  Plugin constants
 * ---------------------------------------------------------------
 */
define( 'MSCPE_PLUGIN_VERSION', '1.5.1' );
define( 'MSCPE_PLUGIN_FILE', dirname( __DIR__ ) . '/msc-post-expiry.php' );
define( 'MSCPE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MSCPE_PLUGIN_URL', 'https://example.com/wp-content/plugins/msc-post-expiry/' );

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'register_post_meta' ) ) {
	function register_post_meta( $post_type, $meta_key, $args ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'register_post_meta', $post_type, $meta_key );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_enqueue_script', $handle );
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $l10n ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_localize_script', $handle, $object_name );
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		return null;
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path, $plugin ) {
		return 'https://example.com/wp-content/plugins/msc-post-expiry/' . $path;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $post_id, $taxonomy ) {
		$key = "_test_terms_{$taxonomy}";
		return $GLOBALS['mscpe_test_postmeta'][ $post_id ][ $key ][0] ?? false;
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post_id = null ) {
		return 'post';
	}
}

if ( ! function_exists( 'get_post_time' ) ) {
	function get_post_time( $d, $gmt, $post ) {
		return strtotime( $post->post_date );
	}
}

if ( ! function_exists( 'get_comments_number' ) ) {
	function get_comments_number( $post_id ) {
		return $GLOBALS['mscpe_test_postmeta'][ $post_id ]['_comment_count'][0] ?? 0;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular() {
		return false;
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return 0;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		if ( 'timestamp' === $type ) {
			return time();
		}
		return gmdate( $type );
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return ( 1 === $number ) ? $single : $plural;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null ) {
		return date( $format, $timestamp ?? time() );
	}
}

if ( ! function_exists( 'get_the_author_meta' ) ) {
	function get_the_author_meta( $field, $user_id ) {
		if ( 'email' === $field ) {
			return 'author@example.com';
		}
		return '';
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	function get_edit_post_link( $post_id, $context = 'display' ) {
		return 'https://example.com/wp-admin/post.php?post=' . $post_id . '&action=edit';
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message ) {
		$GLOBALS['mscpe_test_calls'][] = array( 'wp_mail', $to, $subject );
		return true;
	}
}

/*
 * ---------------------------------------------------------------
 *  Autoload Composer dependencies (PHPUnit, polyfills)
 * ---------------------------------------------------------------
 */
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
