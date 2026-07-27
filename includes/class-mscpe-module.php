<?php
/**
 * Module class for MSC Post Expiry.
 *
 * Handles per-post expiry actions, meta box, redirect, bulk scheduling,
 * email notifications, and action history.
 *
 * @package MSCPE
 */

namespace MSCPE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend and admin module class.
 */
class Module {

	const META_KEY_EXPIRY       = '_mscpe_expiry_timestamp';
	const META_KEY_PROCESSED    = '_mscpe_expiry_processed';
	const META_KEY_ACTION       = '_mscpe_expiry_action';
	const META_KEY_REDIRECT_URL = '_mscpe_expiry_redirect_url';
	const META_KEY_NOTIFY_SENT  = '_mscpe_notify_sent';
	const META_KEY_EXPIRY_CAT   = '_mscpe_expiry_category';
	const CRON_HOOK             = 'mscpe_process_expiry_advanced';

	/**
	 * Main plugin instance.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( self::CRON_HOOK, array( $this, 'process_expired_posts' ) );
		add_action( 'template_redirect', array( $this, 'handle_redirect' ) );
		add_action( 'added_post_meta', array( $this, 'maybe_reset_processed_flag' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'maybe_reset_processed_flag' ), 10, 4 );

		$post_types = $this->get_post_types();
		foreach ( $post_types as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, array( $this, 'register_bulk_action' ) );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'handle_bulk_action' ), 10, 3 );
		}
	}

	/**
	 * Get post types from plugin settings.
	 *
	 * @return array<string>
	 */
	private function get_post_types() {
		return (array) $this->plugin->get_option( 'post_types', array( 'post', 'page' ) );
	}

	/**
	 * Get post type mode from plugin settings.
	 *
	 * @return string
	 */
	private function get_post_type_mode() {
		return (string) $this->plugin->get_option( 'post_type_mode', 'include' );
	}

