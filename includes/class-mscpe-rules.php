<?php
/**
 * Conditional expiry rules and rule evaluation for MSC Post Expiry.
 *
 * @package MSCPE
 */

namespace MSCPE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages conditional expiry rules.
 */
class Rules {

	/**
	 * Option key for rules data.
	 */
	const RULES_OPTION = 'mscpe_rules';

	/**
	 * Main plugin instance.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Gets all rules.
	 *
	 * @return array<int,array>
	 */
	public function get_rules() {
		$rules = get_option( self::RULES_OPTION, array() );
		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Gets a single rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return array|null
	 */
	public function get_rule( $rule_id ) {
		$rules = $this->get_rules();
		return isset( $rules[ $rule_id ] ) ? $rules[ $rule_id ] : null;
	}

	/**
	 * Saves a rule (create or update).
	 *
	 * @param array    $rule_data Rule data.
	 * @param int|null $rule_id   Rule ID (null for create).
	 * @return int Rule ID.
	 */
	public function save_rule( $rule_data, $rule_id = null ) {
		$rules = $this->get_rules();

		$clean = array(
			'name'             => sanitize_text_field( isset( $rule_data['name'] ) ? $rule_data['name'] : '' ),
			'description'      => sanitize_textarea_field( isset( $rule_data['description'] ) ? $rule_data['description'] : '' ),
			'enabled'          => ! empty( $rule_data['enabled'] ) ? 1 : 0,
			'priority'         => isset( $rule_data['priority'] ) ? min( 100, max( 1, absint( $rule_data['priority'] ) ) ) : 10,
			'condition_type'   => sanitize_key( $rule_data['condition_type'] ),
			'condition_config' => $this->sanitize_condition_config( $rule_data['condition_type'], isset( $rule_data['condition_config'] ) ? $rule_data['condition_config'] : array() ),
			'action_type'      => sanitize_key( $rule_data['action_type'] ),
			'action_config'    => $this->sanitize_action_config( $rule_data['action_type'], isset( $rule_data['action_config'] ) ? $rule_data['action_config'] : array() ),
			'updated_at'       => time(),
		);

		if ( null === $rule_id ) {
			$clean['created_at'] = time();
			$rules[]             = $clean;
			end( $rules );
			$rule_id = key( $rules );
		} else {
			if ( isset( $rules[ $rule_id ] ) ) {
				$clean['created_at'] = $rules[ $rule_id ]['created_at'];
			} else {
				$clean['created_at'] = time();
			}
			$rules[ $rule_id ] = $clean;
		}

		update_option( self::RULES_OPTION, $rules );
		return $rule_id;
	}

	/**
	 * Deletes a rule.
	 *
	 * @param int $rule_id Rule ID.
	 * @return bool
	 */
	public function delete_rule( $rule_id ) {
		$rules = $this->get_rules();
		if ( ! isset( $rules[ $rule_id ] ) ) {
			return false;
		}

		unset( $rules[ $rule_id ] );
		update_option( self::RULES_OPTION, $rules );
		return true;
	}

	/**
	 * Sanitizes condition config based on condition type.
	 *
	 * @param string $condition_type Condition type.
	 * @param mixed  $config         Condition config.
	 * @return array
	 */
	private function sanitize_condition_config( $condition_type, $config ) {
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		switch ( $condition_type ) {
			case 'category':
			case 'tag':
				return array(
					'category_ids' => isset( $config['category_ids'] ) ? array_map( 'absint', (array) $config['category_ids'] ) : array(),
					'tag_ids'      => isset( $config['tag_ids'] ) ? array_map( 'absint', (array) $config['tag_ids'] ) : array(),
				);

			case 'author':
				return array(
					'author_ids' => isset( $config['author_ids'] ) ? array_map( 'absint', (array) $config['author_ids'] ) : array(),
				);

			case 'age':
				return array(
					'min_days' => isset( $config['min_days'] ) ? absint( $config['min_days'] ) : 0,
					'max_days' => isset( $config['max_days'] ) ? absint( $config['max_days'] ) : 0,
				);

			case 'comments':
				return array(
					'min_comments' => isset( $config['min_comments'] ) ? absint( $config['min_comments'] ) : 0,
					'max_comments' => isset( $config['max_comments'] ) ? absint( $config['max_comments'] ) : 0,
				);

			case 'views':
				return array(
					'min_views' => isset( $config['min_views'] ) ? absint( $config['min_views'] ) : 0,
					'max_views' => isset( $config['max_views'] ) ? absint( $config['max_views'] ) : 0,
				);

			case 'custom_field':
				return array(
					'field_name'  => isset( $config['field_name'] ) ? sanitize_text_field( $config['field_name'] ) : '',
					'field_value' => isset( $config['field_value'] ) ? sanitize_text_field( $config['field_value'] ) : '',
					'compare'     => isset( $config['compare'] ) ? sanitize_key( $config['compare'] ) : 'equals',
				);

			default:
				return array();
		}
	}

	/**
	 * Sanitizes action config based on action type.
	 *
	 * @param string $action_type Action type.
	 * @param mixed  $config      Action config.
	 * @return array
	 */
	private function sanitize_action_config( $action_type, $config ) {
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		switch ( $action_type ) {
			case 'category':
				return array(
					'category_id' => isset( $config['category_id'] ) ? absint( $config['category_id'] ) : 0,
				);

			case 'redirect':
				return array(
					'redirect_url' => isset( $config['redirect_url'] ) ? esc_url_raw( $config['redirect_url'] ) : '',
				);

			case 'email':
				return array(
					'recipients'   => isset( $config['recipients'] ) ? sanitize_text_field( $config['recipients'] ) : 'author',
					'custom_email' => isset( $config['custom_email'] ) ? sanitize_email( $config['custom_email'] ) : '',
				);

			default:
				return array();
		}
	}

	/**
	 * Evaluates all enabled rules against a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Matching rule or null.
	 */
	public function evaluate_rules( $post_id ) {
		$rules = $this->get_rules();

		// Evaluate in priority order: lower number = higher priority.
		uasort(
			$rules,
			static function ( $a, $b ) {
				$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 10;
				$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 10;
				return $pa <=> $pb;
			}
		);

		foreach ( $rules as $rule_id => $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$condition_type   = isset( $rule['condition_type'] ) ? $rule['condition_type'] : '';
			$condition_config = isset( $rule['condition_config'] ) ? $rule['condition_config'] : array();

			if ( empty( $condition_type ) ) {
				continue;
			}

			if ( Rule_Evaluator::evaluate( $post_id, $condition_type, $condition_config ) ) {
				return array(
					'rule_id'       => $rule_id,
					'action_type'   => isset( $rule['action_type'] ) ? $rule['action_type'] : '',
					'action_config' => isset( $rule['action_config'] ) ? $rule['action_config'] : array(),
				);
			}
		}

		return null;
	}

	/**
	 * Applies a rule's action to a post.
	 *
	 * @param int   $post_id     Post ID.
	 * @param array $rule_result Result from evaluate_rules.
	 * @return bool
	 */
	public function apply_rule_action( $post_id, $rule_result ) {
		$action_type   = $rule_result['action_type'];
		$action_config = $rule_result['action_config'];

		switch ( $action_type ) {
			case 'draft':
				return ! is_wp_error(
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						),
						true
					)
				);

			case 'private':
				return ! is_wp_error(
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'private',
						),
						true
					)
				);

			case 'trash':
				return false !== wp_trash_post( $post_id );

			case 'category':
				$cat_id = isset( $action_config['category_id'] ) ? absint( $action_config['category_id'] ) : 0;
				if ( $cat_id <= 0 || 'post' !== get_post_type( $post_id ) ) {
					return false;
				}
				return false !== wp_set_post_categories( $post_id, array( $cat_id ), false );

			case 'redirect':
				$redirect_url = isset( $action_config['redirect_url'] ) ? $action_config['redirect_url'] : '';
				if ( ! empty( $redirect_url ) ) {
					update_post_meta( $post_id, '_mscpe_expiry_redirect_url', $redirect_url );
				}
				return true;

			case 'delete':
				return false !== wp_delete_post( $post_id, true );

			default:
				return false;
		}
	}

	/**
	 * Gets available action types for rules.
	 *
	 * @return array<string,string>
	 */
	public static function get_action_types() {
		return array(
			'draft'    => __( 'Change to Draft', 'msc-post-expiry' ),
			'private'  => __( 'Change to Private', 'msc-post-expiry' ),
			'trash'    => __( 'Move to Trash', 'msc-post-expiry' ),
			'category' => __( 'Move to Category', 'msc-post-expiry' ),
			'redirect' => __( 'Set Redirect URL', 'msc-post-expiry' ),
			'delete'   => __( 'Permanently Delete', 'msc-post-expiry' ),
		);
	}
}

