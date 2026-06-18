<?php
/**
 * WooCommerce Orders Integration
 *
 * Adds a withdrawal-status column to the orders list table (legacy &amp; HPOS)
 * and injects order notes when a withdrawal request changes status.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_WC_Orders
 *
 * Handles:
 * - Custom "Withdrawal" column in the WooCommerce orders list (legacy + HPOS).
 * - Adds column to default hidden columns (toggleable via Screen Options).
 * - Adds order notes when withdrawal request statuses change.
 */
class Trece_WDEU_WC_Orders {

	/**
	 * Constructor – register hooks.
	 */
	public function __construct() {

		// Legacy CPT-based orders table.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_withdrawal_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_withdrawal_column' ), 10, 2 );

		// HPOS orders table.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_withdrawal_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_withdrawal_column_hpos' ), 10, 2 );

		// Default hidden columns.
		add_filter( 'default_hidden_columns', array( $this, 'default_hidden_columns' ), 10, 2 );

		// Order notes on withdrawal status changes.
		add_action( 'trece_wdeu_status_changed', array( $this, 'on_status_changed' ), 10, 4 );

		// Admin UX: pending count on the WooCommerce dashboard status widget.
		add_action( 'woocommerce_after_dashboard_status_widget', array( $this, 'dashboard_pending_count' ) );

		// Admin UX: notice on the order edit screen when a request is pending.
		add_action( 'admin_notices', array( $this, 'order_pending_notice' ) );
	}

	/* ------------------------------------------------------------------
	 * Admin UX
	 * ----------------------------------------------------------------*/

	/**
	 * Count of withdrawal requests still pending review.
	 *
	 * @return int
	 */
	private function pending_request_count() {

		return count(
			get_posts(
				array(
					'post_type'      => 'trece_withdrawal',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => '_trece_wdeu_status',
					'meta_value'     => 'pending',
				)
			)
		);
	}

	/**
	 * Append a pending-withdrawals line to the WooCommerce dashboard widget.
	 *
	 * @return void
	 */
	public function dashboard_pending_count() {

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count = $this->pending_request_count();

		if ( ! $count ) {
			return;
		}

		printf(
			'<li class="trece-wdeu-pending"><a href="%1$s"><strong>%2$d</strong> %3$s</a></li>',
			esc_url( admin_url( 'admin.php?page=trece-withdrawal-eu' ) ),
			absint( $count ),
			esc_html( _n( 'withdrawal request pending review', 'withdrawal requests pending review', $count, 'trece-withdrawal-eu' ) )
		);
	}

