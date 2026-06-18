<?php
/**
 * Admin Log – Withdrawal Requests list table.
 *
 * Registers the top-level "Withdrawals" menu, renders the main list page
 * using WP_List_Table, and handles bulk status changes.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_Admin_Log
 *
 * Displays the paginated, filterable list of all withdrawal requests
 * inside a WP_List_Table and processes bulk actions.
 *
 * @since 1.0.0
 */
class Trece_WDEU_Admin_Log {

	/**
	 * Screen hook suffix returned by add_menu_page().
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Constructor – register hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
	}

	/**
	 * Register the top-level menu and the "All Requests" submenu.
	 *
	 * @since 1.0.0
	 */
	public function register_menu() {
		$this->hook = add_menu_page(
			__( 'Withdrawals', 'trece-withdrawal-eu' ),
			__( 'Withdrawals', 'trece-withdrawal-eu' ),
			'manage_options',
			'trece-withdrawal-eu',
			array( $this, 'render_page' ),
			'dashicons-shield',
			56
		);

		add_submenu_page(
			'trece-withdrawal-eu',
			__( 'All Requests', 'trece-withdrawal-eu' ),
			__( 'All Requests', 'trece-withdrawal-eu' ),
			'manage_options',
			'trece-withdrawal-eu',
			array( $this, 'render_page' )
		);

		add_action( 'load-' . $this->hook, array( $this, 'screen_options' ) );
	}

	/**
	 * Add the "per page" screen option.
	 *
	 * @since 1.0.0
	 */
	public function screen_options() {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Requests per page', 'trece-withdrawal-eu' ),
				'default' => 20,
				'option'  => 'trece_wdeu_requests_per_page',
			)
		);
	}

	/**
	 * Persist the screen option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $status Screen option status (false to save).
	 * @param string $option Option name.
	 * @param int    $value  Value chosen by the user.
	 * @return mixed
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( 'trece_wdeu_requests_per_page' === $option ) {
			return absint( $value );
		}
		return $status;
	}

	/**
	 * Render the list page or delegate to the detail view.
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		// Delegate to detail view when action=view.
		if ( isset( $_GET['action'] ) && 'view' === $_GET['action'] && isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$detail = new Trece_WDEU_Admin_Detail();
			$detail->render_page( absint( $_GET['id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Process any pending bulk action.
		$this->process_bulk_action();

		$list_table = new Trece_WDEU_Requests_List_Table();
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Withdrawal Requests', 'trece-withdrawal-eu' ); ?></h1>
			<hr class="wp-header-end">

			<?php settings_errors( 'trece_wdeu_bulk' ); ?>

			<form method="get">
				<input type="hidden" name="page" value="trece-withdrawal-eu" />

				<div class="trece-wdeu-filters" style="display:flex;gap:10px;align-items:flex-end;margin:12px 0;">
					<?php // Status filter. ?>
					<div>
						<label for="trece-wdeu-filter-status">
							<strong><?php esc_html_e( 'Status', 'trece-withdrawal-eu' ); ?></strong>
						</label><br>
						<select id="trece-wdeu-filter-status" name="wdeu_status">
							<option value=""><?php esc_html_e( 'All statuses', 'trece-withdrawal-eu' ); ?></option>
							<?php
							$current_status = isset( $_GET['wdeu_status'] ) ? sanitize_text_field( wp_unslash( $_GET['wdeu_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							foreach ( array( 'pending', 'accepted', 'rejected', 'completed' ) as $s ) :
								?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $current_status, $s ); ?>>
									<?php echo esc_html( ucfirst( $s ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<?php // Date range filter. ?>
					<div>
						<label for="trece-wdeu-filter-from">
							<strong><?php esc_html_e( 'From', 'trece-withdrawal-eu' ); ?></strong>
						</label><br>
						<input
							type="date"
							id="trece-wdeu-filter-from"
							name="wdeu_from"
							value="<?php echo esc_attr( isset( $_GET['wdeu_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wdeu_from'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
						/>
					</div>
					<div>
						<label for="trece-wdeu-filter-to">
							<strong><?php esc_html_e( 'To', 'trece-withdrawal-eu' ); ?></strong>
						</label><br>
						<input
							type="date"
							id="trece-wdeu-filter-to"
							name="wdeu_to"
							value="<?php echo esc_attr( isset( $_GET['wdeu_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wdeu_to'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
						/>
					</div>

					<div>
						<?php submit_button( __( 'Filter', 'trece-withdrawal-eu' ), 'secondary', 'trece_wdeu_filter', false ); ?>
					</div>
				</div>

				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Process bulk actions dispatched from the list table.
	 *
	 * @since 1.0.0
	 */
	private function process_bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		$action = '';
		if ( isset( $_GET['action'] ) && '-1' !== $_GET['action'] ) {
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		} elseif ( isset( $_GET['action2'] ) && '-1' !== $_GET['action2'] ) {
			$action = sanitize_text_field( wp_unslash( $_GET['action2'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $action, array( 'accept', 'reject', 'complete' ), true ) ) {
			return;
		}

		// Verify nonce.
		check_admin_referer( 'bulk-withdrawal-requests' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'trece-withdrawal-eu' ) );
		}

		$post_ids = isset( $_GET['withdrawal_requests'] ) ? array_map( 'absint', (array) $_GET['withdrawal_requests'] ) : array();

		if ( empty( $post_ids ) ) {
			return;
		}

		$status_map = array(
			'accept'   => 'accepted',
			'reject'   => 'rejected',
			'complete' => 'completed',
		);

		$new_status = $status_map[ $action ];
		$count      = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'trece_withdrawal' !== $post->post_type ) {
				continue;
			}

			$old_status = get_post_meta( $post_id, '_trece_wdeu_status', true );
			if ( $old_status === $new_status ) {
				continue;
			}

			update_post_meta( $post_id, '_trece_wdeu_status', $new_status );
			update_post_meta( $post_id, '_trece_wdeu_resolved_at', current_time( 'mysql', true ) );

			/**
			 * Fires when a withdrawal request status is changed.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $post_id    Withdrawal post ID.
			 * @param string $new_status New status.
			 * @param string $old_status Previous status.
			 */
			do_action( 'trece_wdeu_status_changed', $post_id, $new_status, $old_status );

			// Send email notification via the email service.
			if ( class_exists( 'Trece_WDEU_Email_Service' ) ) {
				$email_service = new Trece_WDEU_Email_Service();
				$email_service->send_status_change( $post_id, $new_status );
			}

			++$count;
		}

		if ( $count > 0 ) {
			add_settings_error(
				'trece_wdeu_bulk',
				'trece_wdeu_bulk_updated',
				/* translators: 1: number of updated requests, 2: new status */
				sprintf(
					_n(
						'%1$d request marked as %2$s.',
						'%1$d requests marked as %2$s.',
						$count,
						'trece-withdrawal-eu'
					),
					$count,
					$new_status
				),
				'updated'
			);
		}
	}
}