	/**
	 * Whether module is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$module_enabled = $this->plugin->get_option( 'module_enabled', 1 );
		return ! empty( $module_enabled );
	}

	/**
	 * Registers post meta for expiry fields via the REST API.
	 *
	 * @return void
	 */
	public function register_meta() {
		$post_types = $this->get_post_types();

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY_EXPIRY,
				array(
					'type'          => 'integer',
					'single'        => true,
					'show_in_rest'  => true,
					'default'       => 0,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::META_KEY_PROCESSED,
				array(
					'type'          => 'boolean',
					'single'        => true,
					'show_in_rest'  => false,
					'default'       => false,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::META_KEY_NOTIFY_SENT,
				array(
					'type'          => 'integer',
					'single'        => true,
					'show_in_rest'  => false,
					'default'       => 0,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			// Per-post override meta (editable in both editors since 1.6.0).
			register_post_meta(
				$post_type,
				self::META_KEY_ACTION,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'default'       => '',
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::META_KEY_REDIRECT_URL,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'default'           => '',
					'sanitize_callback' => 'esc_url_raw',
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::META_KEY_EXPIRY_CAT,
				array(
					'type'          => 'integer',
					'single'        => true,
					'show_in_rest'  => true,
					'default'       => 0,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Enqueues the block-editor expiry sidebar script.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->get_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'mscpe-expiry-sidebar',
			MSCPE_PLUGIN_URL . 'assets/js/expiry-sidebar.js',
			array( 'wp-components', 'wp-compose', 'wp-data', 'wp-edit-post', 'wp-element', 'wp-plugins' ),
			MSCPE_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'mscpe-expiry-sidebar',
			'mscpeExpiryConfig',
			array(
				'metaKey'         => self::META_KEY_EXPIRY,
				'actionMetaKey'   => self::META_KEY_ACTION,
				'redirectMetaKey' => self::META_KEY_REDIRECT_URL,
				'categoryMetaKey' => self::META_KEY_EXPIRY_CAT,
				'title'           => __( 'Post Expiry', 'msc-post-expiry' ),
				'help'            => __( 'Set an expiry date/time. Optionally override the action just for this post.', 'msc-post-expiry' ),
				'label'           => __( 'Expiry date/time (local)', 'msc-post-expiry' ),
				'actionLabel'     => __( 'Action for this post', 'msc-post-expiry' ),
				'redirectLabel'   => __( 'Redirect URL', 'msc-post-expiry' ),
				'categoryLabel'   => __( 'Move to category', 'msc-post-expiry' ),
				'categoryDefault' => __( '— Select —', 'msc-post-expiry' ),
				'actions'         => self::get_action_choices(),
			)
		);
	}

	/**
	 * Per-post expiry action choices (shared by the metabox and block sidebar).
	 *
	 * The empty value means "use the global default action".
	 *
	 * @return array<string,string>
	 */
	public static function get_action_choices() {
		return array(
			''              => __( 'Use global default', 'msc-post-expiry' ),
			'trash'         => __( 'Move to Trash', 'msc-post-expiry' ),
			'delete'        => __( 'Permanently Delete', 'msc-post-expiry' ),
			'draft'         => __( 'Change to Draft', 'msc-post-expiry' ),
			'private'       => __( 'Change to Private', 'msc-post-expiry' ),
			'category'      => __( 'Move to Category', 'msc-post-expiry' ),
			'redirect_only' => __( 'Redirect Only', 'msc-post-expiry' ),
		);
	}

	/**
	 * Adds the bulk expiry action to the post list bulk-actions dropdown.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_action( $actions ) {
		$actions['mscpe_set_expiry_default'] = __( 'Set expiry using default window', 'msc-post-expiry' );
		return $actions;
	}

	/**
	 * Handles the bulk expiry action.
	 *
	 * @param string $redirect_url Redirect URL after bulk action.
	 * @param string $action       Bulk action slug.
	 * @param int[]  $post_ids     Array of post IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_url, $action, $post_ids ) {
		if ( 'mscpe_set_expiry_default' !== $action ) {
			return $redirect_url;
		}

		$days = max( 1, absint( $this->plugin->get_option( 'bulk_default_days', 30 ) ) );
		$ts   = time() + ( $days * DAY_IN_SECONDS );

		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, self::META_KEY_EXPIRY, $ts );
			update_post_meta( $post_id, self::META_KEY_PROCESSED, 0 );
		}

		return add_query_arg( 'mscpe_bulk_expiry', count( $post_ids ), $redirect_url );
	}

	/**
	 * Resets the processed flag when a post is given a new future expiry date.
	 *
	 * Runs on added_post_meta/updated_post_meta so rescheduling via the block
	 * editor sidebar (which only writes the timestamp meta) re-arms processing.
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public function maybe_reset_processed_flag( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( self::META_KEY_EXPIRY !== $meta_key ) {
			return;
		}

		if ( (int) $meta_value > time() && (bool) get_post_meta( $post_id, self::META_KEY_PROCESSED, true ) ) {
			update_post_meta( $post_id, self::META_KEY_PROCESSED, 0 );
		}
	}

	/**
	 * Cron callback: queries and processes all expired posts.
	 *
	 * @return void
	 */
	public function process_expired_posts() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		/**
		 * Fires before processing expired posts.
		 */
		do_action( 'mscpe_before_process_expired_posts' );

		// Send expiry notifications first.
		$this->send_expiry_notifications();

		$post_types = $this->get_post_types();
		if ( empty( $post_types ) ) {
			do_action( 'mscpe_after_process_expired_posts', 0 );
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => array( 'publish', 'future', 'private' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_KEY_EXPIRY,
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::META_KEY_EXPIRY,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			do_action( 'mscpe_after_process_expired_posts', 0 );
			return;
		}

		$seo             = $this->plugin->get_seo();
		$rules           = $this->plugin->get_rules();
		$analytics       = $this->plugin->get_analytics();
		$processed_count = 0;

		foreach ( $query->posts as $post_id ) {
			if ( (bool) get_post_meta( $post_id, self::META_KEY_PROCESSED, true ) ) {
				continue;
			}

			// Check for conditional rules.
			if ( $rules ) {
				$rule_result = $rules->evaluate_rules( $post_id );
				if ( null !== $rule_result ) {
					/** This action is documented below. */
					do_action( 'mscpe_before_expire_post', $post_id, $rule_result['action_type'] );

					$result = $rules->apply_rule_action( $post_id, $rule_result );
					if ( $result ) {
						update_post_meta( $post_id, self::META_KEY_PROCESSED, 1 );
						$this->log_action( $post_id, $rule_result['action_type'] );
						if ( $analytics ) {
							$analytics->log_expiry( $post_id, $rule_result['action_type'] );
						}
						if ( $seo ) {
							$seo->apply_seo_on_expiry( $post_id );
						}
						++$processed_count;

						/** This action is documented below. */
						do_action( 'mscpe_after_expire_post', $post_id, $rule_result['action_type'], $result );
					}
					continue;
				}
			}

			// Use standard action.
			$action = (string) get_post_meta( $post_id, self::META_KEY_ACTION, true );
			if ( '' === $action ) {
				$action = (string) $this->plugin->get_option( 'expiry_action', 'trash' );
			}

			/**
			 * Fires before expiring a post.
			 *
			 * @param int    $post_id Post ID.
			 * @param string $action  Expiry action.
			 */
			do_action( 'mscpe_before_expire_post', $post_id, $action );

			$result = $this->apply_action( $post_id, $action );
			if ( $result ) {
				update_post_meta( $post_id, self::META_KEY_PROCESSED, 1 );
				$this->log_action( $post_id, $action );
				if ( $analytics ) {
					$analytics->log_expiry( $post_id, $action );
				}
				if ( $seo ) {
					$seo->apply_seo_on_expiry( $post_id );
				}
				++$processed_count;

				/**
				 * Fires after a post has been expired.
				 *
				 * @param int    $post_id Post ID.
				 * @param string $action  Expiry action.
				 * @param mixed  $result  Result of the action.
				 */
				do_action( 'mscpe_after_expire_post', $post_id, $action, $result );
			}
		}

		wp_reset_postdata();

		/**
		 * Fires after processing expired posts.
		 *
		 * @param int $processed_count Number of posts processed.
		 */
		do_action( 'mscpe_after_process_expired_posts', $processed_count );
	}

	/**
	 * Sends email notifications for posts expiring soon.
	 *
	 * @return void
	 */
	public function send_expiry_notifications() {
		if ( ! (bool) $this->plugin->get_option( 'notify_enabled', 0 ) ) {
			return;
		}

		$days_before = max( 1, (int) $this->plugin->get_option( 'notify_days_before', 3 ) );
		$recipients  = (string) $this->plugin->get_option( 'notify_recipients', 'author' );
		$cutoff_time = time() + ( $days_before * DAY_IN_SECONDS );

		$query = new \WP_Query(
			array(
				'post_type'      => $this->get_post_types(),
				'post_status'    => array( 'publish', 'future' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_KEY_EXPIRY,
						'value'   => array( time(), $cutoff_time ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::META_KEY_PROCESSED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return;
		}

		foreach ( $query->posts as $post_id ) {
			$notify_sent = (int) get_post_meta( $post_id, self::META_KEY_NOTIFY_SENT, true );
			if ( $notify_sent > 0 ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$expiry_ts   = (int) get_post_meta( $post_id, self::META_KEY_EXPIRY, true );
			$expiry_date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiry_ts );

			$recipients_arr = array();
			if ( 'author' === $recipients || 'both' === $recipients ) {
				$author_email = get_the_author_meta( 'email', $post->post_author );
				if ( $author_email ) {
					$recipients_arr[] = $author_email;
				}
			}
			if ( 'admin' === $recipients || 'both' === $recipients ) {
				$admin_email = get_option( 'admin_email' );
				if ( $admin_email ) {
					$recipients_arr[] = $admin_email;
				}
			}

			if ( empty( $recipients_arr ) ) {
				continue;
			}

			$edit_link = get_edit_post_link( $post_id, 'raw' );
			$subject   = sprintf(
				/* translators: %s is the post title */
				__( 'Post Expiring Soon: %s', 'msc-post-expiry' ),
				$post->post_title
			);
			$body = sprintf(
				/* translators: 1: Post title, 2: Expiry date/time, 3: Edit link */
				__( "The following post is expiring soon:\n\nPost: %1\$s\nExpiry Date: %2\$s\n\nEdit post: %3\$s", 'msc-post-expiry' ),
				$post->post_title,
				$expiry_date,
				$edit_link
			);

			$sent = wp_mail( $recipients_arr, $subject, $body );
			if ( $sent ) {
				update_post_meta( $post_id, self::META_KEY_NOTIFY_SENT, time() );
			}
		}

		wp_reset_postdata();
	}

	/**
	 * Logs an expiry action to the action history.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $action  Expiry action.
	 * @return void
	 */
	public function log_action( $post_id, $action ) {
		if ( ! (bool) $this->plugin->get_option( 'log_enabled', 1 ) ) {
			return;
		}

		$post  = get_post( $post_id );
		$entry = array(
			'post_id'    => $post_id,
			/* translators: %d: Post ID number */
			'post_title' => $post ? $post->post_title : sprintf( __( 'Post #%d', 'msc-post-expiry' ), $post_id ),
			'action'     => $action,
			'timestamp'  => time(),
		);

		$log = get_option( 'mscpe_action_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );

		if ( count( $log ) > 50 ) {
			$log = array_slice( $log, 0, 50 );
		}

		update_option( 'mscpe_action_log', $log );
	}

	/**
	 * Returns the action log entries.
	 *
	 * @return array
	 */
	public function get_action_log() {
		$log = get_option( 'mscpe_action_log', array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Handles front-end redirect for expired posts with a redirect URL.
	 *
	 * @return void
	 */
	public function handle_redirect() {
		if ( ! $this->is_enabled() || ! is_singular() || ! (bool) $this->plugin->get_option( 'redirect_enabled', 0 ) ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		if ( ! (bool) get_post_meta( $post->ID, self::META_KEY_PROCESSED, true ) ) {
			return;
		}

		$redirect = (string) get_post_meta( $post->ID, self::META_KEY_REDIRECT_URL, true );
		if ( '' === $redirect ) {
			return;
		}

		wp_safe_redirect( $redirect, 302 );
		exit;
	}

	/**
	 * Applies the configured expiry action to a single post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $action  Expiry action slug.
	 * @return bool
	 */
	private function apply_action( $post_id, $action ) {
		switch ( $action ) {
			case 'private':
				return ! is_wp_error(
					wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ), true )
				);

			case 'trash':
				return false !== wp_trash_post( $post_id );

			case 'category':
				if ( 'post' !== get_post_type( $post_id ) ) {
					return false;
				}
				$cat = (int) get_post_meta( $post_id, self::META_KEY_EXPIRY_CAT, true );
				if ( $cat <= 0 ) {
					$cat = absint( $this->plugin->get_option( 'expiry_category', 0 ) );
				}
				return $cat > 0 && false !== wp_set_post_categories( $post_id, array( $cat ), false );

			case 'redirect_only':
				return true;

			case 'delete':
				return false !== wp_delete_post( $post_id, true );

			case 'draft':
			default:
				return ! is_wp_error(
					wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ), true )
				);
		}
	}
}
