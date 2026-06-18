<?php
/**
 * WooCommerce My Account – Withdrawal Endpoint
 *
 * Adds a "Right of withdrawal" tab to the customer My Account area,
 * listing eligible orders and their withdrawal status.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_WC_MyAccount
 *
 * Handles:
 * - Registering the `withdrawal` rewrite endpoint.
 * - Adding the "Right of withdrawal" menu item to My Account.
 * - Rendering the endpoint content via a template.
 * - Calculating withdrawal deadlines per order.
 */
class Trece_WDEU_WC_MyAccount {

	/**
	 * Constructor – register hooks.
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_withdrawal_endpoint', array( $this, 'render_endpoint' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_native_orders_action' ), 10, 2 );
	}

	/* ------------------------------------------------------------------
	 * Native Orders Table Action
	 * ----------------------------------------------------------------*/

	public function add_native_orders_action( $actions, $order ) {
		$settings = get_option( 'trece_wdeu_settings', array() );
		$defaults = array(
			'deadline_days'      => 14,
			'deadline_basis'     => 'order_date',
			'grace_days'         => 0,
			'eligible_statuses'  => array( 'processing', 'completed' ),
			'withdrawal_page_id' => 0,
		);
		$settings = wp_parse_args( $settings, $defaults );

		$eligible_statuses = array_map( 'sanitize_text_field', (array) $settings['eligible_statuses'] );

		if ( in_array( $order->get_status(), $eligible_statuses, true ) ) {
			if ( $this->is_deadline_open( $order, $settings )
				&& $this->has_withdrawable_items( $order )
				&& Trece_WDEU_WC_Checkout::country_in_scope( $order->get_billing_country() ) ) {
				$withdrawal_status = $this->get_order_withdrawal_status( $order->get_id() );
				
				if ( ! $withdrawal_status ) {
					$page_id = absint( $settings['withdrawal_page_id'] );
					if ( $page_id ) {
						$url = add_query_arg(
							array(
								'order_number'  => $order->get_order_number(),
								'auto_withdraw' => 1,
							),
							get_permalink( $page_id )
						);

						$actions['withdraw'] = array(
							'url'  => $url,
							'name' => __( 'Withdraw', 'trece-withdrawal-eu' ),
						);
					}
				}
			}
		}

		return $actions;
	}

	/* ------------------------------------------------------------------
	 * Endpoint Registration
	 * ----------------------------------------------------------------*/

