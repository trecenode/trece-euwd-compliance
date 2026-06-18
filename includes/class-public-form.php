<?php
/**
 * Public Form Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trece_WDEU_Public_Form {

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
            // Auto-withdraw fast path for logged-in users
            if ( isset( $_GET['auto_withdraw'] ) && $_GET['auto_withdraw'] == '1' && is_user_logged_in() && $step === 1 && empty($errors) ) {
                $order_number = sanitize_text_field( $_GET['order_number'] ?? '' );
                if ( $order_number ) {
                    $order_id = apply_filters( 'trece_wdeu_resolve_order_number', 0, $order_number );
                    if ( ! $order_id ) {
                        $orders = wc_get_orders( ['limit' => 1, 'meta_key' => '_order_number', 'meta_value' => $order_number] );
                        $order = !empty($orders) ? $orders[0] : wc_get_order($order_number);
                    } else {
                        $order = wc_get_order($order_id);
                    }

                    if ( $order && $order->get_customer_id() == get_current_user_id() ) {
                        $data = [
                            'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            'customer_email' => $order->get_billing_email(),
                            'order_number'   => $order_number,
                            'order_date'     => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : '',
                            'scope'          => 'full',
                            'products'       => '',
                        ];
                        
                        $token = wp_generate_password( 32, false );
                        set_transient( 'trece_wdeu_token_' . $token, $data, 15 * MINUTE_IN_SECONDS );
                        
                        $step = 2; // Jump directly to step 2
                    }
                }
            }

            if ( $step === 2 && empty( $errors ) && ( isset( $_POST['trece_wdeu_nonce'] ) || isset( $token ) ) ) {
                // Render step 2
                if ( ! isset( $data ) ) {
                    $data = [
                        'customer_name'  => sanitize_text_field( $_POST['customer_name'] ?? '' ),
                        'customer_email' => sanitize_email( $_POST['customer_email'] ?? '' ),
                        'order_number'   => sanitize_text_field( $_POST['order_number'] ?? '' ),
                        'order_date'     => sanitize_text_field( $_POST['order_date'] ?? '' ),
                        'scope'          => sanitize_text_field( $_POST['scope'] ?? 'full' ),
                        'products'       => sanitize_textarea_field( $_POST['products'] ?? '' ),
                    ];
                    $token = wp_generate_password( 32, false );
                    set_transient( 'trece_wdeu_token_' . $token, $data, 15 * MINUTE_IN_SECONDS );
                }

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
					
					// Resolve order
					$order_id = apply_filters( 'trece_wdeu_resolve_order_number', 0, $order_number );
					if ( ! $order_id ) {
						$orders = wc_get_orders( [
							'limit'      => 1,
							'meta_key'   => '_order_number',
							'meta_value' => $order_number,
						] );
						if ( ! empty( $orders ) ) {
							$order = $orders[0];
						} else {
							$order = wc_get_order( $order_number );
						}
					} else {
						$order = wc_get_order( $order_id );
					}

					if ( ! $order || strtolower( $order->get_billing_email() ) !== strtolower( $email ) ) {
						$errors[] = __( 'The email address does not match the order.', 'trece-withdrawal-eu' );
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

			// Determine WC order ID if applicable
			$wc_order_id = 0;
			if ( Trece_WDEU_Plugin::instance()->is_woocommerce_active() && ! empty( $data['order_number'] ) ) {
				$order_id_resolved = apply_filters( 'trece_wdeu_resolve_order_number', 0, $data['order_number'] );
				if ( $order_id_resolved ) {
					$wc_order_id = $order_id_resolved;
				} else {
					$orders = wc_get_orders( [
						'limit'      => 1,
						'meta_key'   => '_order_number',
						'meta_value' => $data['order_number'],
					] );
					if ( ! empty( $orders ) ) {
						$wc_order_id = $orders[0]->get_id();
					} else {
						$order = wc_get_order( $data['order_number'] );
						if ( $order ) {
							$wc_order_id = $order->get_id();
						}
					}
				}
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
}
