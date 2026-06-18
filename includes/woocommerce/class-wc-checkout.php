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

		// Classic (shortcode) checkout.
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_consent_checkboxes' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_consent_checkboxes' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_consent_data' ), 10, 2 );

		// Block-based checkout (Store API) — WooCommerce 8.9+.
		add_action( 'woocommerce_init', array( $this, 'register_block_consent_fields' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_block_consent' ), 10, 2 );
		add_action( 'woocommerce_set_additional_field_value', array( $this, 'save_block_consent' ), 10, 4 );

		// Bust the cached consent-product index when catalog/category data changes.
		add_action( 'save_post_product', array( __CLASS__, 'clear_consent_product_ids_cache' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'clear_consent_product_ids_cache' ) );
		add_action( 'created_product_cat', array( __CLASS__, 'clear_consent_product_ids_cache' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'clear_consent_product_ids_cache' ) );
		add_action( 'delete_product_cat', array( __CLASS__, 'clear_consent_product_ids_cache' ) );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_consents_in_admin' ) );
	}

	/**
	 * Return the consent label texts (settings merged with defaults).
	 *
	 * @return array{digital_content:string,service_early:string}
	 */
	private function get_consent_texts() {

		$settings = get_option( 'trece_wdeu_settings', array() );

		$defaults = array(
			'consent_digital_text' => __( 'I consent to the immediate supply of digital content and acknowledge that I will lose my right of withdrawal.', 'trece-withdrawal-eu' ),
			'consent_service_text' => __( 'I request that the service begins during the withdrawal period and acknowledge that, should I withdraw, I will be liable for the proportionate cost of the service already provided.', 'trece-withdrawal-eu' ),
		);

		$settings = wp_parse_args( $settings, $defaults );

		return array(
			'digital_content' => apply_filters( 'trece_wdeu_consent_digital_text', $settings['consent_digital_text'] ),
			'service_early'   => apply_filters( 'trece_wdeu_consent_service_text', $settings['consent_service_text'] ),
		);
	}

	/**
	 * Persist a single captured consent onto an order (HPOS-compatible).
	 *
	 * Shared by the classic and block checkout paths. Does not save the order;
	 * the caller / WooCommerce persists it.
	 *
	 * @param WC_Order $order    Order object.
	 * @param string   $type     digital_content|service_early.
	 * @param bool     $accepted Whether the consent was accepted.
	 *
	 * @return void
	 */
	private function persist_consent( $order, $type, $accepted ) {

		$texts    = $this->get_consent_texts();
		$text     = isset( $texts[ $type ] ) ? $texts[ $type ] : '';
		$decision = $accepted ? 'yes' : 'no';

		$order->update_meta_data( '_trece_wdeu_consent_' . $type . '_text', sanitize_textarea_field( $text ) );
		$order->update_meta_data( '_trece_wdeu_consent_' . $type . '_accepted', $decision );
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
		do_action( 'trece_wdeu_consent_captured', $order->get_id(), $type, $decision );
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

		if ( ! self::country_in_scope( self::current_customer_country() ) ) {
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

		if ( ! self::country_in_scope( self::current_customer_country() ) ) {
			return;
		}

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

		$field_map = array(
			'digital_content' => 'trece_wdeu_consent_digital',
			'service_early'   => 'trece_wdeu_consent_service',
		);

		foreach ( $field_map as $type => $field_name ) {
			if ( ! in_array( $type, $types, true ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout nonce.
			$accepted = ! empty( $_POST[ $field_name ] );

			$this->persist_consent( $order, $type, $accepted );
		}
	}

	/* ------------------------------------------------------------------
	 * Block Checkout (Store API)
	 * ----------------------------------------------------------------*/

	/**
	 * Register consent checkboxes as additional checkout fields for the
	 * block-based checkout. No-op on WooCommerce versions without the API.
	 *
	 * The fields are shown only when the cart contains a product whose
	 * effective withdrawal status matches (digital_content / service_early),
	 * using a JSON-schema `hidden` rule evaluated client-side.
	 *
	 * @return void
	 */
	public function register_block_consent_fields() {

		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$texts = $this->get_consent_texts();
		$ids   = self::get_consent_product_ids();

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'trece-wdeu/consent-digital',
				'label'             => $texts['digital_content'],
				'optionalLabel'     => $texts['digital_content'],
				'location'          => 'order',
				'type'              => 'checkbox',
				'required'          => false,
				'hidden'            => self::cart_contains_schema( $ids['digital_content'] ),
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'trece-wdeu/consent-service',
				'label'             => $texts['service_early'],
				'optionalLabel'     => $texts['service_early'],
				'location'          => 'order',
				'type'              => 'checkbox',
				'required'          => false,
				'hidden'            => self::cart_contains_schema( $ids['service_early'] ),
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
			)
		);
	}

	/**
	 * Validate the mandatory digital-content consent on block checkout.
	 *
	 * Mirrors the classic `validate_consent_checkboxes()`: digital consent is
	 * required when the cart contains a digital_content product.
	 *
	 * @param WC_Order        $order   Draft order.
	 * @param WP_REST_Request $request Store API request.
	 *
	 * @return void
	 * @throws Exception When the required consent is missing.
	 */
	public function validate_block_consent( $order, $request ) {

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! self::country_in_scope( $order->get_billing_country() ) ) {
			return;
		}

		$ids = self::get_consent_product_ids();

		if ( ! $this->order_has_listed_product( $order, $ids['digital_content'] ) ) {
			return;
		}

		$fields = $request->get_param( 'additional_fields' );

		if ( empty( $fields['trece-wdeu/consent-digital'] ) ) {
			throw new Exception(
				esc_html__( 'You must consent to the digital content terms to proceed.', 'trece-withdrawal-eu' )
			);
		}
	}

	/**
	 * Persist a block-checkout consent field onto the order.
	 *
	 * @param string   $key   Field id.
	 * @param mixed    $value Submitted value.
	 * @param string   $group Field group.
	 * @param WC_Order $order Order object.
	 *
	 * @return void
	 */
	public function save_block_consent( $key, $value, $group, $order ) {

		$map = array(
			'trece-wdeu/consent-digital' => 'digital_content',
			'trece-wdeu/consent-service' => 'service_early',
		);

		if ( ! isset( $map[ $key ] ) || ! $order instanceof WC_Order ) {
			return;
		}

		$this->persist_consent( $order, $map[ $key ], ! empty( $value ) );
	}

	/**
	 * Build the `hidden` JSON-schema rule: hide the field unless the cart
	 * contains at least one of the given product / variation IDs.
	 *
	 * @param int[] $product_ids Product / variation IDs.
	 *
	 * @return array
	 */
	private static function cart_contains_schema( array $product_ids ) {

		return array(
			'cart' => array(
				'properties' => array(
					'items' => array(
						'not' => array(
							'contains' => array(
								'enum' => array_values( array_map( 'absint', $product_ids ) ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Whether an order contains at least one of the given product/variation IDs.
	 *
	 * @param WC_Order $order        Order object.
	 * @param int[]    $product_ids  Product / variation IDs.
	 *
	 * @return bool
	 */
	private function order_has_listed_product( $order, array $product_ids ) {

		if ( empty( $product_ids ) ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			$id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

			if ( in_array( (int) $id, array_map( 'absint', $product_ids ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Indexed list of product / variation IDs per consent-requiring status.
	 *
	 * Cached for a month; busted on product / category changes.
	 *
	 * ponytail: full published-catalog scan, cached monthly. If catalogs grow
	 * to tens of thousands of products, pre-filter by the status meta + flagged
	 * category terms before resolving.
	 *
	 * @return array{digital_content:int[],service_early:int[]}
	 */
	public static function get_consent_product_ids() {

		$cached = get_transient( 'trece_wdeu_consent_product_ids' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = array(
			'digital_content' => array(),
			'service_early'   => array(),
		);

		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $product_ids as $pid ) {
			$status = Trece_WDEU_WC_Product::get_product_withdrawal_status( $pid );

			if ( ! isset( $result[ $status ] ) ) {
				continue;
			}

			$result[ $status ][] = (int) $pid;

			// Variable products: the parent's status applies to every variation,
			// and block-cart items report the variation ID.
			$product = wc_get_product( $pid );

			if ( $product && $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$result[ $status ][] = (int) $variation_id;
				}
			}
		}

		set_transient( 'trece_wdeu_consent_product_ids', $result, MONTH_IN_SECONDS );

		return $result;
	}

	/**
	 * Clear the cached consent-product index.
	 *
	 * @return void
	 */
	public static function clear_consent_product_ids_cache() {

		delete_transient( 'trece_wdeu_consent_product_ids' );
	}

	/* ------------------------------------------------------------------
	 * Country Applicability
	 * ----------------------------------------------------------------*/

	/**
	 * Whether withdrawal availability is restricted by billing country.
	 *
	 * @return bool
	 */
	public static function country_scoping_enabled() {

		$settings = get_option( 'trece_wdeu_settings', array() );

		return ! empty( $settings['use_billing_country'] );
	}

	/**
	 * Allowed billing countries (uppercase ISO codes).
	 *
	 * Falls back to the EU countries enabled in WooCommerce when none are saved.
	 *
	 * @return string[]
	 */
	public static function get_allowed_countries() {

		$settings = get_option( 'trece_wdeu_settings', array() );
		$list     = isset( $settings['allowed_countries'] ) && is_array( $settings['allowed_countries'] )
			? $settings['allowed_countries']
			: array();

		if ( empty( $list ) && function_exists( 'WC' ) && WC()->countries ) {
			$enabled = WC()->countries->get_allowed_countries();
			$list    = array_intersect( array_keys( $enabled ), WC()->countries->get_european_union_countries() );
		}

		return array_values( array_map( 'strtoupper', (array) $list ) );
	}

	/**
	 * Whether a billing country is within the withdrawal scope.
	 *
	 * Returns true when country scoping is disabled (no restriction).
	 *
	 * @param string $country ISO country code.
	 *
	 * @return bool
	 */
	public static function country_in_scope( $country ) {

		if ( ! self::country_scoping_enabled() ) {
			return true;
		}

		$country = strtoupper( (string) $country );

		if ( '' === $country ) {
			return false;
		}

		return in_array( $country, self::get_allowed_countries(), true );
	}

	/**
	 * Billing country of the active checkout customer (cart context).
	 *
	 * @return string
	 */
	private static function current_customer_country() {

		if ( function_exists( 'WC' ) && WC()->customer ) {
			return (string) WC()->customer->get_billing_country();
		}

		return '';
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