/**
 * Evaluates rule conditions against posts.
 */
class Rule_Evaluator {

	/**
	 * Available condition types.
	 *
	 * @return array<string,string>
	 */
	public static function get_condition_types() {
		return array(
			'category'     => __( 'Post Category', 'msc-post-expiry' ),
			'tag'          => __( 'Post Tag', 'msc-post-expiry' ),
			'author'       => __( 'Post Author', 'msc-post-expiry' ),
			'age'          => __( 'Post Age (days)', 'msc-post-expiry' ),
			'comments'     => __( 'Comment Count', 'msc-post-expiry' ),
			'views'        => __( 'Post Views', 'msc-post-expiry' ),
			'custom_field' => __( 'Custom Field', 'msc-post-expiry' ),
		);
	}

	/**
	 * Evaluates a rule's condition against a post.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $condition_type   Condition type.
	 * @param array  $condition_config Condition config.
	 * @return bool True if condition matches.
	 */
	public static function evaluate( $post_id, $condition_type, $condition_config ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		switch ( $condition_type ) {
			case 'category':
				return self::evaluate_category( $post, $condition_config );
			case 'tag':
				return self::evaluate_tag( $post, $condition_config );
			case 'author':
				return self::evaluate_author( $post, $condition_config );
			case 'age':
				return self::evaluate_age( $post, $condition_config );
			case 'comments':
				return self::evaluate_comments( $post, $condition_config );
			case 'views':
				return self::evaluate_views( $post, $condition_config );
			case 'custom_field':
				return self::evaluate_custom_field( $post, $condition_config );
			default:
				return false;
		}
	}