/* -------------------------------------------------------------------------- */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Trece_WDEU_Requests_List_Table
 *
 * Custom WP_List_Table for displaying withdrawal requests.
 *
 * @since 1.0.0
 */
class Trece_WDEU_Requests_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'withdrawal_request',
				'plural'   => 'withdrawal_requests',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define table columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array Column slug => label.
	 */
	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'id'        => __( 'ID', 'trece-withdrawal-eu' ),
			'customer'  => __( 'Customer', 'trece-withdrawal-eu' ),
			'email'     => __( 'Email', 'trece-withdrawal-eu' ),
			'order'     => __( 'Order', 'trece-withdrawal-eu' ),
			'submitted' => __( 'Submitted', 'trece-withdrawal-eu' ),
			'status'    => __( 'Status', 'trece-withdrawal-eu' ),
			'hash'      => __( 'Receipt Hash', 'trece-withdrawal-eu' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array Column slug => [ orderby key, default desc ].
	 */
	public function get_sortable_columns() {
		return array(
			'id'        => array( 'ID', false ),
			'submitted' => array( 'submitted', true ),
			'status'    => array( 'status', false ),
		);
	}

	/**
	 * Prepare items: run query, apply filters, pagination.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = $this->get_items_per_page( 'trece_wdeu_requests_per_page', 20 );
		$current_page = $this->get_pagenum();

		// Build meta_query.
		$meta_query = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		// Status filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['wdeu_status'] ) ? sanitize_text_field( wp_unslash( $_GET['wdeu_status'] ) ) : '';
		if ( $status_filter && in_array( $status_filter, array( 'pending', 'accepted', 'rejected', 'completed' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_trece_wdeu_status',
				'value' => $status_filter,
			);
		}

		// Date range filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_GET['wdeu_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wdeu_from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to   = isset( $_GET['wdeu_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wdeu_to'] ) ) : '';

		if ( $date_from ) {
			$meta_query[] = array(
				'key'     => '_trece_wdeu_submitted_at',
				'value'   => $date_from . ' 00:00:00',
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		}
		if ( $date_to ) {
			$meta_query[] = array(
				'key'     => '_trece_wdeu_submitted_at',
				'value'   => $date_to . ' 23:59:59',
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}

		// Sorting.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby_param = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'ID';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_param   = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		$order_param   = in_array( $order_param, array( 'ASC', 'DESC' ), true ) ? $order_param : 'DESC';

		$query_args = array(
			'post_type'      => 'trece_withdrawal',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'order'          => $order_param,
		);

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		// Map orderby to query args.
		switch ( $orderby_param ) {
			case 'submitted':
				$query_args['meta_key'] = '_trece_wdeu_submitted_at'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value';
				break;

			case 'status':
				$query_args['meta_key'] = '_trece_wdeu_status'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value';
				break;

			default:
				$query_args['orderby'] = 'ID';
				break;
		}

		$query = new WP_Query( $query_args );

		$this->items = $query->posts;

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => $query->max_num_pages,
			)
		);
	}

	/**
	 * Render the checkbox column.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $item Current row post object.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="withdrawal_requests[]" value="%d" />',
			absint( $item->ID )
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $item        Current row post object.
	 * @param string  $column_name Column slug.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {

			case 'id':
				$view_url = add_query_arg(
					array(
						'page'   => 'trece-withdrawal-eu',
						'action' => 'view',
						'id'     => $item->ID,
					),
					admin_url( 'admin.php' )
				);
				return sprintf(
					'<a href="%s"><strong>#%d</strong></a>',
					esc_url( $view_url ),
					absint( $item->ID )
				);

			case 'customer':
				return esc_html( get_post_meta( $item->ID, '_trece_wdeu_customer_name', true ) );

			case 'email':
				$email = get_post_meta( $item->ID, '_trece_wdeu_customer_email', true );
				return sprintf(
					'<a href="mailto:%1$s">%1$s</a>',
					esc_attr( $email )
				);

			case 'order':
				$order_number = get_post_meta( $item->ID, '_trece_wdeu_order_number', true );
				$wc_order_id  = get_post_meta( $item->ID, '_trece_wdeu_wc_order_id', true );

				if ( $wc_order_id && function_exists( 'wc_get_order' ) ) {
					$order_url = get_edit_post_link( $wc_order_id );
					if ( function_exists( 'wc_get_page_screen_id' ) ) {
						$order_url = admin_url( 'post.php?post=' . absint( $wc_order_id ) . '&action=edit' );
					}
					return sprintf(
						'<a href="%s">#%s</a>',
						esc_url( $order_url ),
						esc_html( $order_number )
					);
				}

				return esc_html( $order_number ? '#' . $order_number : '—' );

			case 'submitted':
				$submitted = get_post_meta( $item->ID, '_trece_wdeu_submitted_at', true );
				if ( ! $submitted ) {
					return '—';
				}
				$timestamp = strtotime( $submitted );
				return esc_html(
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp )
				);

			case 'status':
				$status = get_post_meta( $item->ID, '_trece_wdeu_status', true );
				$status = $status ? $status : 'pending';
				return sprintf(
					'<span class="trece-wdeu-status trece-wdeu-status-%1$s">%2$s</span>',
					esc_attr( $status ),
					esc_html( ucfirst( $status ) )
				);

			case 'hash':
				$hash = get_post_meta( $item->ID, '_trece_wdeu_receipt_hash', true );
				if ( ! $hash ) {
					return '—';
				}
				return sprintf(
					'<code title="%1$s">%2$s</code>',
					esc_attr( $hash ),
					esc_html( substr( $hash, 0, 12 ) )
				);

			default:
				return '—';
		}
	}

	/**
	 * Define available bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @return array Action slug => label.
	 */
	public function get_bulk_actions() {
		return array(
			'accept'   => __( 'Mark as Accepted', 'trece-withdrawal-eu' ),
			'reject'   => __( 'Mark as Rejected', 'trece-withdrawal-eu' ),
			'complete' => __( 'Mark as Completed', 'trece-withdrawal-eu' ),
		);
	}

	/**
	 * Message displayed when no requests are found.
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		esc_html_e( 'No withdrawal requests found.', 'trece-withdrawal-eu' );
	}
}
