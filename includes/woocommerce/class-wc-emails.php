<?php
/**
 * WooCommerce Emails – Withdrawal Notice Injection
 *
 * Injects an informational withdrawal-rights notice into customer order
 * emails based on the eligible order statuses configured in the plugin settings.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_WC_Emails
 *
 * Handles:
 * - Hooking into `woocommerce_email_before_order_table` to inject a
 *   withdrawal-rights notice with a link to the withdrawal form page.
 * - Respecting `show_in_emails` and `eligible_statuses` settings.
 * - Rendering both HTML and plain-text versions.
 */
class Trece_WDEU_WC_Emails {

	/**
	 * Map of WC order status slugs to their customer email IDs.
	 *
	 * @var array
	 */
	private static $status_email_map = array(
		'processing' => 'customer_processing_order',
		'completed'  => 'customer_completed_order',
		'on-hold'    => 'customer_on_hold_order',
		'refunded'   => 'customer_refunded_order',
	);

	/**
	 * Constructor – register hooks.
	 */
	public function __construct() {

		add_action( 'woocommerce_email_before_order_table', array( $this, 'inject_withdrawal_notice' ), 10, 4 );
	}

	/* ------------------------------------------------------------------
	 * Notice Injection
	 * ----------------------------------------------------------------*/

	/**
	 * Inject a withdrawal-rights notice before the order table in
	 * customer emails.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether this is an admin email.
	 * @param bool     $plain_text    Whether the email is plain text.
	 * @param WC_Email $email         Email object.
	 *
	 * @return void
	 */
	public function inject_withdrawal_notice( $order, $sent_to_admin, $plain_text, $email ) {

		// Never show in admin emails.
		if ( $sent_to_admin ) {
			return;
		}

		$settings = get_option( 'trece_wdeu_settings', array() );

		$defaults = array(
			'show_in_emails'     => true,
			'eligible_statuses'  => array( 'processing', 'completed' ),
			'withdrawal_page_id' => 0,
		);

		$settings = wp_parse_args( $settings, $defaults );

		// Check the show_in_emails flag.
		if ( empty( $settings['show_in_emails'] ) ) {
			return;
		}

		// Determine which email IDs are eligible.
		$eligible_email_ids = $this->get_eligible_email_ids( $settings['eligible_statuses'] );

		// Always include customer_invoice for customer-facing emails.
		$eligible_email_ids[] = 'customer_invoice';

		if ( ! $email || ! isset( $email->id ) ) {
			return;
		}

		if ( ! in_array( $email->id, $eligible_email_ids, true ) ) {
			return;
		}

		// Build the withdrawal form URL.
		$page_id = absint( $settings['withdrawal_page_id'] );

		if ( ! $page_id ) {
			return;
		}

		$withdrawal_url = add_query_arg(
			'order_number',
			$order->get_order_number(),
			get_permalink( $page_id )
		);

		if ( $plain_text ) {
			$this->render_plain_text_notice( $withdrawal_url, $order );
		} else {
			$this->render_html_notice( $withdrawal_url, $order );
		}
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ----------------------------------------------------------------*/

	/**
	 * Render the withdrawal notice in HTML format.
	 *
	 * @param string   $url   Withdrawal form URL.
	 * @param WC_Order $order Order object.
	 *
	 * @return void
	 */
	private function render_html_notice( $url, $order ) {

		/**
		 * Filter the withdrawal notice heading shown in emails.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $heading Heading text.
		 * @param WC_Order $order   Order object.
		 */
		$heading = apply_filters(
			'trece_wdeu_email_notice_heading',
			__( 'Right of Withdrawal', 'trece-withdrawal-eu' ),
			$order
		);

		/**
		 * Filter the withdrawal notice body text shown in emails.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $body  Body text.
		 * @param WC_Order $order Order object.
		 */
		$body = apply_filters(
			'trece_wdeu_email_notice_body',
			__( 'Under EU consumer law, you have the right to withdraw from this purchase within the statutory withdrawal period. To exercise your right of withdrawal, please use the link below.', 'trece-withdrawal-eu' ),
			$order
		);

		$link_text = __( 'Submit a withdrawal request', 'trece-withdrawal-eu' );

		echo '<div style="margin-bottom:24px;padding:16px;background:#f7f7f7;border:1px solid #e0e0e0;border-radius:4px;">';
		echo '<h3 style="margin:0 0 8px;font-size:16px;color:#333;">' . esc_html( $heading ) . '</h3>';
		echo '<p style="margin:0 0 12px;color:#555;font-size:14px;line-height:1.5;">' . esc_html( $body ) . '</p>';
		printf(
			'<a href="%1$s" rel="noopener nofollow" style="display:inline-block;padding:8px 16px;background:#0073aa;color:#fff;text-decoration:none;border-radius:3px;font-size:14px;">%2$s</a>',
			esc_url( $url ),
			esc_html( $link_text )
		);
		echo '</div>';
	}

	/**
	 * Render the withdrawal notice in plain-text format.
	 *
	 * @param string   $url   Withdrawal form URL.
	 * @param WC_Order $order Order object.
	 *
	 * @return void
	 */
	private function render_plain_text_notice( $url, $order ) {

		$heading = __( 'Right of Withdrawal', 'trece-withdrawal-eu' );
		$body    = __( 'Under EU consumer law, you have the right to withdraw from this purchase within the statutory withdrawal period. To exercise your right of withdrawal, please visit the following link:', 'trece-withdrawal-eu' );

		echo "\n\n";
		echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . "\n";
		echo esc_html( strtoupper( $heading ) ) . "\n";
		echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . "\n\n";
		echo esc_html( $body ) . "\n\n";
		echo esc_url( $url ) . "\n\n";
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Convert eligible order statuses to WC email IDs.
	 *
	 * @param array $eligible_statuses Order status slugs.
	 *
	 * @return array WC email IDs.
	 */
	private function get_eligible_email_ids( $eligible_statuses ) {

		$email_ids = array();

		foreach ( (array) $eligible_statuses as $status ) {
			$status = sanitize_text_field( $status );

			if ( isset( self::$status_email_map[ $status ] ) ) {
				$email_ids[] = self::$status_email_map[ $status ];
			}
		}

		return array_unique( $email_ids );
	}
}