	/**
	 * Evaluates category condition.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param mixed    $config Condition config.
	 * @return bool
	 */
	private static function evaluate_category( $post, $config ) {
		if ( ! is_array( $config ) ) {
			return false;
		}

		$category_ids = isset( $config['category_ids'] ) ? (array) $config['category_ids'] : array();
		if ( empty( $category_ids ) ) {
			return false;
		}

		$post_categories = get_the_terms( $post->ID, 'category' );
		if ( empty( $post_categories ) || is_wp_error( $post_categories ) ) {
			return false;
		}

		$post_category_ids = wp_list_pluck( $post_categories, 'term_id' );
		return ! empty( array_intersect( $post_category_ids, $category_ids ) );
	}

	/**
	 * Evaluates tag condition.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param mixed    $config Condition config.
	 * @return bool
	 */
	private static function evaluate_tag( $post, $config ) {
		if ( ! is_array( $config ) ) {
			return false;
		}

		$tag_ids = isset( $config['tag_ids'] ) ? (array) $config['tag_ids'] : array();
		if ( empty( $tag_ids ) ) {
			return false;
		}

		$post_tags = get_the_terms( $post->ID, 'post_tag' );
		if ( empty( $post_tags ) || is_wp_error( $post_tags ) ) {
			return false;
		}

		$post_tag_ids = wp_list_pluck( $post_tags, 'term_id' );
		return ! empty( array_intersect( $post_tag_ids, $tag_ids ) );
	}

	/**
	 * Evaluates author condition.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param mixed    $config Condition config.
	 * @return bool
	 */
	private static function evaluate_author( $post, $config ) {
		if ( ! is_array( $config ) ) {
			return false;
		}

		$author_ids = isset( $config['author_ids'] ) ? (array) $config['author_ids'] : array();
		if ( empty( $author_ids ) ) {
			return false;
		}

		return in_array( (int) $post->post_author, array_map( 'intval', $author_ids ), true );
	}

