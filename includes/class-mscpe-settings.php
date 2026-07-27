<?php
/**
 * Admin settings class for MSC Post Expiry.
 *
 * @package MSCPE
 */

namespace MSCPE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings and metabox class.
 */
class Settings {

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

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_mscpe_save_settings', array( $this, 'handle_save' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Enqueue admin assets for the plugin settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_mscpe-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'mscpe-settings',
			MSCPE_PLUGIN_URL . 'assets/js/settings.js',
			array(),
			MSCPE_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Register admin page.
	 */
	public function register_menu() {
		add_options_page(
			esc_html__( 'MSC Post Expiry', 'msc-post-expiry' ),
			esc_html__( 'MSC Post Expiry', 'msc-post-expiry' ),
			'manage_options',
			'mscpe-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle settings save.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msc-post-expiry' ) );
		}

		// Verify nonce with better error handling.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'mscpe_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'msc-post-expiry' ) );
		}

		$module_enabled = isset( $_POST['module_enabled'] ) ? 1 : 0;
		$post_types     = isset( $_POST['post_types'] ) ? array_values( array_filter( array_map( 'sanitize_key', wp_unslash( (array) $_POST['post_types'] ) ) ) ) : array();
		$post_type_mode = isset( $_POST['post_type_mode'] ) ? sanitize_key( wp_unslash( $_POST['post_type_mode'] ) ) : 'include';
		$expiry_action  = isset( $_POST['expiry_action'] ) ? sanitize_key( wp_unslash( $_POST['expiry_action'] ) ) : 'trash';
		$expiry_category = isset( $_POST['expiry_category'] ) ? absint( wp_unslash( $_POST['expiry_category'] ) ) : 0;

		$redirect_enabled   = isset( $_POST['redirect_enabled'] ) ? 1 : 0;
		$bulk_default_days  = isset( $_POST['bulk_default_days'] ) ? absint( wp_unslash( $_POST['bulk_default_days'] ) ) : 30;
		$notify_enabled     = isset( $_POST['notify_enabled'] ) ? 1 : 0;
		$notify_days_before = isset( $_POST['notify_days_before'] ) ? absint( wp_unslash( $_POST['notify_days_before'] ) ) : 3;
		$notify_recipients  = isset( $_POST['notify_recipients'] ) ? sanitize_key( wp_unslash( $_POST['notify_recipients'] ) ) : 'author';
		$log_enabled        = isset( $_POST['log_enabled'] ) ? 1 : 0;

		$this->plugin->update_options(
			array(
				'module_enabled'     => $module_enabled,
				'post_types'         => $post_types,
				'post_type_mode'     => $post_type_mode,
				'expiry_action'      => $expiry_action,
				'expiry_category'    => $expiry_category,
				'redirect_enabled'   => $redirect_enabled,
				'bulk_default_days'  => $bulk_default_days,
				'notify_enabled'     => $notify_enabled,
				'notify_days_before' => $notify_days_before,
				'notify_recipients'  => $notify_recipients,
				'log_enabled'        => $log_enabled,
			)
		);

		/**
		 * Fires after plugin settings are saved.
		 * Allows extensions to save additional settings within the same form submission.
		 * Nonce is verified above.
		 *
		 * @param array $sanitized_post Fully sanitized POST data array.
		 */
		$sanitized_post = $this->sanitize_recursive_text( wp_unslash( $_POST ) );
		do_action( 'mscpe_settings_save', $sanitized_post );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'mscpe-settings',
					'updated' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = array(
			'module_enabled'  => (int) $this->plugin->get_option( 'module_enabled', 1 ),
			'post_types'      => (array) $this->plugin->get_option( 'post_types', array( 'post', 'page' ) ),
			'post_type_mode'  => (string) $this->plugin->get_option( 'post_type_mode', 'include' ),
			'expiry_action'   => (string) $this->plugin->get_option( 'expiry_action', 'trash' ),
			'expiry_category' => (int) $this->plugin->get_option( 'expiry_category', 0 ),
		);

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$categories = get_categories( array( 'hide_empty' => false ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab is a safe UI routing parameter.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';

		// Build tabs array with defaults.
		$tabs = array(
			array(
				'slug'  => 'settings',
				'label' => __( 'Settings', 'msc-post-expiry' ),
			),
			array(
				'slug'  => 'seo',
				'label' => __( 'SEO', 'msc-post-expiry' ),
			),
			array(
				'slug'  => 'rules',
				'label' => __( 'Smart Rules', 'msc-post-expiry' ),
			),
			array(
				'slug'  => 'analytics',
				'label' => __( 'Analytics', 'msc-post-expiry' ),
			),
			array(
				'slug'  => 'history',
				'label' => __( 'History', 'msc-post-expiry' ),
			),
			array(
				'slug'  => 'support',
				'label' => __( 'Support', 'msc-post-expiry' ),
			),
		);

		/**
		 * Filter the tabs displayed on the settings page.
		 *
		 * @param array $tabs Array of tab definitions with 'slug' and 'label'.
		 */
		$tabs = apply_filters( 'mscpe_tabs', $tabs );

		// Build URLs and active state for each tab.
		foreach ( $tabs as &$tab ) {
			$tab['url']    = add_query_arg(
				array(
					'page' => 'mscpe-settings',
					'tab'  => $tab['slug'],
				),
				admin_url( 'options-general.php' )
			);
			$tab['active'] = $active_tab === $tab['slug'];
		}
		unset( $tab );

		// Validate active tab against registered tabs.
		$valid_slugs = wp_list_pluck( $tabs, 'slug' );
		if ( ! in_array( $active_tab, $valid_slugs, true ) ) {
			$active_tab = 'settings';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag.
		$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MSC Post Expiry', 'msc-post-expiry' ); ?></h1>

			<?php if ( '1' === $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'msc-post-expiry' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab ) : ?>
					<a href="<?php echo esc_url( $tab['url'] ); ?>" class="nav-tab <?php echo $tab['active'] ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $active_tab ) {
				case 'settings':
					$this->render_settings_tab( $options, $post_types, $categories );
					break;
				case 'seo':
					$this->render_seo_tab();
					break;
				case 'rules':
					$this->render_rules_tab();
					break;
				case 'analytics':
					$this->render_analytics_tab();
					break;
				case 'history':
					$this->render_history_tab();
					break;
				case 'support':
					$this->render_support_tab();
					break;
				default:
					/**
					 * Action to render content for custom tabs.
					 *
					 * @param string $active_tab The active tab slug.
					 * @param array  $options    Plugin options.
					 */
					do_action( 'mscpe_tab_content', $active_tab, $options );
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the settings tab content.
	 *
	 * @param array $options    Plugin options.
	 * @param array $post_types Available post types.
	 * @param array $categories Available categories.
	 */
	public function render_settings_tab( $options, $post_types, $categories ) {
		// Build expiry actions array.
		$expiry_actions = array(
			'trash'         => __( 'Move to Trash', 'msc-post-expiry' ),
			'delete'        => __( 'Permanently Delete', 'msc-post-expiry' ),
			'draft'         => __( 'Change to Draft', 'msc-post-expiry' ),
			'private'       => __( 'Change to Private', 'msc-post-expiry' ),
			'category'      => __( 'Move to Category', 'msc-post-expiry' ),
			'redirect_only' => __( 'Redirect Only (keep published)', 'msc-post-expiry' ),
		);
		$expiry_actions = apply_filters( 'mscpe_expiry_actions', $expiry_actions );

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1.5em;">
			<input type="hidden" name="action" value="mscpe_save_settings" />
			<?php wp_nonce_field( 'mscpe_save_settings' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable post expiry', 'msc-post-expiry' ); ?></th>
						<td>
							<label for="module_enabled">
								<input id="module_enabled" type="checkbox" name="module_enabled" value="1" <?php checked( 1, $options['module_enabled'] ); ?> />
								<?php esc_html_e( 'Allow posts to expire on a scheduled date.', 'msc-post-expiry' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="post_type_mode"><?php esc_html_e( 'Post type mode', 'msc-post-expiry' ); ?></label></th>
						<td>
							<select id="post_type_mode" name="post_type_mode">
								<option value="include" <?php selected( 'include', $options['post_type_mode'] ); ?>><?php esc_html_e( 'Enable expiry only on selected post types', 'msc-post-expiry' ); ?></option>
								<option value="exclude" <?php selected( 'exclude', $options['post_type_mode'] ); ?>><?php esc_html_e( 'Enable expiry on all public post types except selected', 'msc-post-expiry' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'msc-post-expiry' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $post_types as $post_type ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $options['post_types'], true ) ); ?> />
										<?php echo esc_html( $post_type->labels->singular_name ); ?>
										<span style="color:#888;font-size:12px;">(<?php echo esc_html( $post_type->name ); ?>)</span>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="expiry_action"><?php esc_html_e( 'Expiry action', 'msc-post-expiry' ); ?></label></th>
						<td>
							<select id="expiry_action" name="expiry_action">
								<?php foreach ( $expiry_actions as $action_key => $action_label ) : ?>
									<option value="<?php echo esc_attr( $action_key ); ?>" <?php selected( $action_key, $options['expiry_action'] ); ?>><?php echo esc_html( $action_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'What should happen when a post expires.', 'msc-post-expiry' ); ?></p>
						</td>
					</tr>
					<tr id="expiry-category-row">
						<th scope="row"><label for="expiry_category"><?php esc_html_e( 'Expiry category', 'msc-post-expiry' ); ?></label></th>
						<td>
							<select id="expiry_category" name="expiry_category">
								<option value="0"><?php esc_html_e( 'Select a category', 'msc-post-expiry' ); ?></option>
								<?php foreach ( $categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( (int) $category->term_id, $options['expiry_category'] ); ?>><?php echo esc_html( $category->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Posts will be moved to this category when expired. Only used when "Move to Category" is selected above.', 'msc-post-expiry' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Redirect Settings', 'msc-post-expiry' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable redirects', 'msc-post-expiry' ); ?></th>
						<td>
							<label for="redirect_enabled">
								<input id="redirect_enabled" type="checkbox" name="redirect_enabled" value="1" <?php checked( 1, (int) $this->plugin->get_option( 'redirect_enabled', 0 ) ); ?> />
								<?php esc_html_e( 'Redirect expired posts to a specified URL (set per-post in the editor).', 'msc-post-expiry' ); ?>
							</label>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Bulk Scheduling', 'msc-post-expiry' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="bulk_default_days"><?php esc_html_e( 'Default expiry window', 'msc-post-expiry' ); ?></label></th>
						<td>
							<input id="bulk_default_days" type="number" name="bulk_default_days" min="1" max="3650" value="<?php echo esc_attr( (int) $this->plugin->get_option( 'bulk_default_days', 30 ) ); ?>" style="width:80px;" />
							<?php esc_html_e( 'days from now', 'msc-post-expiry' ); ?>
							<p class="description"><?php esc_html_e( 'Used when bulk-scheduling expiry from the Posts list.', 'msc-post-expiry' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Notifications', 'msc-post-expiry' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email notifications', 'msc-post-expiry' ); ?></th>
						<td>
							<label for="notify_enabled">
								<input id="notify_enabled" type="checkbox" name="notify_enabled" value="1" <?php checked( 1, (int) $this->plugin->get_option( 'notify_enabled', 0 ) ); ?> />
								<?php esc_html_e( 'Send email notification before posts expire.', 'msc-post-expiry' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="notify_days_before"><?php esc_html_e( 'Days before expiry', 'msc-post-expiry' ); ?></label></th>
						<td>
							<input id="notify_days_before" type="number" name="notify_days_before" min="1" max="30" value="<?php echo esc_attr( (int) $this->plugin->get_option( 'notify_days_before', 3 ) ); ?>" style="width:80px;" />
							<p class="description"><?php esc_html_e( 'Send notification this many days before a post expires.', 'msc-post-expiry' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="notify_recipients"><?php esc_html_e( 'Notify', 'msc-post-expiry' ); ?></label></th>
						<td>
							<?php $nr = (string) $this->plugin->get_option( 'notify_recipients', 'author' ); ?>
							<select id="notify_recipients" name="notify_recipients">
								<option value="author" <?php selected( 'author', $nr ); ?>><?php esc_html_e( 'Post Author', 'msc-post-expiry' ); ?></option>
								<option value="admin" <?php selected( 'admin', $nr ); ?>><?php esc_html_e( 'Site Admin', 'msc-post-expiry' ); ?></option>
								<option value="both" <?php selected( 'both', $nr ); ?>><?php esc_html_e( 'Both', 'msc-post-expiry' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Logging', 'msc-post-expiry' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Action log', 'msc-post-expiry' ); ?></th>
						<td>
							<label for="log_enabled">
								<input id="log_enabled" type="checkbox" name="log_enabled" value="1" <?php checked( 1, (int) $this->plugin->get_option( 'log_enabled', 1 ) ); ?> />
								<?php esc_html_e( 'Log expiry actions in the History tab.', 'msc-post-expiry' ); ?>
							</label>
						</td>
					</tr>
				</tbody>
			</table>

			<?php
			/**
			 * Fires before extension settings sections are rendered.
			 *
			 * @param array<string,mixed> $options Current options.
			 */
			do_action( 'mscpe_settings_before_extensions', $options );

			/**
			 * Renders extension settings inside the shared form.
			 *
			 * @param array<string,mixed> $options Current options.
			 */
			do_action( 'mscpe_settings_sections', $options );
			?>

			<?php submit_button( __( 'Save Settings', 'msc-post-expiry' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the support tab content.
	 */
	public function render_support_tab() {
		?>
		<div style="max-width:800px;margin-top:1.5em;">

			<h2><?php esc_html_e( 'How to Use Post Expiry', 'msc-post-expiry' ); ?></h2>
			<p><?php esc_html_e( 'Post Expiry allows you to automatically handle posts when they reach a specified expiration date.', 'msc-post-expiry' ); ?></p>

			<h3><?php esc_html_e( 'Setting an Expiry Date', 'msc-post-expiry' ); ?></h3>
			<p><?php esc_html_e( 'When editing a post or page, look for the "Post Expiry" box in the sidebar on the right. Enter the date and time when you want the post to expire.', 'msc-post-expiry' ); ?></p>

			<h3><?php esc_html_e( 'Expiry Actions', 'msc-post-expiry' ); ?></h3>
			<p><?php esc_html_e( 'When a post expires, one of the following actions will occur based on your settings:', 'msc-post-expiry' ); ?></p>
			<ul style="margin-left:20px;">
				<li><strong><?php esc_html_e( 'Move to Trash', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'The post is moved to trash and no longer visible to visitors.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Permanently Delete', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'The post is permanently deleted from your site.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Change to Draft', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'The post is changed to draft status and hidden from visitors.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Change to Private', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'The post is changed to private status and only visible to logged-in users with appropriate permissions.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Move to Category', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'The post is moved to a specific archive category. Configure the category in the Settings tab.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Redirect Only', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'The post stays published but visitors are redirected to a specified URL.', 'msc-post-expiry' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Per-Post Overrides', 'msc-post-expiry' ); ?></h3>
			<p><?php esc_html_e( 'Each post can have its own expiry action, redirect URL, and target category. These per-post settings override the global default configured in the Settings tab.', 'msc-post-expiry' ); ?></p>

			<h3><?php esc_html_e( 'Smart Expiry Rules', 'msc-post-expiry' ); ?></h3>
			<p><?php esc_html_e( 'Smart Rules let you define automatic actions based on post properties. When a post expires, the plugin checks your rules in priority order before falling back to the default action.', 'msc-post-expiry' ); ?></p>
			<p><?php esc_html_e( 'Available conditions:', 'msc-post-expiry' ); ?></p>
			<ul style="margin-left:20px;">
				<li><strong><?php esc_html_e( 'Category', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'Match posts in a specific category.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Tag', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'Match posts with a specific tag.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Author', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'Match posts by a specific author.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Post Age', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'Match posts older than a specified number of days.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Custom Field', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'Match posts with a specific custom field value.', 'msc-post-expiry' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Example: "If a post is in the News category, move it to draft when it expires" or "If a post is older than 90 days, delete it permanently."', 'msc-post-expiry' ); ?></p>

			<h3><?php esc_html_e( 'SEO Handling', 'msc-post-expiry' ); ?></h3>
			<p><?php esc_html_e( 'When a post expires, the plugin can automatically manage SEO signals. Configure these options in the SEO tab:', 'msc-post-expiry' ); ?></p>
			<ul style="margin-left:20px;">
				<li><strong><?php esc_html_e( 'Noindex / Nofollow', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'Prevent search engines from indexing or following links on expired posts.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'Canonical URL', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'Set a canonical URL to redirect SEO value to another page.', 'msc-post-expiry' ); ?></li>
				<li><strong><?php esc_html_e( 'HTTP Status Code', 'msc-post-expiry' ); ?></strong> - <?php esc_html_e( 'Return a 410 Gone status code for expired posts.', 'msc-post-expiry' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Analytics & History', 'msc-post-expiry' ); ?></h3>
			<p><?php esc_html_e( 'The Analytics tab provides charts showing expiry trends, action breakdowns, and top categories/authors. The History tab shows the last 50 expiry actions for quick reference.', 'msc-post-expiry' ); ?></p>

			<h3><?php esc_html_e( 'Post Type Configuration', 'msc-post-expiry' ); ?></h3>
			<p><?php esc_html_e( 'Use the Settings tab to choose which post types support expiry dates. You can either enable expiry on specific post types or disable it on specific types while enabling it on all others.', 'msc-post-expiry' ); ?></p>

			<h3><?php esc_html_e( 'Frequently Asked Questions', 'msc-post-expiry' ); ?></h3>

			<h4><?php esc_html_e( 'The Post Expiry metabox is not showing on my posts.', 'msc-post-expiry' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Check that "Enable post expiry" is ticked on the Settings tab.', 'msc-post-expiry' ); ?></li>
				<li><?php esc_html_e( 'Check that the post type (e.g. Post, Page) is selected in the Post types list.', 'msc-post-expiry' ); ?></li>
				<li><?php esc_html_e( 'The metabox appears in the sidebar on the right when editing a post.', 'msc-post-expiry' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'When does the expiry action occur?', 'msc-post-expiry' ); ?></h4>
			<p><?php esc_html_e( 'Post expiry is processed by WordPress scheduled events (cron). The action will occur shortly after the expiry date and time passes. The exact timing depends on your site traffic and WordPress cron configuration.', 'msc-post-expiry' ); ?></p>

			<h4><?php esc_html_e( 'Can I disable expiry for a specific post?', 'msc-post-expiry' ); ?></h4>
			<p><?php esc_html_e( 'Yes. Simply leave the expiry date and time fields empty in the Post Expiry metabox.', 'msc-post-expiry' ); ?></p>

			<h4><?php esc_html_e( 'How do Smart Rules work with per-post overrides?', 'msc-post-expiry' ); ?></h4>
			<p><?php esc_html_e( 'When a post expires, the plugin checks Smart Rules first. If a rule matches the post, its action is used. If no rules match, the per-post action override is checked. If neither exists, the global default action is applied.', 'msc-post-expiry' ); ?></p>

			<hr style="margin:2em 0;" />

			<h2><?php esc_html_e( 'Support', 'msc-post-expiry' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s is the URL to the plugin support forum on WordPress.org. */
					wp_kses_post( __( 'Need a hand? Visit the <a href="%s" target="_blank" rel="noopener noreferrer">plugin support forum on WordPress.org</a> for help, bug reports and feature requests.', 'msc-post-expiry' ) ),
					esc_url( 'https://wordpress.org/support/plugin/msc-post-expiry/' )
				);
				?>
			</p>

		</div>
		<?php
	}

	/**
	 * Register metabox for post expiry date.
	 *
	 * @param string  $post_type Post type being filtered.
	 * @param WP_Post $post      Post object being edited.
	 */
	public function register_metabox( $post_type, $post ) {
		if ( ! $this->plugin->get_option( 'module_enabled', 1 ) ) {
			return;
		}

		// Only show in classic editor — block editor uses expiry-sidebar.js.
		if ( $post && use_block_editor_for_post( $post ) ) {
			return;
		}
		if ( ! $post && use_block_editor_for_post_type( $post_type ) ) {
			return;
		}

		$post_types     = (array) $this->plugin->get_option( 'post_types', array( 'post', 'page' ) );
		$post_type_mode = (string) $this->plugin->get_option( 'post_type_mode', 'include' );

		// Determine which post types should have the metabox.
		$all_post_types = get_post_types( array( 'public' => true ) );
		if ( 'include' === $post_type_mode ) {
			$target_post_types = $post_types;
		} else {
			$target_post_types = array_diff( $all_post_types, $post_types );
		}

		if ( ! in_array( $post_type, $target_post_types, true ) ) {
			return;
		}

		add_meta_box(
			'mscpe-expiry-metabox',
			__( 'Post Expiry', 'msc-post-expiry' ),
			array( $this, 'render_metabox' ),
			$post_type,
			'side',
			'high'
		);
	}

	/**
	 * Render metabox for post expiry date.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'mscpe_expiry_nonce', 'mscpe_expiry_nonce' );

		// Read unified timestamp first (preferred).
		$timestamp = get_post_meta( $post->ID, '_mscpe_expiry_timestamp', true );
		if ( is_numeric( $timestamp ) && (int) $timestamp > 0 ) {
			$ts              = (int) $timestamp;
			$expiry_date_val = wp_date( 'Y-m-d', $ts );
			$expiry_time_val = wp_date( 'H:i', $ts );
		} else {
			// Fallback to separate date/time fields.
			$expiry_date_val = get_post_meta( $post->ID, 'mscpe_expiry_date', true );
			$expiry_time_val = get_post_meta( $post->ID, 'mscpe_expiry_time', true );
		}

		?>
		<div style="padding: 12px 0;">
			<label for="mscpe_expiry_date" style="display:block;margin-bottom:8px;">
				<strong><?php esc_html_e( 'Expiry Date', 'msc-post-expiry' ); ?></strong>
			</label>
			<input type="date" id="mscpe_expiry_date" name="mscpe_expiry_date" value="<?php echo esc_attr( $expiry_date_val ); ?>" style="width:100%;padding:6px;box-sizing:border-box;" />

			<label for="mscpe_expiry_time" style="display:block;margin-top:8px;margin-bottom:8px;">
				<strong><?php esc_html_e( 'Expiry Time', 'msc-post-expiry' ); ?></strong>
			</label>
			<input type="time" id="mscpe_expiry_time" name="mscpe_expiry_time" value="<?php echo esc_attr( $expiry_time_val ); ?>" style="width:100%;padding:6px;box-sizing:border-box;" />

			<p class="description" style="margin-top:8px;font-size:12px;color:#666;">
				<?php esc_html_e( 'Leave empty to disable expiry for this post.', 'msc-post-expiry' ); ?>
			</p>

			<?php
			$action_val   = (string) get_post_meta( $post->ID, '_mscpe_expiry_action', true );
			$redirect_val = (string) get_post_meta( $post->ID, '_mscpe_expiry_redirect_url', true );
			$category_val = (int) get_post_meta( $post->ID, '_mscpe_expiry_category', true );
			?>
			<label for="mscpe_expiry_action" style="display:block;margin-top:12px;margin-bottom:8px;">
				<strong><?php esc_html_e( 'Action for this post', 'msc-post-expiry' ); ?></strong>
			</label>
			<select id="mscpe_expiry_action" name="mscpe_expiry_action" style="width:100%;padding:6px;box-sizing:border-box;">
				<?php foreach ( \MSCPE\Module::get_action_choices() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $action_val, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="mscpe_expiry_redirect_url" style="display:block;margin-top:8px;margin-bottom:8px;">
				<strong><?php esc_html_e( 'Redirect URL', 'msc-post-expiry' ); ?></strong>
			</label>
			<input type="url" id="mscpe_expiry_redirect_url" name="mscpe_expiry_redirect_url" value="<?php echo esc_attr( $redirect_val ); ?>" placeholder="https://example.com/" style="width:100%;padding:6px;box-sizing:border-box;" />
			<p class="description" style="margin-top:4px;font-size:12px;color:#666;">
				<?php esc_html_e( 'Used when the action is "Redirect Only".', 'msc-post-expiry' ); ?>
			</p>

			<label for="mscpe_expiry_category" style="display:block;margin-top:8px;margin-bottom:8px;">
				<strong><?php esc_html_e( 'Move to category', 'msc-post-expiry' ); ?></strong>
			</label>
			<?php
			wp_dropdown_categories(
				array(
					'id'               => 'mscpe_expiry_category',
					'name'             => 'mscpe_expiry_category',
					'selected'         => $category_val,
					'show_option_none' => __( '— Select —', 'msc-post-expiry' ),
					'option_none_value' => 0,
					'hide_empty'       => 0,
				)
			);
			?>
			<p class="description" style="margin-top:4px;font-size:12px;color:#666;">
				<?php esc_html_e( 'Used when the action is "Move to Category" (posts only).', 'msc-post-expiry' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save metabox data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_metabox( $post_id, $post ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check below.
		if ( ! isset( $_POST['mscpe_expiry_nonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		$nonce = sanitize_text_field( wp_unslash( $_POST['mscpe_expiry_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'mscpe_expiry_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
		$expiry_date = isset( $_POST['mscpe_expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['mscpe_expiry_date'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
		$expiry_time = isset( $_POST['mscpe_expiry_time'] ) ? sanitize_text_field( wp_unslash( $_POST['mscpe_expiry_time'] ) ) : '';

		if ( $expiry_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiry_date ) ) {
			return;
		}
		if ( $expiry_time && ! preg_match( '/^\d{2}:\d{2}$/', $expiry_time ) ) {
			return;
		}

		if ( $expiry_date ) {
			// Store the unified timestamp — the single source of truth since 1.6.0.
			// Default time to '00:00' so posts without a time still expire at midnight.
			$effective_time = $expiry_time ?: '00:00';
			$timestamp      = strtotime( $expiry_date . ' ' . $effective_time );
			if ( false !== $timestamp ) {
				update_post_meta( $post_id, '_mscpe_expiry_timestamp', $timestamp );
				update_post_meta( $post_id, '_mscpe_expiry_processed', 0 );
			}
		} else {
			delete_post_meta( $post_id, '_mscpe_expiry_timestamp' );
		}

		// Clear any legacy date/time meta (migrated to the timestamp).
		delete_post_meta( $post_id, 'mscpe_expiry_date' );
		delete_post_meta( $post_id, 'mscpe_expiry_time' );

		// Per-post overrides.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
		$action = isset( $_POST['mscpe_expiry_action'] ) ? sanitize_key( wp_unslash( $_POST['mscpe_expiry_action'] ) ) : '';
		if ( '' !== $action && array_key_exists( $action, \MSCPE\Module::get_action_choices() ) ) {
			update_post_meta( $post_id, '_mscpe_expiry_action', $action );
		} else {
			delete_post_meta( $post_id, '_mscpe_expiry_action' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
		$redirect_url = isset( $_POST['mscpe_expiry_redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['mscpe_expiry_redirect_url'] ) ) : '';
		if ( '' !== $redirect_url ) {
			update_post_meta( $post_id, '_mscpe_expiry_redirect_url', $redirect_url );
		} else {
			delete_post_meta( $post_id, '_mscpe_expiry_redirect_url' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
		$category = isset( $_POST['mscpe_expiry_category'] ) ? absint( wp_unslash( $_POST['mscpe_expiry_category'] ) ) : 0;
		if ( $category > 0 ) {
			update_post_meta( $post_id, '_mscpe_expiry_category', $category );
		} else {
			delete_post_meta( $post_id, '_mscpe_expiry_category' );
		}
	}

	/**
	 * Render the SEO tab content.
	 */
	public function render_seo_tab() {
		$seo = $this->plugin->get_seo();
		if ( ! $seo ) {
			return;
		}

		if ( isset( $_POST['mscpe_seo_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['mscpe_seo_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'mscpe_seo_settings' ) && current_user_can( 'manage_options' ) ) {
				$seo_data = array(
					'noindex_enabled'    => isset( $_POST['noindex_enabled'] ) ? 1 : 0,
					'nofollow_enabled'   => isset( $_POST['nofollow_enabled'] ) ? 1 : 0,
					'canonical_strategy' => isset( $_POST['canonical_strategy'] ) ? sanitize_key( wp_unslash( $_POST['canonical_strategy'] ) ) : '',
					'status_code'        => isset( $_POST['status_code'] ) ? sanitize_key( wp_unslash( $_POST['status_code'] ) ) : '',
				);
				$seo->save_options( $seo_data );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SEO settings saved.', 'msc-post-expiry' ) . '</p></div>';
			}
		}

		$seo_options = get_option( 'mscpe_seo_options', array() );
		$defaults    = SEO::get_default_options();
		$seo_options = wp_parse_args( $seo_options, $defaults );
		?>
		<div style="max-width:800px;margin-top:1.5em;">
			<h2><?php esc_html_e( 'SEO Settings for Expired Posts', 'msc-post-expiry' ); ?></h2>
			<p><?php esc_html_e( 'Configure how search engines handle expired content.', 'msc-post-expiry' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'mscpe_seo_settings', 'mscpe_seo_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Noindex', 'msc-post-expiry' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="noindex_enabled" value="1" <?php checked( 1, (int) $seo_options['noindex_enabled'] ); ?> />
									<?php esc_html_e( 'Add noindex to expired posts (prevents indexing).', 'msc-post-expiry' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Nofollow', 'msc-post-expiry' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="nofollow_enabled" value="1" <?php checked( 1, (int) $seo_options['nofollow_enabled'] ); ?> />
									<?php esc_html_e( 'Add nofollow to expired posts (prevents link following).', 'msc-post-expiry' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="canonical_strategy"><?php esc_html_e( 'Canonical', 'msc-post-expiry' ); ?></label></th>
							<td>
								<select id="canonical_strategy" name="canonical_strategy">
									<option value="none" <?php selected( 'none', $seo_options['canonical_strategy'] ); ?>><?php esc_html_e( 'No change', 'msc-post-expiry' ); ?></option>
									<option value="homepage" <?php selected( 'homepage', $seo_options['canonical_strategy'] ); ?>><?php esc_html_e( 'Point to home page', 'msc-post-expiry' ); ?></option>
									<option value="category" <?php selected( 'category', $seo_options['canonical_strategy'] ); ?>><?php esc_html_e( 'Point to primary category', 'msc-post-expiry' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="status_code"><?php esc_html_e( 'HTTP Status Code', 'msc-post-expiry' ); ?></label></th>
							<td>
								<select id="status_code" name="status_code">
									<option value="200" <?php selected( '200', $seo_options['status_code'] ); ?>>200 OK</option>
									<option value="410" <?php selected( '410', $seo_options['status_code'] ); ?>>410 Gone</option>
								</select>
								<p class="description"><?php esc_html_e( '410 tells search engines the page is intentionally gone.', 'msc-post-expiry' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save SEO Settings', 'msc-post-expiry' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Rules tab content.
	 */
	public function render_rules_tab() {
		$rules = $this->plugin->get_rules();
		if ( ! $rules ) {
			return;
		}

		// Handle rule save.
		if ( isset( $_POST['mscpe_rules_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['mscpe_rules_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'mscpe_rules_settings' ) && current_user_can( 'manage_options' ) ) {
				$condition_type  = isset( $_POST['condition_type'] ) ? sanitize_key( wp_unslash( $_POST['condition_type'] ) ) : '';
				$condition_value = isset( $_POST['condition_value'] ) ? sanitize_text_field( wp_unslash( $_POST['condition_value'] ) ) : '';
				$action_type     = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';
				$action_value    = isset( $_POST['action_value'] ) ? sanitize_text_field( wp_unslash( $_POST['action_value'] ) ) : '';

				$condition_config = $this->build_condition_config( $condition_type, $condition_value );

				if ( null === $condition_config ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Rule not saved: the condition value could not be resolved. Check the expected format for the selected condition.', 'msc-post-expiry' ) . '</p></div>';
				} else {
					$rule_data = array(
						'name'             => isset( $_POST['rule_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_name'] ) ) : '',
						'description'      => '',
						'enabled'          => isset( $_POST['rule_enabled'] ) ? 1 : 0,
						'priority'         => isset( $_POST['priority'] ) ? absint( wp_unslash( $_POST['priority'] ) ) : 10,
						'condition_type'   => $condition_type,
						'condition_config' => $condition_config,
						'action_type'      => $action_type,
						'action_config'    => $this->build_action_config( $action_type, $action_value ),
					);
					$rules->save_rule( $rule_data );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule saved.', 'msc-post-expiry' ) . '</p></div>';
				}
			}
		}

		// Handle rule delete.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['delete_rule'] ) && isset( $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'mscpe_delete_rule' ) && current_user_can( 'manage_options' ) ) {
				$rules->delete_rule( absint( wp_unslash( $_GET['delete_rule'] ) ) );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule deleted.', 'msc-post-expiry' ) . '</p></div>';
			}
		}

		$all_rules = $rules->get_rules();
		?>
		<div style="max-width:800px;margin-top:1.5em;">
			<h2><?php esc_html_e( 'Smart Expiry Rules', 'msc-post-expiry' ); ?></h2>
			<p><?php esc_html_e( 'Smart Rules automatically determine what happens when a post expires based on its properties — like category, tag, author, age, or custom fields. When a post expires, the plugin checks these rules in priority order. If a rule matches, its action overrides the default expiry action.', 'msc-post-expiry' ); ?></p>

			<?php
			$condition_types = Rule_Evaluator::get_condition_types();
			$action_types    = Rules::get_action_types();
			?>
			<?php if ( ! empty( $all_rules ) ) : ?>
				<table class="widefat" style="margin-bottom:2em;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rule', 'msc-post-expiry' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'msc-post-expiry' ); ?></th>
							<th><?php esc_html_e( 'Condition', 'msc-post-expiry' ); ?></th>
							<th><?php esc_html_e( 'Action', 'msc-post-expiry' ); ?></th>
							<th><?php esc_html_e( 'Status', 'msc-post-expiry' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_rules as $index => $rule ) : ?>
							<?php
							$type_key       = isset( $rule['condition_type'] ) ? $rule['condition_type'] : '';
							$action_key     = isset( $rule['action_type'] ) ? $rule['action_type'] : '';
							$condition_text = isset( $condition_types[ $type_key ] ) ? $condition_types[ $type_key ] : $type_key;
							$summary        = $this->summarize_rule_condition( $rule );
							?>
							<tr>
								<td><?php echo esc_html( ! empty( $rule['name'] ) ? $rule['name'] : sprintf( /* translators: %d: rule number. */ __( 'Rule %d', 'msc-post-expiry' ), $index + 1 ) ); ?></td>
								<td><?php echo esc_html( $rule['priority'] ?? 10 ); ?></td>
								<td><?php echo esc_html( $condition_text . ( $summary ? ': ' . $summary : '' ) ); ?></td>
								<td><?php echo esc_html( isset( $action_types[ $action_key ] ) ? $action_types[ $action_key ] : $action_key ); ?></td>
								<td>
									<?php if ( ! empty( $rule['enabled'] ) ) : ?>
										<?php esc_html_e( 'Enabled', 'msc-post-expiry' ); ?>
									<?php else : ?>
										<strong style="color:#b32d2e;"><?php esc_html_e( 'Disabled', 'msc-post-expiry' ); ?></strong>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'mscpe-settings', 'tab' => 'rules', 'delete_rule' => $index ), admin_url( 'options-general.php' ) ), 'mscpe_delete_rule' ) ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Delete this rule?', 'msc-post-expiry' ); ?>');">
										<?php esc_html_e( 'Delete', 'msc-post-expiry' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Add New Rule', 'msc-post-expiry' ); ?></h3>
			<form method="post" action="">
				<?php wp_nonce_field( 'mscpe_rules_settings', 'mscpe_rules_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="rule_name"><?php esc_html_e( 'Rule Name', 'msc-post-expiry' ); ?></label></th>
							<td>
								<input type="text" id="rule_name" name="rule_name" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Optional — a short label so you can recognise the rule later.', 'msc-post-expiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="condition_type"><?php esc_html_e( 'Condition', 'msc-post-expiry' ); ?></label></th>
							<td>
								<select id="condition_type" name="condition_type">
									<?php foreach ( $condition_types as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="condition_value"><?php esc_html_e( 'Condition Value', 'msc-post-expiry' ); ?></label></th>
							<td>
								<input type="text" id="condition_value" name="condition_value" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Category/tag slug or ID; author login or ID; minimum days for Post Age; a number or "min-max" range for Comment Count and Post Views; "field_name=value" (or just "field_name" for exists) for Custom Field.', 'msc-post-expiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="action_type"><?php esc_html_e( 'Action', 'msc-post-expiry' ); ?></label></th>
							<td>
								<select id="action_type" name="action_type">
									<?php foreach ( $action_types as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="action_value"><?php esc_html_e( 'Action Value', 'msc-post-expiry' ); ?></label></th>
							<td>
								<input type="text" id="action_value" name="action_value" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Category ID or slug for "Move to Category", URL for "Set Redirect URL". Not used by other actions.', 'msc-post-expiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="priority"><?php esc_html_e( 'Priority', 'msc-post-expiry' ); ?></label></th>
							<td>
								<input type="number" id="priority" name="priority" value="10" min="1" max="100" style="width:80px;" />
								<p class="description"><?php esc_html_e( 'Lower number = higher priority. The first matching rule wins.', 'msc-post-expiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enabled', 'msc-post-expiry' ); ?></th>
							<td>
								<label for="rule_enabled">
									<input type="checkbox" id="rule_enabled" name="rule_enabled" value="1" checked="checked" />
									<?php esc_html_e( 'Rule is active immediately after saving', 'msc-post-expiry' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Add Rule', 'msc-post-expiry' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Builds a condition config array from the simple form value.
	 *
	 * @param string $condition_type  Condition type.
	 * @param string $condition_value Raw form value.
	 * @return array|null Config array, or null when the value cannot be resolved.
	 */
	private function build_condition_config( $condition_type, $condition_value ) {
		$condition_value = trim( $condition_value );

		switch ( $condition_type ) {
			case 'category':
				$term = get_term_by( is_numeric( $condition_value ) ? 'id' : 'slug', $condition_value, 'category' );
				return $term ? array( 'category_ids' => array( (int) $term->term_id ) ) : null;

			case 'tag':
				$term = get_term_by( is_numeric( $condition_value ) ? 'id' : 'slug', $condition_value, 'post_tag' );
				return $term ? array( 'tag_ids' => array( (int) $term->term_id ) ) : null;

			case 'author':
				$user = is_numeric( $condition_value ) ? get_user_by( 'id', (int) $condition_value ) : get_user_by( 'login', $condition_value );
				return $user ? array( 'author_ids' => array( (int) $user->ID ) ) : null;

			case 'age':
				$days = absint( $condition_value );
				return $days > 0 ? array( 'min_days' => $days ) : null;

			case 'comments':
			case 'views':
				$range = $this->parse_min_max( $condition_value );
				if ( null === $range ) {
					return null;
				}
				return 'comments' === $condition_type
					? array(
						'min_comments' => $range[0],
						'max_comments' => $range[1],
					)
					: array(
						'min_views' => $range[0],
						'max_views' => $range[1],
					);

			case 'custom_field':
				if ( '' === $condition_value ) {
					return null;
				}
				if ( false !== strpos( $condition_value, '=' ) ) {
					list( $field_name, $field_value ) = array_map( 'trim', explode( '=', $condition_value, 2 ) );
					if ( '' === $field_name ) {
						return null;
					}
					return array(
						'field_name'  => $field_name,
						'field_value' => $field_value,
						'compare'     => 'equals',
					);
				}
				return array(
					'field_name'  => $condition_value,
					'field_value' => '',
					'compare'     => 'exists',
				);

			default:
				return null;
		}
	}

	/**
	 * Parses a "min" or "min-max" numeric string.
	 *
	 * @param string $value Raw value.
	 * @return array{0:int,1:int}|null [min, max] (0 = unbounded), or null when invalid.
	 */
	private function parse_min_max( $value ) {
		if ( preg_match( '/^(\d+)\s*-\s*(\d+)$/', $value, $m ) ) {
			return array( (int) $m[1], (int) $m[2] );
		}
		if ( is_numeric( $value ) ) {
			return array( (int) $value, 0 );
		}
		return null;
	}

	/**
	 * Builds an action config array from the simple form value.
	 *
	 * @param string $action_type  Action type.
	 * @param string $action_value Raw form value.
	 * @return array
	 */
	private function build_action_config( $action_type, $action_value ) {
		$action_value = trim( $action_value );

		switch ( $action_type ) {
			case 'category':
				if ( is_numeric( $action_value ) ) {
					return array( 'category_id' => absint( $action_value ) );
				}
				$term = get_term_by( 'slug', $action_value, 'category' );
				return array( 'category_id' => $term ? (int) $term->term_id : 0 );

			case 'redirect':
				return array( 'redirect_url' => esc_url_raw( $action_value ) );

			default:
				return array();
		}
	}

	/**
	 * Human-readable one-line summary of a rule condition config.
	 *
	 * @param array $rule Stored rule.
	 * @return string
	 */
	private function summarize_rule_condition( $rule ) {
		$config = isset( $rule['condition_config'] ) && is_array( $rule['condition_config'] ) ? $rule['condition_config'] : array();
		$type   = isset( $rule['condition_type'] ) ? $rule['condition_type'] : '';

		switch ( $type ) {
			case 'category':
			case 'tag':
				$key      = 'category' === $type ? 'category_ids' : 'tag_ids';
				$taxonomy = 'category' === $type ? 'category' : 'post_tag';
				$ids      = isset( $config[ $key ] ) ? (array) $config[ $key ] : array();
				$names    = array();
				foreach ( $ids as $id ) {
					$term    = get_term( (int) $id, $taxonomy );
					$names[] = ( $term && ! is_wp_error( $term ) ) ? $term->name : "#$id";
				}
				return implode( ', ', $names );

			case 'author':
				$ids   = isset( $config['author_ids'] ) ? (array) $config['author_ids'] : array();
				$names = array();
				foreach ( $ids as $id ) {
					$user    = get_user_by( 'id', (int) $id );
					$names[] = $user ? $user->user_login : "#$id";
				}
				return implode( ', ', $names );

			case 'age':
				return isset( $config['min_days'] ) && $config['min_days'] > 0
					/* translators: %d: number of days. */
					? sprintf( __( '≥ %d days old', 'msc-post-expiry' ), (int) $config['min_days'] )
					: '';

			case 'comments':
			case 'views':
				$prefix = 'comments' === $type ? 'min_comments' : 'min_views';
				$suffix = 'comments' === $type ? 'max_comments' : 'max_views';
				$min    = isset( $config[ $prefix ] ) ? (int) $config[ $prefix ] : 0;
				$max    = isset( $config[ $suffix ] ) ? (int) $config[ $suffix ] : 0;
				if ( $min > 0 && $max > 0 ) {
					return "$min – $max";
				}
				if ( $min > 0 ) {
					/* translators: %d: minimum count. */
					return sprintf( __( '≥ %d', 'msc-post-expiry' ), $min );
				}
				if ( $max > 0 ) {
					/* translators: %d: maximum count. */
					return sprintf( __( '≤ %d', 'msc-post-expiry' ), $max );
				}
				return '';

			case 'custom_field':
				$field   = isset( $config['field_name'] ) ? $config['field_name'] : '';
				$value   = isset( $config['field_value'] ) ? $config['field_value'] : '';
				$compare = isset( $config['compare'] ) ? $config['compare'] : 'equals';
				return 'exists' === $compare ? "$field (exists)" : "$field = $value";

			default:
				return '';
		}
	}

	/**
	 * Render the Analytics tab content.
	 */
	public function render_analytics_tab() {
		$analytics = $this->plugin->get_analytics();
		if ( ! $analytics ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( wp_unslash( $_GET['date_range'] ) ) : '30 days';
		$analytics->render_dashboard( $date_range );
	}

	/**
	 * Render the History tab content.
	 */
	public function render_history_tab() {
		$module = $this->plugin->get_module();
		if ( ! $module ) {
			return;
		}

		$log = $module->get_action_log();
		?>
		<div style="max-width:800px;margin-top:1.5em;">
			<h2><?php esc_html_e( 'Expiry Action History', 'msc-post-expiry' ); ?></h2>
			<p><?php esc_html_e( 'Recent expiry actions (last 50 entries).', 'msc-post-expiry' ); ?></p>

			<?php if ( empty( $log ) ) : ?>
				<p><?php esc_html_e( 'No expiry actions recorded yet.', 'msc-post-expiry' ); ?></p>
			<?php else : ?>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post', 'msc-post-expiry' ); ?></th>
							<th><?php esc_html_e( 'Action', 'msc-post-expiry' ); ?></th>
							<th><?php esc_html_e( 'Date', 'msc-post-expiry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $entry ) : ?>
							<tr>
								<td>
									<?php
									$edit_link = get_edit_post_link( $entry['post_id'] );
									if ( $edit_link ) {
										printf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html( $entry['post_title'] ) );
									} else {
										echo esc_html( $entry['post_title'] );
									}
									?>
								</td>
								<td><?php echo esc_html( ucfirst( $entry['action'] ) ); ?></td>
								<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $entry['timestamp'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Recursively sanitize scalar input values with sanitize_text_field().
	 *
	 * @param mixed $value Raw input value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_recursive_text( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->sanitize_recursive_text( $item );
			}

			return $value;
		}

		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}

		return $value;
	}
}
