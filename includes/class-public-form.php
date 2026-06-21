<?php
/**
 * Public Form Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trece_WDEU_Public_Form {

	/**
	 * Token for the validated review payload (step 1 → step 2 transition).
	 *
	 * Set by handle_submission() ONLY after step-1 validation succeeds, then
	 * consumed by render() to display the review step. Never populated from
	 * unvalidated request input.
	 *
	 * @var string
	 */
	private static $review_token = '';

	public static function init() {
		add_shortcode( 'trece_withdrawal_form', [ __CLASS__, 'render' ] );
		add_action( 'init', [ __CLASS__, 'handle_submission' ] );
	}

	public static function render() {
		$step = isset( $_POST['trece_wdeu_step'] ) ? intval( $_POST['trece_wdeu_step'] ) : 1;
		$errors = get_transient( 'trece_wdeu_errors_' . session_id() );
		if ( ! $errors ) {
			$errors = [];
		} else {
			delete_transient( 'trece_wdeu_errors_' . session_id() );
		}

		ob_start();

		if ( isset( $_GET['success'] ) && isset( $_GET['id'] ) && isset( $_GET['hash'] ) ) {
			$withdrawal_id = absint( $_GET['id'] );
			$hash          = sanitize_text_field( $_GET['hash'] );
			
			// Get withdrawal data to show in success
			$data = Trece_WDEU_CPT::get_withdrawal( $withdrawal_id );
			
			include TRECE_WDEU_PATH . 'templates/public-form-success.php';
		} else {
            // Auto-withdraw fast path for logged-in customers and tokenized guests.
            if ( isset( $_GET['auto_withdraw'] ) && $_GET['auto_withdraw'] == '1' && $step === 1 && empty($errors) ) {
                $order_number = sanitize_text_field( $_GET['order_number'] ?? '' );
                $guest_token  = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
                if ( $order_number ) {
                    $order = self::resolve_order( $order_number );

                    if ( $order && self::can_auto_withdraw( $order, $guest_token ) ) {
                        $classified = Trece_WDEU_WC_Product::classify_order_items( $order );

                        $data = [
                            'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            'customer_email' => $order->get_billing_email(),
                            'order_number'   => $order_number,
                            'order_date'     => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : '',
                            'scope'          => empty( $classified['excluded'] ) ? 'full' : 'partial',
                            'products'       => $classified['withdrawable'],
                            'excluded_items' => $classified['excluded'],
                        ];
                        
                        $token = wp_generate_password( 32, false );
                        set_transient( 'trece_wdeu_token_' . $token, $data, 15 * MINUTE_IN_SECONDS );
                        
                        $step = 2; // Jump directly to step 2
                    }
                }
            }

            // Step 2 (review) renders ONLY from a server-side token minted after
            // validation — in handle_submission() step 1 (email-vs-order verified)
            // or the auto_withdraw fast path (ownership verified). Never rebuild
            // the payload from raw $_POST; the token is the capability that later
            // gates withdrawal creation in step 2 of handle_submission().
            $review_token = isset( $token ) ? $token : self::$review_token;

            if ( ! isset( $data ) && $review_token ) {
                $data = get_transient( 'trece_wdeu_token_' . $review_token );
            }

            if ( $step === 2 && empty( $errors ) && $review_token && ! empty( $data ) ) {
                $token = $review_token; // Consumed by the hidden field in the template.
                include TRECE_WDEU_PATH . 'templates/public-form-step2.php';
		} else {
			// Render step 1
			$settings       = Trece_WDEU_Plugin::instance()->get_settings();
			$prefill_order  = isset( $_GET['order_number'] ) ? sanitize_text_field( $_GET['order_number'] ) : '';
			$is_woocommerce = Trece_WDEU_Plugin::instance()->is_woocommerce_active();
			
			include TRECE_WDEU_PATH . 'templates/public-form-step1.php';
            
            echo Trece_WDEU_Annex_Form::render();
		}
		}

		return ob_get_clean();
	}

	/**
	 * Resolve a WooCommerce order from an order number / reference.
	 *
	 * @param string $order_number Order number or free-text reference.
	 *
	 * @return WC_Order|null Order object, or null if not found / WC inactive.
	 */
	private static function resolve_order( $order_number ) {
		if ( empty( $order_number ) || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order_id = apply_filters( 'trece_wdeu_resolve_order_number', 0, $order_number );
		if ( $order_id ) {
			return wc_get_order( $order_id ) ?: null;
		}

		$orders = wc_get_orders( [ 'limit' => 1, 'meta_key' => '_order_number', 'meta_value' => $order_number ] );
		if ( ! empty( $orders ) ) {
			return $orders[0];
		}

		return wc_get_order( $order_number ) ?: null;
	}

	/**
	 * Whether the current visitor may auto-start a withdrawal for an order.
	 *
	 * Logged-in customers must own the order; guests must present the order's
	 * tokenized email link (validated via hash_equals).
	 *
	 * @param WC_Order $order       Order object.
	 * @param string   $guest_token Token from the email link (guests only).
	 *
	 * @return bool
	 */
	private static function can_auto_withdraw( $order, $guest_token ) {

		$customer_id = (int) $order->get_customer_id();

		if ( $customer_id ) {
			return is_user_logged_in() && $customer_id === get_current_user_id();
		}

		if ( '' === $guest_token ) {
			return false;
		}

		$stored = (string) $order->get_meta( '_trece_wdeu_guest_token', true );

		return '' !== $stored && hash_equals( $stored, $guest_token );
	}

	public static function handle_submission() {
		if ( ! isset( $_POST['trece_wdeu_action'] ) || $_POST['trece_wdeu_action'] !== 'submit_withdrawal' ) {
			return;
		}

		if ( ! session_id() && ! headers_sent() ) {
			session_start();
		}

		$step = isset( $_POST['trece_wdeu_step'] ) ? intval( $_POST['trece_wdeu_step'] ) : 1;

		if ( $step === 1 ) {
			if ( ! isset( $_POST['trece_wdeu_nonce'] ) || ! wp_verify_nonce( $_POST['trece_wdeu_nonce'], 'trece_wdeu_step1' ) ) {
				return;
			}

			// Honeypot
			if ( ! empty( $_POST['trece_wdeu_website'] ) ) {
				wp_die( 'Spam detected' );
			}

			$errors = [];

			// ALTCHA spam protection — verified before any other validation
			// so a failed challenge never reveals which field would error.
			if ( class_exists( 'Trece_WDEU_Altcha' ) && Trece_WDEU_Altcha::is_enabled() ) {
				$altcha_payload = isset( $_POST['altcha'] ) ? wp_unslash( $_POST['altcha'] ) : '';
				if ( ! Trece_WDEU_Altcha::verify_solution( $altcha_payload ) ) {
					$errors[] = __( 'Spam check failed. Please reload the page and try again.', 'trece-withdrawal-eu' );
				}
			}

			if ( empty( $_POST['customer_name'] ) ) {
				$errors[] = __( 'Full name is required.', 'trece-withdrawal-eu' );
			}
			if ( empty( $_POST['customer_email'] ) || ! is_email( $_POST['customer_email'] ) ) {
				$errors[] = __( 'Valid email is required.', 'trece-withdrawal-eu' );
			}
			if ( empty( $_POST['order_date'] ) ) {
				$errors[] = __( 'Order date is required.', 'trece-withdrawal-eu' );
			}
			if ( empty( $_POST['privacy_policy'] ) ) {
				$errors[] = __( 'You must accept the privacy policy.', 'trece-withdrawal-eu' );
			}

			$is_wc = Trece_WDEU_Plugin::instance()->is_woocommerce_active();
			if ( $is_wc ) {
				if ( empty( $_POST['order_number'] ) ) {
					$errors[] = __( 'Order number is required.', 'trece-withdrawal-eu' );
				} else {
					$order_number = sanitize_text_field( $_POST['order_number'] );
					$email        = sanitize_email( $_POST['customer_email'] );
					
					$order = self::resolve_order( $order_number );

					if ( ! $order || strtolower( $order->get_billing_email() ) !== strtolower( $email ) ) {
						$errors[] = __( 'The email address does not match the order.', 'trece-withdrawal-eu' );
					} elseif ( class_exists( 'Trece_WDEU_WC_Checkout' ) && ! Trece_WDEU_WC_Checkout::country_in_scope( $order->get_billing_country() ) ) {
						$errors[] = __( 'Withdrawal requests are not available for this order.', 'trece-withdrawal-eu' );
					} else {
						// Check deadline
						$settings = Trece_WDEU_Plugin::instance()->get_settings();
						$basis    = $settings['deadline_basis'] ?? 'order_date';
						$days     = intval( $settings['deadline_days'] ?? 14 ) + intval( $settings['grace_days'] ?? 0 );
						$days     = apply_filters( 'trece_wdeu_withdrawal_deadline_days', $days );

						$start_date = null;
						if ( $basis === 'completion_date' ) {
							$start_date = $order->get_date_completed();
						} else {
							$start_date = $order->get_date_created();
						}

						if ( $start_date ) {
							$deadline = $start_date->getTimestamp() + ( $days * DAY_IN_SECONDS );
							if ( time() > $deadline ) {
								$errors[] = __( 'The withdrawal period has expired for this order.', 'trece-withdrawal-eu' );
							}
						} elseif ( $basis === 'completion_date' ) {
							// Order not completed yet, deadline hasn't started
						}
					}
				}
			}

			if ( ! empty( $errors ) ) {
				set_transient( 'trece_wdeu_errors_' . session_id(), $errors, 60 );
				// Let the form re-render with errors
				$_POST['trece_wdeu_step'] = 1;
			} else {
				// Step 1 valid → mint the review token from validated input only,
				// then advance to the review step on render. From here on, render()
				// and step 2 trust the token, never raw $_POST.
				$review_data  = self::build_review_data();
				$review_token = wp_generate_password( 32, false );
				set_transient( 'trece_wdeu_token_' . $review_token, $review_data, 15 * MINUTE_IN_SECONDS );

				self::$review_token       = $review_token;
				$_POST['trece_wdeu_step'] = 2;
			}

		} elseif ( $step === 2 ) {
			if ( ! isset( $_POST['trece_wdeu_nonce'] ) || ! wp_verify_nonce( $_POST['trece_wdeu_nonce'], 'trece_wdeu_step2' ) ) {
				return;
			}

			$token = sanitize_text_field( $_POST['trece_wdeu_token'] ?? '' );
			$data  = get_transient( 'trece_wdeu_token_' . $token );

			if ( ! $data ) {
				wp_die( __( 'Session expired. Please try again.', 'trece-withdrawal-eu' ) );
			}

			delete_transient( 'trece_wdeu_token_' . $token );

			// If the order's lines were classified (structured products array),
			// honour the customer's per-line selection from the review step.
			// The selection is intersected with the withdrawable lines stored in
			// the token so an excluded item can never be smuggled back in.
			if ( isset( $data['products'] ) && is_array( $data['products'] ) ) {
				$withdrawable = $data['products']; // Full set of eligible lines from the token.
				$excluded     = isset( $data['excluded_items'] ) && is_array( $data['excluded_items'] ) ? $data['excluded_items'] : array();

				$selected = isset( $_POST['withdraw_items'] ) && is_array( $_POST['withdraw_items'] )
					? array_map( 'sanitize_text_field', wp_unslash( $_POST['withdraw_items'] ) )
					: array();

				// Intersect with eligible lines so excluded items can't be added.
				// No selection posted → withdraw all eligible lines.
				$data['products'] = empty( $selected )
					? $withdrawable
					: array_values( array_intersect( $withdrawable, $selected ) );

				// Full only when nothing is excluded and every eligible line is withdrawn.
				$data['scope'] = ( empty( $excluded ) && count( $data['products'] ) === count( $withdrawable ) )
					? 'full'
					: 'partial';
			}

			// Determine the linked WooCommerce order, if any.
			$order       = Trece_WDEU_Plugin::instance()->is_woocommerce_active() ? self::resolve_order( $data['order_number'] ?? '' ) : null;
			$wc_order_id = $order ? $order->get_id() : 0;

			// Defence-in-depth: re-assert the email-vs-order ownership at create
			// time. The token is already single-use and validation-gated, but
			// this bounds the blast radius if a token were ever leaked via logs
			// or a shared link.
			if ( $order && ! empty( $data['customer_email'] )
				&& strtolower( $order->get_billing_email() ) !== strtolower( $data['customer_email'] ) ) {
				wp_die( esc_html__( 'Session expired. Please try again.', 'trece-withdrawal-eu' ) );
			}

			$submitted_at = current_time( 'mysql', true );
			
			$data['ip_address']   = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
			$data['user_agent']   = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );
			$data['submitted_at'] = $submitted_at;
			$data['status']       = 'pending';
			$data['wc_order_id']  = $wc_order_id;
			
			// Calc hash
			$hash = Trece_WDEU_Email_Service::calculate_hash( $data );
			$data['receipt_hash'] = $hash;

			$post_id = Trece_WDEU_CPT::create_withdrawal( $data );

			if ( $post_id ) {
				Trece_WDEU_Email_Service::send_receipt_email( $post_id );
				Trece_WDEU_Email_Service::send_admin_notification( $post_id );
				do_action( 'trece_wdeu_withdrawal_created', $post_id, $data );

				$redirect_url = add_query_arg( [
					'success' => 1,
					'id'      => $post_id,
					'hash'    => $hash,
				], wp_get_referer() );

				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Build the review-step payload from the (already validated) step-1 input.
	 *
	 * Only called after handle_submission() step 1 has verified the nonce,
	 * honeypot, required fields and — for WooCommerce — that the submitted
	 * email matches the order, the country is in scope and the deadline has
	 * not passed. For WC orders the free-text product field is replaced with
	 * the authoritative order lines classified into withdrawable / excluded.
	 *
	 * @return array Review payload stored against the session token.
	 */
	private static function build_review_data() {

		$data = [
			'customer_name'  => sanitize_text_field( $_POST['customer_name'] ?? '' ),
			'customer_email' => sanitize_email( $_POST['customer_email'] ?? '' ),
			'order_number'   => sanitize_text_field( $_POST['order_number'] ?? '' ),
			'order_date'     => sanitize_text_field( $_POST['order_date'] ?? '' ),
			'scope'          => sanitize_text_field( $_POST['scope'] ?? 'full' ),
			'products'       => sanitize_textarea_field( $_POST['products'] ?? '' ),
		];

		if ( Trece_WDEU_Plugin::instance()->is_woocommerce_active() && ! empty( $data['order_number'] ) ) {
			$order = self::resolve_order( $data['order_number'] );
			if ( $order ) {
				$classified             = Trece_WDEU_WC_Product::classify_order_items( $order );
				$data['products']       = $classified['withdrawable'];
				$data['excluded_items'] = $classified['excluded'];
				$data['scope']          = empty( $classified['excluded'] ) ? 'full' : 'partial';
			}
		}

		return $data;
	}
}