	/**
	 * Evaluates age condition (post older than X days).
	 *
	 * @param \WP_Post $post   Post object.
	 * @param mixed    $config Condition config.
	 * @return bool
	 */
	private static function evaluate_age( $post, $config ) {
		if ( ! is_array( $config ) ) {
			return false;
		}

		$min_days = isset( $config['min_days'] ) ? absint( $config['min_days'] ) : 0;
		if ( $min_days <= 0 ) {
			return false;
		}

		$post_age = ( time() - get_post_time( 'U', false, $post ) ) / DAY_IN_SECONDS;
		return $post_age >= $min_days;
	}

	/**
	 * Evaluates comments condition.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param mixed    $config Condition config.
	 * @return bool
	 */
	private static function evaluate_comments( $post, $config ) {
		if ( ! is_array( $config ) ) {
			return false;
		}

		$max_comments = isset( $config['max_comments'] ) ? absint( $config['max_comments'] ) : 0;
		$min_comments = isset( $config['min_comments'] ) ? absint( $config['min_comments'] ) : 0;

		$comment_count = get_comments_number( $post->ID );

		if ( $min_comments > 0 && $comment_count < $min_comments ) {
			return false;
		}

		if ( $max_comments > 0 && $comment_count > $max_comments ) {
			return false;
		}

		return true;
	}

	/**
	 * Evaluates views condition (requires external view tracking).
	 *
	 * @param \WP_Post $post   Post object.
	 * @param mixed    $config Condition config.
	 * @return bool
	 */
	private static function evaluate_views( $post, $config ) {
		if ( ! is_array( $config ) ) {
			return false;
		}

		$views = self::get_post_views( $post->ID );

		if ( false === $views ) {
			return false;
		}

		$max_views = isset( $config['max_views'] ) ? absint( $config['max_views'] ) : 0;
		$min_views = isset( $config['min_views'] ) ? absint( $config['min_views'] ) : 0;

		if ( $min_views > 0 && $views < $min_views ) {
			return false;
		}

		if ( $max_views > 0 && $views > $max_views ) {
			return false;
		}

		return true;
	}

	/**
	 * Evaluates custom field condition.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param mixed    $config Condition config.
	 * @return bool
	 */
	private static function evaluate_custom_field( $post, $config ) {
		if ( ! is_array( $config ) ) {
			return false;
		}

		$field_name = isset( $config['field_name'] ) ? sanitize_text_field( $config['field_name'] ) : '';
		if ( empty( $field_name ) ) {
			return false;
		}

		$field_value    = get_post_meta( $post->ID, $field_name, true );
		$expected_value = isset( $config['field_value'] ) ? $config['field_value'] : '';
		$compare        = isset( $config['compare'] ) ? $config['compare'] : 'equals';

		switch ( $compare ) {
			case 'equals':
				return $field_value == $expected_value; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			case 'not_equals':
				return $field_value != $expected_value; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
			case 'contains':
				return is_string( $field_value ) && false !== strpos( $field_value, $expected_value );
			case 'greater_than':
				return is_numeric( $field_value ) && (float) $field_value > (float) $expected_value;
			case 'less_than':
				return is_numeric( $field_value ) && (float) $field_value < (float) $expected_value;
			case 'exists':
				return '' !== $field_value && null !== $field_value;
			case 'not_exists':
				return '' === $field_value || null === $field_value;
			default:
				return false;
		}
	}

	/**
	 * Gets post view count from common sources.
	 *
	 * @param int $post_id Post ID.
	 * @return int|false
	 */
	private static function get_post_views( $post_id ) {
		// Try WordPress.com Stats (Jetpack).
		if ( function_exists( 'stats_get_csv' ) ) {
			$stats = stats_get_csv( 'postviews', array( 'post_id' => $post_id, 'days' => 1 ) );
			if ( ! empty( $stats ) && isset( $stats[0]['views'] ) ) {
				return (int) $stats[0]['views'];
			}
		}

		// Try WP PostViews plugin.
		$postviews = get_post_meta( $post_id, 'views', true );
		if ( is_numeric( $postviews ) ) {
			return (int) $postviews;
		}

		// Try popular post plugin.
		$wpp_views = get_post_meta( $post_id, 'wpp_total_views', true );
		if ( is_numeric( $wpp_views ) ) {
			return (int) $wpp_views;
		}

		return false;
	}
}
