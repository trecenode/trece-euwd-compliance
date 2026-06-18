<?php
/**
 * WooCommerce Checkout – Consent Checkboxes
 *
 * Implements checkout consent checkboxes per Art. 16(m) and Art. 14(4)(a)
 * of the EU Consumer Rights Directive.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_WC_Checkout
 *
 * Handles:
 * - Rendering consent checkboxes before the submit button.
 * - Validating that required consents are accepted.
 * - Persisting consent data as order meta (HPOS-compatible).
 * - Displaying captured consents in the admin order screen.
 */
class Trece_WDEU_WC_Checkout {

	/**
	 * Constructor – register hooks.
	 */
	public function __construct() {

		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_consent_checkboxes' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_consent_checkboxes' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_consent_data' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_consents_in_admin' ) );
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ----------------------------------------------------------------*/

	/**
	 * Render consent checkboxes before the Place Order button.
	 *
	 * One checkbox per applicable consent type found in the cart.
	 *
	 * @return void
	 */
	public function render_consent_checkboxes() {

		$types = $this->get_cart_withdrawal_types();

		if ( empty( $types ) ) {
			return;
		}

		$settings = get_option( 'trece_wdeu_settings', array() );

		$defaults = array(
			'consent_digital_text' => __( 'I consent to the immediate supply of digital content and acknowledge that I will lose my right of withdrawal.', 'trece-withdrawal-eu' ),
			'consent_service_text' => __( 'I request that the service begins during the withdrawal period and acknowledge that, should I withdraw, I will be liable for the proportionate cost of the service already provided.', 'trece-withdrawal-eu' ),
		);

		$settings = wp_parse_args( $settings, $defaults );

		echo '<div class="trece-wdeu-consent-checkboxes">';

		// Digital content consent – Art. 16(m).
		if ( in_array( 'digital_content', $types, true ) ) {
			$label = $settings['consent_digital_text'];

			/**
			 * Filter the digital-content consent label text.
			 *
			 * @since 1.0.0
			 *
			 * @param string $label Consent checkbox label.
			 */
			$label = apply_filters( 'trece_wdeu_consent_digital_text', $label );

			$this->render_checkbox(
				'trece_wdeu_consent_digital',
				$label,
				'trece-wdeu-consent-checkbox trece-wdeu-consent-digital'
			);
		}

		// Service early consent – Art. 14(4)(a).
		if ( in_array( 'service_early', $types, true ) ) {
			$label = $settings['consent_service_text'];

			/**
			 * Filter the service-early consent label text.
			 *
			 * @since 1.0.0
			 *
			 * @param string $label Consent checkbox label.
			 */
			$label = apply_filters( 'trece_wdeu_consent_service_text', $label );

			$this->render_checkbox(
				'trece_wdeu_consent_service',
				$label,
				'trece-wdeu-consent-checkbox trece-wdeu-consent-service'
			);
		}

		echo '</div>';
	}

	/**
	 * Render a single consent checkbox.
	 *
	 * @param string $name  Input field name.
	 * @param string $label Checkbox label text.
	 * @param string $class CSS class(es).
	 *
	 * @return void
	 */
	private function render_checkbox( $name, $label, $class ) {

		printf(
			'<p class="form-row %1$s">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input type="checkbox"
						   class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
						   name="%2$s"
						   id="%2$s"
						   value="1" />
					<span class="woocommerce-terms-and-conditions-checkbox-text">%3$s</span>
				</label>
			</p>',
			esc_attr( $class ),
			esc_attr( $name ),
			esc_html( $label )
		);
	}

	/* ------------------------------------------------------------------
	 * Validation
	 * ----------------------------------------------------------------*/

	/**
	 * Validate that required consent checkboxes are checked.
	 *
	 * Digital-content consent is MANDATORY – it blocks the order.
	 * Service-early consent is OPTIONAL.
	 *
	 * @return void
	 */
	public function validate_consent_checkboxes() {

		$types = $this->get_cart_withdrawal_types();

		if ( in_array( 'digital_content', $types, true ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce owns the checkout nonce.
			if ( empty( $_POST['trece_wdeu_consent_digital'] ) ) {
				wc_add_notice(
					__( 'You must consent to the digital content terms to proceed.', 'trece-withdrawal-eu' ),
					'error'
				);
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Persistence (HPOS-compatible)
	 * ----------------------------------------------------------------*/

	/**
	 * Save consent data as order meta when the order is created.
	 *
	 * @param WC_Order $order Order object (not yet saved).
	 * @param array    $data  Checkout posted data.
	 *
	 * @return void
	 */
	public function save_consent_data( $order, $data ) {

		$types = $this->get_cart_withdrawal_types();

		if ( empty( $types ) ) {
			return;
		}

		$settings = get_option( 'trece_wdeu_settings', array() );

		$defaults = array(
			'consent_digital_text' => __( 'I consent to the immediate supply of digital content and acknowledge that I will lose my right of withdrawal.', 'trece-withdrawal-eu' ),
			'consent_service_text' => __( 'I request that the service begins during the withdrawal period and acknowledge that, should I withdraw, I will be liable for the proportionate cost of the service already provided.', 'trece-withdrawal-eu' ),
		);

		$settings = wp_parse_args( $settings, $defaults );

		$consent_map = array(
			'digital_content' => array(
				'short' => 'digital',
				'text'  => $settings['consent_digital_text'],
			),
			'service_early'   => array(
				'short' => 'service',
				'text'  => $settings['consent_service_text'],
			),
		);

		foreach ( $consent_map as $type => $config ) {
			if ( ! in_array( $type, $types, true ) ) {
				continue;
			}

			$field_name = 'trece_wdeu_consent_' . $config['short'];

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout nonce.
			$accepted = ! empty( $_POST[ $field_name ] ) ? 'yes' : 'no';

			$order->update_meta_data( '_trece_wdeu_consent_' . $type . '_text', sanitize_textarea_field( $config['text'] ) );
			$order->update_meta_data( '_trece_wdeu_consent_' . $type . '_accepted', $accepted );
			$order->update_meta_data( '_trece_wdeu_consent_' . $type . '_timestamp', current_time( 'mysql', true ) );
			$order->update_meta_data(
				'_trece_wdeu_consent_' . $type . '_ip',
				sanitize_text_field( isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '' )
			);
			$order->update_meta_data(
				'_trece_wdeu_consent_' . $type . '_user_agent',
				sanitize_text_field( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' )
			);

			/**
			 * Fires after a consent is captured during checkout.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $order_id Order ID.
			 * @param string $type     Consent type (digital_content|service_early).
			 * @param string $accepted 'yes' or 'no'.
			 */
			do_action( 'trece_wdeu_consent_captured', $order->get_id(), $type, $accepted );
		}
	}

	/* ------------------------------------------------------------------
	 * Admin Display
	 * ----------------------------------------------------------------*/

	/**
	 * Display captured consents in the admin order edit screen.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return void
	 */
	public function display_consents_in_admin( $order ) {

		$consent_types = array(
			'digital_content' => __( 'Digital Content Consent (Art. 16(m))', 'trece-withdrawal-eu' ),
			'service_early'   => __( 'Service Early Consent (Art. 14(4)(a))', 'trece-withdrawal-eu' ),
		);

		$has_any = false;

		foreach ( $consent_types as $type => $title ) {
			$accepted = $order->get_meta( '_trece_wdeu_consent_' . $type . '_accepted', true );

			if ( '' === $accepted ) {
				continue;
			}

			if ( ! $has_any ) {
				echo '<div class="trece-wdeu-admin-consents" style="margin-top:16px;padding:12px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;">';
				echo '<h3 style="margin:0 0 10px;">' . esc_html__( 'Withdrawal Consents', 'trece-withdrawal-eu' ) . '</h3>';
				$has_any = true;
			}

			$text       = $order->get_meta( '_trece_wdeu_consent_' . $type . '_text', true );
			$timestamp  = $order->get_meta( '_trece_wdeu_consent_' . $type . '_timestamp', true );
			$ip         = $order->get_meta( '_trece_wdeu_consent_' . $type . '_ip', true );
			$user_agent = $order->get_meta( '_trece_wdeu_consent_' . $type . '_user_agent', true );

			$status_label = 'yes' === $accepted
				? '<span style="color:#46b450;font-weight:700;">' . esc_html__( 'Accepted', 'trece-withdrawal-eu' ) . '</span>'
				: '<span style="color:#dc3232;font-weight:700;">' . esc_html__( 'Declined', 'trece-withdrawal-eu' ) . '</span>';

			echo '<div class="trece-wdeu-consent-block" style="margin-bottom:10px;padding:8px;background:#fff;border:1px solid #eee;border-radius:3px;">';
			echo '<strong>' . esc_html( $title ) . '</strong><br />';
			echo '<em>' . esc_html( $text ) . '</em><br />';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $status_label is built with esc_html above.
			echo '<strong>' . esc_html__( 'Status:', 'trece-withdrawal-eu' ) . '</strong> ' . $status_label . '<br />';
			echo '<strong>' . esc_html__( 'Timestamp:', 'trece-withdrawal-eu' ) . '</strong> ' . esc_html( $timestamp ) . '<br />';
			echo '<strong>' . esc_html__( 'IP:', 'trece-withdrawal-eu' ) . '</strong> ' . esc_html( $ip ) . '<br />';
			echo '<strong>' . esc_html__( 'User Agent:', 'trece-withdrawal-eu' ) . '</strong> ' . esc_html( $user_agent );
			echo '</div>';
		}

		if ( $has_any ) {
			echo '</div>';
		}
	}

	/* ------------------------------------------------------------------
	 * Cart Helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Scan the current WooCommerce cart and return unique withdrawal types
	 * that require consent (digital_content, service_early).
	 *
	 * @return array List of consent-requiring withdrawal types found in the cart.
	 */
	private function get_cart_withdrawal_types() {

		if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
			return array();
		}

		$types = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = ! empty( $cart_item['variation_id'] )
				? $cart_item['variation_id']
				: $cart_item['product_id'];

			$status = Trece_WDEU_WC_Product::get_product_withdrawal_status( $product_id );

			if ( in_array( $status, array( 'digital_content', 'service_early' ), true ) ) {
				$types[] = $status;
			}
		}

		return array_unique( $types );
	}
}
