<?php
/**
 * Analytics tracking and dashboard for MSC Post Expiry.
 *
 * @package MSCPE
 */

namespace MSCPE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages analytics data.
 */
class Analytics {

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

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueues dashboard assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_mscpe-settings' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab routing parameter.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		if ( 'analytics' !== $active_tab ) {
			return;
		}

		wp_enqueue_script(
			'mscpe-chartjs',
			plugins_url( 'assets/js/vendor/chart.umd.min.js', MSCPE_PLUGIN_FILE ),
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_style(
			'mscpe-analytics-dashboard',
			MSCPE_PLUGIN_URL . 'assets/css/analytics-dashboard.css',
			array(),
			MSCPE_PLUGIN_VERSION
		);
	}

	/**
	 * Logs an expiry event to analytics.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $action  Expiry action.
	 * @param string $status  Status of the action (success/failure).
	 * @return int|false Analytics entry ID on success, false on failure.
	 */
	public function log_expiry( $post_id, $action, $status = 'success' ) {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$category_id = 0;
		$author_id   = (int) $post->post_author;

		$terms = get_the_terms( $post_id, 'category' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$category_id = (int) reset( $terms )->term_id;
		}

		$views = $this->get_post_views( $post_id );

		$post_age = ( time() - get_post_time( 'U', false, $post ) ) / DAY_IN_SECONDS;

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'mscpe_analytics',
			array(
				'post_id'             => $post_id,
				'action'              => sanitize_key( $action ),
				'category_id'         => $category_id,
				'author_id'           => $author_id,
				'views_before_expiry' => $views,
				'age_days'            => (int) $post_age,
				'status'              => sanitize_key( $status ),
				'created_at'          => time(),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Gets post view count from common sources.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function get_post_views( $post_id ) {
		if ( function_exists( 'stats_get_csv' ) ) {
			$stats = stats_get_csv( 'postviews', array( 'post_id' => $post_id, 'days' => 999 ) );
			if ( ! empty( $stats ) && isset( $stats[0]['views'] ) ) {
				return (int) $stats[0]['views'];
			}
		}

		$postviews = get_post_meta( $post_id, 'views', true );
		if ( is_numeric( $postviews ) ) {
			return (int) $postviews;
		}

		$wpp_views = get_post_meta( $post_id, 'wpp_total_views', true );
		if ( is_numeric( $wpp_views ) ) {
			return (int) $wpp_views;
		}

		return 0;
	}

	/**
	 * Gets analytics summary statistics.
	 *
	 * @param string $date_range Date range: '7 days', '30 days', '90 days', 'all'.
	 * @param array  $filters    Optional filters.
	 * @return array
	 */
	public function get_summary( $date_range = '30 days', $filters = array() ) {
		global $wpdb;

		$filter = $this->build_where_clause( $date_range, $filters );

		// Each query uses a fully-static SQL template. Optional filter conditions use a
		// toggle pattern: "(0 = %d OR column = %d)" — passing 0 makes the OR always true
		// (no filter applied); passing a non-zero value activates the filter. This means
		// the SQL template is always a string literal with no dynamic SQL construction,
		// so no variable SQL fragment is ever passed to prepare() or get_var().
		$cutoff = time() - ( 30 * DAY_IN_SECONDS );
		$total  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}mscpe_analytics
				WHERE created_at >= %d
				AND (0 = %d OR created_at >= %d)
				AND (%d = 0 OR category_id = %d)
				AND (%d = 0 OR author_id = %d)
				AND ('' = %s OR action = %s)",
				$cutoff,
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action']
			)
		);

		$month_start = mktime( 0, 0, 0, (int) gmdate( 'n' ), 1 );
		$month_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}mscpe_analytics
				WHERE created_at >= %d
				AND (0 = %d OR created_at >= %d)
				AND (%d = 0 OR category_id = %d)
				AND (%d = 0 OR author_id = %d)
				AND ('' = %s OR action = %s)",
				$month_start,
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action']
			)
		);

		$week_start = time() - ( 7 * DAY_IN_SECONDS );
		$week_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}mscpe_analytics
				WHERE created_at >= %d
				AND (0 = %d OR created_at >= %d)
				AND (%d = 0 OR category_id = %d)
				AND (%d = 0 OR author_id = %d)
				AND ('' = %s OR action = %s)",
				$week_start,
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action']
			)
		);

		$avg_age = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT AVG(age_days) FROM {$wpdb->prefix}mscpe_analytics
				WHERE created_at >= %d
				AND (0 = %d OR created_at >= %d)
				AND (%d = 0 OR category_id = %d)
				AND (%d = 0 OR author_id = %d)
				AND ('' = %s OR action = %s)",
				$cutoff,
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action']
			)
		);

		$success_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}mscpe_analytics
				WHERE status = 'success'
				AND created_at >= %d
				AND (0 = %d OR created_at >= %d)
				AND (%d = 0 OR category_id = %d)
				AND (%d = 0 OR author_id = %d)
				AND ('' = %s OR action = %s)",
				$cutoff,
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action']
			)
		);

		$success_rate = $total > 0 ? round( ( $success_count / $total ) * 100, 1 ) : 0;

		return array(
			'total_expired' => $total,
			'this_month'    => $month_count,
			'this_week'     => $week_count,
			'avg_post_age'  => round( $avg_age, 1 ),
			'success_rate'  => $success_rate,
		);
	}

	/**
	 * Gets action breakdown for pie chart.
	 *
	 * @param string $date_range Date range.
	 * @param array  $filters    Optional filters.
	 * @return array
	 */
	public function get_action_breakdown( $date_range = '30 days', $filters = array() ) {
		global $wpdb;

		$filter = $this->build_where_clause( $date_range, $filters );

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT action, COUNT(*) as count FROM {$wpdb->prefix}mscpe_analytics
				WHERE (0 = %d OR created_at >= %d)
				AND (%d = 0 OR category_id = %d)
				AND (%d = 0 OR author_id = %d)
				AND ('' = %s OR action = %s)
				GROUP BY action ORDER BY count DESC",
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action']
			),
			ARRAY_A
		);

		$labels = array(
			'draft'    => __( 'Draft', 'msc-post-expiry' ),
			'private'  => __( 'Private', 'msc-post-expiry' ),
			'trash'    => __( 'Trash', 'msc-post-expiry' ),
			'category' => __( 'Category', 'msc-post-expiry' ),
			'redirect' => __( 'Redirect', 'msc-post-expiry' ),
			'delete'   => __( 'Delete', 'msc-post-expiry' ),
		);

		$data = array();
		foreach ( $results as $row ) {
			$action = $row['action'];
			$data[] = array(
				'action' => $action,
				'label'  => isset( $labels[ $action ] ) ? $labels[ $action ] : ucfirst( $action ),
				'count'  => (int) $row['count'],
			);
		}

		return $data;
	}

	/**
	 * Gets expiry trends over time for line chart.
	 *
	 * @param string $date_range  Date range.
	 * @param string $granularity 'day' or 'week' or 'month'.
	 * @param array  $filters     Optional filters.
	 * @return array
	 */
	public function get_trends( $date_range = '30 days', $granularity = 'day', $filters = array() ) {
		global $wpdb;

		$filter = $this->build_where_clause( $date_range, $filters );

		switch ( $granularity ) {
			case 'week':
				$date_format = '%Y-%W';
				break;
			case 'month':
				$date_format = '%Y-%m';
				break;
			default:
				$date_format = '%Y-%m-%d';
		}

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DATE_FORMAT(FROM_UNIXTIME(created_at), %s) as date_group, COUNT(*) as count
				FROM {$wpdb->prefix}mscpe_analytics
				WHERE (0 = %d OR created_at >= %d)
				AND (%d = 0 OR category_id = %d)
				AND (%d = 0 OR author_id = %d)
				AND ('' = %s OR action = %s)
				GROUP BY date_group ORDER BY date_group ASC",
				$date_format,
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action']
			),
			ARRAY_A
		);

		$data = array();
		foreach ( $results as $row ) {
			$data[] = array(
				'date'  => $row['date_group'],
				'label' => $row['date_group'],
				'count' => (int) $row['count'],
			);
		}

		return $data;
	}

	/**
	 * Gets most expired categories.
	 *
	 * @param string $date_range Date range.
	 * @param int    $limit      Number of results.
	 * @param array  $filters    Optional filters.
	 * @return array
	 */
	public function get_top_categories( $date_range = '30 days', $limit = 5, $filters = array() ) {
		global $wpdb;

		$filter = $this->build_where_clause( $date_range, $filters );

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT a.category_id, t.name as category_name, COUNT(*) as count
				FROM {$wpdb->prefix}mscpe_analytics a
				LEFT JOIN {$wpdb->terms} t ON t.term_id = a.category_id
				WHERE (0 = %d OR a.created_at >= %d)
				AND (%d = 0 OR a.category_id = %d)
				AND (%d = 0 OR a.author_id = %d)
				AND ('' = %s OR a.action = %s)
				GROUP BY a.category_id ORDER BY count DESC LIMIT %d",
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action'],
				absint( $limit )
			),
			ARRAY_A
		);

		$data = array();
		foreach ( $results as $row ) {
			$data[] = array(
				'category_id'   => (int) $row['category_id'],
				'category_name' => $row['category_name'] ?: __( 'Uncategorized', 'msc-post-expiry' ),
				'count'         => (int) $row['count'],
			);
		}

		return $data;
	}

	/**
	 * Gets most expired authors.
	 *
	 * @param string $date_range Date range.
	 * @param int    $limit      Number of results.
	 * @param array  $filters    Optional filters.
	 * @return array
	 */
	public function get_top_authors( $date_range = '30 days', $limit = 5, $filters = array() ) {
		global $wpdb;

		$filter = $this->build_where_clause( $date_range, $filters );

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT a.author_id, u.display_name as author_name, COUNT(*) as count
				FROM {$wpdb->prefix}mscpe_analytics a
				LEFT JOIN {$wpdb->users} u ON u.ID = a.author_id
				WHERE (0 = %d OR a.created_at >= %d)
				AND (%d = 0 OR a.category_id = %d)
				AND (%d = 0 OR a.author_id = %d)
				AND ('' = %s OR a.action = %s)
				GROUP BY a.author_id ORDER BY count DESC LIMIT %d",
				$filter['range_cutoff'], $filter['range_cutoff'],
				$filter['category_id'],  $filter['category_id'],
				$filter['author_id'],    $filter['author_id'],
				$filter['action'],       $filter['action'],
				absint( $limit )
			),
			ARRAY_A
		);

		$data = array();
		foreach ( $results as $row ) {
			$data[] = array(
				'author_id'   => (int) $row['author_id'],
				'author_name' => $row['author_name'] ?: __( 'Unknown', 'msc-post-expiry' ),
				'count'       => (int) $row['count'],
			);
		}

		return $data;
	}

	/**
	 * Gets recent analytics entries.
	 *
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public function get_recent_entries( $limit = 20 ) {
		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT a.*, p.post_title
				FROM {$wpdb->prefix}mscpe_analytics a
				LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_id
				ORDER BY a.created_at DESC
				LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'id'         => (int) $row['id'],
					'post_id'    => (int) $row['post_id'],
					/* translators: %d: Post ID number */
					'post_title' => $row['post_title'] ?: sprintf( __( 'Post #%d', 'msc-post-expiry' ), $row['post_id'] ),
					'action'     => $row['action'],
					'status'     => $row['status'],
					'age_days'   => (int) $row['age_days'],
					'views'      => (int) $row['views_before_expiry'],
					'created_at' => (int) $row['created_at'],
				);
			},
			$results
		);
	}

	/**
	 * Extracts sanitized filter values for use in static SQL query templates.
	 *
	 * Returns flat, sanitized scalar values. Each query method embeds these directly
	 * as %d/%s placeholders in a fully-static SQL template using a toggle pattern:
	 * e.g. "(0 = %d OR column = %d)" — passing 0 / '' disables that condition so the
	 * entire SQL string is always a literal with no dynamic SQL structure.
	 *
	 * @param string $date_range Date range: '7 days', '30 days', '90 days', 'all'.
	 * @param array  $filters    Optional filters: category_id, author_id, action.
	 * @return array {
	 *     @type int    $range_cutoff UNIX timestamp for the start of the date range, 0 for 'all'.
	 *     @type int    $category_id  Category term ID filter, 0 means no filter.
	 *     @type int    $author_id    Author user ID filter, 0 means no filter.
	 *     @type string $action       Expiry action slug filter, '' means no filter.
	 * }
	 */
	private function build_where_clause( $date_range, $filters ) {
		$days = 30;
		switch ( $date_range ) {
			case '7 days':
				$days = 7;
				break;
			case '30 days':
				$days = 30;
				break;
			case '90 days':
				$days = 90;
				break;
			case 'all':
				$days = 0;
				break;
		}

		return array(
			'range_cutoff' => $days > 0 ? time() - ( $days * DAY_IN_SECONDS ) : 0,
			'category_id'  => ! empty( $filters['category_id'] ) ? absint( $filters['category_id'] ) : 0,
			'author_id'    => ! empty( $filters['author_id'] ) ? absint( $filters['author_id'] ) : 0,
			'action'       => ! empty( $filters['action'] ) ? sanitize_key( $filters['action'] ) : '',
		);
	}

	/**
	 * Renders the analytics dashboard.
	 *
	 * @param string $date_range Current date range.
	 * @param array  $filters    Current filters.
	 * @return void
	 */
	public function render_dashboard( $date_range = '30 days', $filters = array() ) {
		$summary          = $this->get_summary( $date_range, $filters );
		$action_breakdown = $this->get_action_breakdown( $date_range, $filters );
		$trends           = $this->get_trends( $date_range, 'day', $filters );
		$top_categories   = $this->get_top_categories( $date_range, 5, $filters );
		$top_authors      = $this->get_top_authors( $date_range, 5, $filters );
		$recent_entries   = $this->get_recent_entries( 10 );

		// Empty state: no analytics data yet.
		if ( empty( $summary['total_expired'] ) ) {
			echo '<div class="mscpe-analytics-dashboard">';
			echo '<div class="notice notice-info inline">';
			echo '<p><strong>' . esc_html__( 'No Analytics Data Yet', 'msc-post-expiry' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Analytics data will appear here once posts expire. Set expiry dates on posts to see activity.', 'msc-post-expiry' ) . '</p>';
			echo '</div>';
			echo '</div>';
			return;
		}

		// Register chart initialization script to run after Chart.js loads.
		$chart_js = $this->build_chart_scripts( $trends, $action_breakdown, $top_categories, $top_authors );
		wp_add_inline_script( 'mscpe-chartjs', $chart_js, 'after' );

		?>
		<div class="mscpe-analytics-dashboard">
			<div class="mscpe-analytics-controls">
				<form method="get" action="">
					<input type="hidden" name="page" value="mscpe-settings" />
					<input type="hidden" name="tab" value="analytics" />
					<label for="mscpe-date-range">
						<?php esc_html_e( 'Date Range:', 'msc-post-expiry' ); ?>
						<select name="date_range" id="mscpe-date-range">
							<option value="7 days" <?php selected( '7 days', $date_range ); ?>><?php esc_html_e( 'Last 7 Days', 'msc-post-expiry' ); ?></option>
							<option value="30 days" <?php selected( '30 days', $date_range ); ?>><?php esc_html_e( 'Last 30 Days', 'msc-post-expiry' ); ?></option>
							<option value="90 days" <?php selected( '90 days', $date_range ); ?>><?php esc_html_e( 'Last 90 Days', 'msc-post-expiry' ); ?></option>
							<option value="all" <?php selected( 'all', $date_range ); ?>><?php esc_html_e( 'All Time', 'msc-post-expiry' ); ?></option>
						</select>
					</label>
					<?php submit_button( __( 'Apply', 'msc-post-expiry' ), 'secondary', 'mscpe_apply_range', false ); ?>
				</form>
			</div>

			<div class="mscpe-summary-cards">
				<div class="mscpe-summary-card">
					<h3><?php esc_html_e( 'Total Expired', 'msc-post-expiry' ); ?></h3>
					<p class="mscpe-card-value"><?php echo esc_html( number_format_i18n( $summary['total_expired'] ) ); ?></p>
				</div>
				<div class="mscpe-summary-card">
					<h3><?php esc_html_e( 'This Month', 'msc-post-expiry' ); ?></h3>
					<p class="mscpe-card-value"><?php echo esc_html( number_format_i18n( $summary['this_month'] ) ); ?></p>
				</div>
				<div class="mscpe-summary-card">
					<h3><?php esc_html_e( 'This Week', 'msc-post-expiry' ); ?></h3>
					<p class="mscpe-card-value"><?php echo esc_html( number_format_i18n( $summary['this_week'] ) ); ?></p>
				</div>
				<div class="mscpe-summary-card">
					<h3><?php esc_html_e( 'Avg Post Age', 'msc-post-expiry' ); ?></h3>
					<p class="mscpe-card-value"><?php echo esc_html( number_format_i18n( $summary['avg_post_age'], 1 ) ); ?> <?php esc_html_e( 'days', 'msc-post-expiry' ); ?></p>
				</div>
				<div class="mscpe-summary-card">
					<h3><?php esc_html_e( 'Success Rate', 'msc-post-expiry' ); ?></h3>
					<p class="mscpe-card-value"><?php echo esc_html( number_format_i18n( $summary['success_rate'], 1 ) ); ?>%</p>
				</div>
			</div>

			<div class="mscpe-charts-grid">
				<div class="mscpe-chart-container">
					<h3><?php esc_html_e( 'Expiry Trends', 'msc-post-expiry' ); ?></h3>
					<canvas id="mscpe-trends-chart"></canvas>
				</div>
				<div class="mscpe-chart-container">
					<h3><?php esc_html_e( 'Action Breakdown', 'msc-post-expiry' ); ?></h3>
					<canvas id="mscpe-actions-chart"></canvas>
				</div>
			</div>

			<div class="mscpe-charts-grid">
				<div class="mscpe-chart-container">
					<h3><?php esc_html_e( 'Top Categories', 'msc-post-expiry' ); ?></h3>
					<canvas id="mscpe-categories-chart"></canvas>
				</div>
				<div class="mscpe-chart-container">
					<h3><?php esc_html_e( 'Top Authors', 'msc-post-expiry' ); ?></h3>
					<canvas id="mscpe-authors-chart"></canvas>
				</div>
			</div>

			<div class="mscpe-recent-activity">
				<h3><?php esc_html_e( 'Recent Expiry Activity', 'msc-post-expiry' ); ?></h3>
				<?php if ( empty( $recent_entries ) ) : ?>
					<p><?php esc_html_e( 'No expiry activity recorded yet.', 'msc-post-expiry' ); ?></p>
				<?php else : ?>
					<table class="widefat" style="margin-top:1em;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Post', 'msc-post-expiry' ); ?></th>
								<th><?php esc_html_e( 'Action', 'msc-post-expiry' ); ?></th>
								<th><?php esc_html_e( 'Status', 'msc-post-expiry' ); ?></th>
								<th><?php esc_html_e( 'Age', 'msc-post-expiry' ); ?></th>
								<th><?php esc_html_e( 'Views', 'msc-post-expiry' ); ?></th>
								<th><?php esc_html_e( 'Date', 'msc-post-expiry' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_entries as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( $entry['post_title'] ); ?></td>
									<td><?php echo esc_html( ucfirst( $entry['action'] ) ); ?></td>
									<td><span class="mscpe-status mscpe-status-<?php echo esc_attr( $entry['status'] ); ?>"><?php echo esc_html( ucfirst( $entry['status'] ) ); ?></span></td>
									<td><?php echo esc_html( $entry['age_days'] ); ?> <?php esc_html_e( 'days', 'msc-post-expiry' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $entry['views'] ) ); ?></td>
									<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $entry['created_at'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Builds Chart.js initialization script.
	 *
	 * @param array $trends           Trend data.
	 * @param array $action_breakdown Action breakdown data.
	 * @param array $top_categories   Top categories data.
	 * @param array $top_authors      Top authors data.
	 * @return string JavaScript code.
	 */
	private function build_chart_scripts( $trends, $action_breakdown, $top_categories, $top_authors ) {
		ob_start();
		?>
		(function() {
			// Trends chart
			var trendsCtx = document.getElementById('mscpe-trends-chart');
			if (trendsCtx) {
				new Chart(trendsCtx, {
					type: 'line',
					data: {
						labels: <?php echo wp_json_encode( wp_list_pluck( $trends, 'label' ) ); ?>,
						datasets: [{
							label: '<?php echo esc_js( __( 'Posts Expired', 'msc-post-expiry' ) ); ?>',
							data: <?php echo wp_json_encode( wp_list_pluck( $trends, 'count' ) ); ?>,
							borderColor: '#2271b1',
							backgroundColor: 'rgba(34, 113, 177, 0.1)',
							fill: true,
							tension: 0.3
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true,
						plugins: { legend: { display: false } },
						scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
					}
				});
			}

			// Actions chart
			var actionsCtx = document.getElementById('mscpe-actions-chart');
			if (actionsCtx) {
				var actionData = <?php echo wp_json_encode( $action_breakdown ); ?>;
				new Chart(actionsCtx, {
					type: 'doughnut',
					data: {
						labels: actionData.map(function(item) { return item.label; }),
						datasets: [{ data: actionData.map(function(item) { return item.count; }), backgroundColor: ['#2271b1','#f0c33c','#72aee6','#00a32a','#d63638','#8b5cf6'] }]
					},
					options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
				});
			}

			// Categories chart
			var categoriesCtx = document.getElementById('mscpe-categories-chart');
			if (categoriesCtx) {
				var catData = <?php echo wp_json_encode( $top_categories ); ?>;
				new Chart(categoriesCtx, {
					type: 'bar',
					data: { labels: catData.map(function(item) { return item.category_name; }), datasets: [{ label: '<?php echo esc_js( __( 'Expired Posts', 'msc-post-expiry' ) ); ?>', data: catData.map(function(item) { return item.count; }), backgroundColor: '#2271b1' }] },
					options: { responsive: true, maintainAspectRatio: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
				});
			}

			// Authors chart
			var authorsCtx = document.getElementById('mscpe-authors-chart');
			if (authorsCtx) {
				var authorData = <?php echo wp_json_encode( $top_authors ); ?>;
				new Chart(authorsCtx, {
					type: 'bar',
					data: { labels: authorData.map(function(item) { return item.author_name; }), datasets: [{ label: '<?php echo esc_js( __( 'Expired Posts', 'msc-post-expiry' ) ); ?>', data: authorData.map(function(item) { return item.count; }), backgroundColor: '#72aee6' }] },
					options: { responsive: true, maintainAspectRatio: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
				});
			}
		})();
		<?php
		return ob_get_clean();
	}
}