	/**
	 * Show a notice on the order edit screen when the order has a pending
	 * withdrawal request awaiting review.
	 *
	 * @return void
	 */
	public function order_pending_notice() {

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only admin context.
		$order_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : ( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $order_id ) {
			return;
		}

		$requests = get_posts(
			array(
				'post_type'      => 'trece_withdrawal',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_trece_wdeu_wc_order_id',
				'meta_value'     => $order_id,
			)
		);

		if ( empty( $requests ) ) {
			return;
		}

		$request_id = $requests[0];

		if ( 'pending' !== get_post_meta( $request_id, '_trece_wdeu_status', true ) ) {
			return;
		}

		$url = add_query_arg(
			array(
				'page'   => 'trece-withdrawal-eu',
				'action' => 'view',
				'id'     => $request_id,
			),
			admin_url( 'admin.php' )
		);

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> <a class="button button-primary" href="%2$s">%3$s</a></p></div>',
			esc_html__( 'This order has a withdrawal request pending review.', 'trece-withdrawal-eu' ),
			esc_url( $url ),
			esc_html__( 'Review request', 'trece-withdrawal-eu' )
		);
	}

	/* ------------------------------------------------------------------
	 * Column Registration
	 * ----------------------------------------------------------------*/

	/**
	 * Add the "Withdrawal" column to the orders list table.
	 *
	 * Inserts the column right after 'order_status'.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array Modified columns.
	 */
	public function add_withdrawal_column( $columns ) {

		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'order_status' === $key ) {
				$new_columns['withdrawal'] = __( 'Withdrawal', 'trece-withdrawal-eu' );
			}
		}

		// Fallback: if 'order_status' wasn't found, append.
		if ( ! isset( $new_columns['withdrawal'] ) ) {
			$new_columns['withdrawal'] = __( 'Withdrawal', 'trece-withdrawal-eu' );
		}

		return $new_columns;
	}

	/* ------------------------------------------------------------------
	 * Column Rendering – Legacy
	 * ----------------------------------------------------------------*/

	/**
	 * Render the withdrawal column content (legacy CPT orders).
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID (order).
	 *
	 * @return void
	 */
	public function render_withdrawal_column( $column, $post_id ) {

		if ( 'withdrawal' !== $column ) {
			return;
		}

		$this->output_withdrawal_badge( $post_id );
	}

	/* ------------------------------------------------------------------
	 * Column Rendering – HPOS
	 * ----------------------------------------------------------------*/

	/**
	 * Render the withdrawal column content (HPOS orders table).
	 *
	 * @param string   $column Column key.
	 * @param WC_Order $order  Order object.
	 *
	 * @return void
	 */
	public function render_withdrawal_column_hpos( $column, $order ) {

		if ( 'withdrawal' !== $column ) {
			return;
		}

		$this->output_withdrawal_badge( $order->get_id() );
	}

	/* ------------------------------------------------------------------
	 * Default Hidden Columns
	 * ----------------------------------------------------------------*/

	/**
	 * Mark the "Withdrawal" column as hidden by default.
	 *
	 * Users can enable it via Screen Options.
	 *
	 * @param array     $hidden List of hidden column keys.
	 * @param WP_Screen $screen Current admin screen.
	 *
	 * @return array Modified hidden columns.
	 */
	public function default_hidden_columns( $hidden, $screen ) {

		$target_screens = array(
			'edit-shop_order',
			'woocommerce_page_wc-orders',
		);

		if ( isset( $screen->id ) && in_array( $screen->id, $target_screens, true ) ) {
			$hidden[] = 'withdrawal';
		}

		return $hidden;
	}

	/* ------------------------------------------------------------------
	 * Order Notes on Withdrawal Status Changes
	 * ----------------------------------------------------------------*/

	/**
	 * Add a private WC order note when a withdrawal request changes status.
	 *
	 * Hooked to the `trece_wdeu_status_changed` action fired by the
	 * withdrawal CPT lifecycle.
	 *
	 * @param int    $withdrawal_id Withdrawal CPT post ID.
	 * @param string $new_status    New withdrawal status.
	 * @param string $old_status    Previous withdrawal status.
	 * @param array  $extra         Extra data (may include 'comment', 'order_id').
	 *
	 * @return void
	 */
	public function on_status_changed( $withdrawal_id, $new_status, $old_status, $extra = array() ) {

		$order_id = isset( $extra['order_id'] ) ? absint( $extra['order_id'] ) : 0;

		if ( ! $order_id ) {
			$order_id = absint( get_post_meta( $withdrawal_id, '_trece_wdeu_wc_order_id', true ) );
		}

		if ( ! $order_id ) {
			return;
		}

		$note = '';

		switch ( $new_status ) {
			case 'pending':
				/* translators: %d: withdrawal request ID */
				$note = sprintf(
					__( 'Withdrawal request received (#%d).', 'trece-withdrawal-eu' ),
					$withdrawal_id
				);
				break;

			case 'accepted':
				/* translators: %d: withdrawal request ID */
				$note = sprintf(
					__( 'Withdrawal request accepted (#%d).', 'trece-withdrawal-eu' ),
					$withdrawal_id
				);
				break;

			case 'rejected':
				/* translators: 1: withdrawal request ID, 2: rejection comment */
				$comment = ! empty( $extra['comment'] ) ? sanitize_text_field( $extra['comment'] ) : '';
				$note    = sprintf(
					__( 'Withdrawal request rejected (#%1$d): %2$s', 'trece-withdrawal-eu' ),
					$withdrawal_id,
					$comment
				);
				break;

			case 'completed':
				/* translators: %d: withdrawal request ID */
				$note = sprintf(
					__( 'Withdrawal request completed (#%d).', 'trece-withdrawal-eu' ),
					$withdrawal_id
				);
				break;
		}

		if ( ! empty( $note ) ) {
			self::add_order_note( $order_id, $note );
		}
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Add a private order note to a WC order.
	 *
	 * @param int    $order_id WC order ID.
	 * @param string $note     Note text.
	 *
	 * @return int|false Note (comment) ID on success, false on failure.
	 */
	public static function add_order_note( $order_id, $note ) {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		return $order->add_order_note( $note, 0, false );
	}

	/**
	 * Output a withdrawal status badge for a given order.
	 *
	 * Queries the trece_withdrawal CPT for a request linked to the order.
	 *
	 * @param int $order_id WC order ID.
	 *
	 * @return void
	 */
	private function output_withdrawal_badge( $order_id ) {

		$requests = get_posts(
			array(
				'post_type'      => 'trece_withdrawal',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_key'       => '_trece_wdeu_wc_order_id',
				'meta_value'     => $order_id,
				'fields'         => 'ids',
			)
		);

		if ( empty( $requests ) ) {
			echo '—';
			return;
		}

		$request_id = $requests[0];
		$status     = get_post_meta( $request_id, '_trece_wdeu_status', true );

		if ( empty( $status ) ) {
			$status = get_post_status( $request_id );
		}

		$colors = array(
			'pending'   => '#f0ad4e',
			'accepted'  => '#5cb85c',
			'rejected'  => '#d9534f',
			'completed' => '#0275d8',
		);

		$color = isset( $colors[ $status ] ) ? $colors[ $status ] : '#999';

		printf(
			'<span class="trece-wdeu-status-badge" style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:12px;font-weight:600;color:#fff;background:%1$s;">%2$s</span>',
			esc_attr( $color ),
			esc_html( ucfirst( $status ) )
		);
	}
}