	/**
	 * Register the `withdrawal` rewrite endpoint.
	 *
	 * After activation, a flush_rewrite_rules() call is needed.
	 *
	 * @return void
	 */
	public function add_endpoint() {

		add_rewrite_endpoint( 'withdrawal', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add the `withdrawal` query variable.
	 *
	 * @param array $vars Query vars.
	 *
	 * @return array Modified query vars.
	 */
	public function add_query_vars( $vars ) {

		$vars[] = 'withdrawal';

		return $vars;
	}

	/* ------------------------------------------------------------------
	 * Menu Item
	 * ----------------------------------------------------------------*/

	/**
	 * Add the "Right of withdrawal" item to the My Account navigation,
	 * positioned immediately after "Orders".
	 *
	 * @param array $items Menu items (slug => label).
	 *
	 * @return array Modified menu items.
	 */
	public function add_menu_item( $items ) {

		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( 'orders' === $key ) {
				$new_items['withdrawal'] = __( 'Right of withdrawal', 'trece-withdrawal-eu' );
			}
		}

		// Fallback: if 'orders' wasn't found, append before 'customer-logout'.
		if ( ! isset( $new_items['withdrawal'] ) ) {
			$logout = isset( $new_items['customer-logout'] ) ? $new_items['customer-logout'] : null;
			unset( $new_items['customer-logout'] );
			$new_items['withdrawal'] = __( 'Right of withdrawal', 'trece-withdrawal-eu' );
			if ( $logout ) {
				$new_items['customer-logout'] = $logout;
			}
		}

		return $new_items;
	}

	/* ------------------------------------------------------------------
	 * Endpoint Rendering
	 * ----------------------------------------------------------------*/

	/**
	 * Render the withdrawal endpoint content.
	 *
	 * Gathers the current user's eligible orders, calculates deadline info,
	 * checks for existing withdrawal requests, and loads the template.
	 *
	 * @return void
	 */
	public function render_endpoint() {

		$customer_id = get_current_user_id();

		if ( ! $customer_id ) {
			echo '<p>' . esc_html__( 'You need to be logged in to view this page.', 'trece-withdrawal-eu' ) . '</p>';
			return;
		}

		$settings = get_option( 'trece_wdeu_settings', array() );

		$defaults = array(
			'deadline_days'      => 14,
			'deadline_basis'     => 'order_date',
			'grace_days'         => 0,
			'eligible_statuses'  => array( 'processing', 'completed' ),
			'withdrawal_page_id' => 0,
		);

		$settings = wp_parse_args( $settings, $defaults );

		// Query customer orders in eligible statuses.
		$eligible_statuses = array_map( 'sanitize_text_field', (array) $settings['eligible_statuses'] );

		$orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'status'      => $eligible_statuses,
				'limit'       => 50,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$orders_data = array();

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();

			// Deadline calculation, item eligibility & country applicability.
			$deadline_open = $this->is_deadline_open( $order, $settings )
				&& $this->has_withdrawable_items( $order )
				&& Trece_WDEU_WC_Checkout::country_in_scope( $order->get_billing_country() );

			// Check for existing withdrawal request.
			$withdrawal_status = $this->get_order_withdrawal_status( $order_id );
			$withdrawal_url    = '';

			if ( $withdrawal_status ) {
				$withdrawal_url = wc_get_account_endpoint_url( 'withdrawal' );
			} elseif ( $deadline_open ) {
				// Build the withdrawal form URL.
				$page_id = absint( $settings['withdrawal_page_id'] );

				if ( $page_id ) {
					$withdrawal_url = add_query_arg(
						array(
							'order_number'  => $order->get_order_number(),
							'auto_withdraw' => 1,
						),
						get_permalink( $page_id )
					);
				}
			}

			$orders_data[] = array(
				'order'             => $order,
				'order_id'          => $order_id,
				'order_number'      => $order->get_order_number(),
				'order_date'        => $order->get_date_created(),
				'order_status'      => $order->get_status(),
				'order_url'         => $order->get_view_order_url(),
				'deadline_open'     => $deadline_open,
				'withdrawal_status' => $withdrawal_status,
				'withdrawal_url'    => $withdrawal_url,
			);
		}

		// Load the template.
		$template_path = plugin_dir_path( dirname( __DIR__ ) ) . 'templates/myaccount-withdrawal.php';

		/**
		 * Filter the template path for the My Account withdrawal tab.
		 *
		 * @since 1.0.0
		 *
		 * @param string $template_path Absolute path to the template file.
		 */
		$template_path = apply_filters( 'trece_wdeu_myaccount_template', $template_path );

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/* ------------------------------------------------------------------
	 * Deadline Helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Determine whether the withdrawal deadline is still open for an order.
	 *
	 * @param WC_Order $order    Order object.
	 * @param array    $settings Plugin settings (merged with defaults).
	 *
	 * @return bool True if the consumer can still withdraw.
	 */
	private function is_deadline_open( $order, $settings ) {

		$deadline_days = absint( $settings['deadline_days'] );
		$grace_days    = absint( $settings['grace_days'] );

		/**
		 * Filter the total withdrawal deadline days.
		 *
		 * @since 1.0.0
		 *
		 * @param int      $deadline_days Total days (deadline + grace).
		 * @param WC_Order $order         Order object.
		 */
		$total_days = apply_filters( 'trece_wdeu_withdrawal_deadline_days', $deadline_days + $grace_days, $order );

		$basis = $settings['deadline_basis'];

		if ( 'completion_date' === $basis ) {
			$completed_date = $order->get_date_completed();

			// If the order is not yet completed, the deadline hasn't started.
			if ( ! $completed_date ) {
				return true;
			}

			$start_date = $completed_date;
		} else {
			// Default: order_date.
			$start_date = $order->get_date_created();
		}

		if ( ! $start_date ) {
			return false;
		}

		$deadline = clone $start_date;
		$deadline->modify( '+' . $total_days . ' days' );

		$now = new WC_DateTime( 'now', new DateTimeZone( 'UTC' ) );

		return $now <= $deadline;
	}

	/**
	 * Check if the order has at least one item eligible for withdrawal.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return bool True if at least one item retains the right of withdrawal.
	 */
	private function has_withdrawable_items( $order ) {

		$classified = Trece_WDEU_WC_Product::classify_order_items( $order );

		return ! empty( $classified['withdrawable'] );
	}

	/**
	 * Get the withdrawal request status for an order, if one exists.
	 *
	 * @param int $order_id WC order ID.
	 *
	 * @return string|null Withdrawal status or null if no request exists.
	 */
	private function get_order_withdrawal_status( $order_id ) {

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
			return null;
		}

		$status = get_post_meta( $requests[0], '_trece_wdeu_status', true );

		return ! empty( $status ) ? $status : get_post_status( $requests[0] );
	}
}
