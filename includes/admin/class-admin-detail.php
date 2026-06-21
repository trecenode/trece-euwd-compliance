<?php
/**
 * Admin Detail – Single withdrawal request view.
 *
 * Renders a two-column metabox layout for inspecting and updating
 * an individual withdrawal request.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_Admin_Detail
 *
 * Handles the detail view (action=view) for a single withdrawal request,
 * including the status-update form with nonce verification.
 *
 * @since 1.0.0
 */
class Trece_WDEU_Admin_Detail {

	/**
	 * Constructor – register hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_status_update' ) );
	}

	/**
	 * Render the full detail page for a withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Withdrawal request (CPT) post ID.
	 */
	public function render_page( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'trece_withdrawal' !== $post->post_type ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>';
			esc_html_e( 'Withdrawal request not found.', 'trece-withdrawal-eu' );
			echo '</p></div></div>';
			return;
		}

		// Retrieve all relevant meta.
		$customer_name  = get_post_meta( $post_id, '_trece_wdeu_customer_name', true );
		$customer_email = get_post_meta( $post_id, '_trece_wdeu_customer_email', true );
		$order_number   = get_post_meta( $post_id, '_trece_wdeu_order_number', true );
		$order_date     = get_post_meta( $post_id, '_trece_wdeu_order_date', true );
		$scope          = get_post_meta( $post_id, '_trece_wdeu_scope', true );
		$products       = get_post_meta( $post_id, '_trece_wdeu_products', true );
		$ip_address     = get_post_meta( $post_id, '_trece_wdeu_ip_address', true );
		$user_agent     = get_post_meta( $post_id, '_trece_wdeu_user_agent', true );
		$submitted_at   = get_post_meta( $post_id, '_trece_wdeu_submitted_at', true );
		$receipt_hash   = get_post_meta( $post_id, '_trece_wdeu_receipt_hash', true );
		$status         = get_post_meta( $post_id, '_trece_wdeu_status', true );
		$status         = $status ? $status : 'pending';
		$resolved_at    = get_post_meta( $post_id, '_trece_wdeu_resolved_at', true );
		$admin_comment  = get_post_meta( $post_id, '_trece_wdeu_admin_comment', true );
		$email_sent     = get_post_meta( $post_id, '_trece_wdeu_email_sent', true );
		$email_sent_at  = get_post_meta( $post_id, '_trece_wdeu_email_sent_at', true );
		$wc_order_id    = get_post_meta( $post_id, '_trece_wdeu_wc_order_id', true );
		$excluded_items = get_post_meta( $post_id, '_trece_wdeu_excluded_items', true );

		// products / excluded_items are stored JSON-encoded — decode to arrays for display.
		if ( is_string( $products ) ) {
			$decoded  = json_decode( $products, true );
			$products = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $products;
		}
		if ( is_string( $excluded_items ) ) {
			$decoded        = json_decode( $excluded_items, true );
			$excluded_items = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $excluded_items;
		}

		$list_url = admin_url( 'admin.php?page=trece-withdrawal-eu' );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: %d: withdrawal request ID */
					esc_html__( 'Withdrawal Request #%d', 'trece-withdrawal-eu' ),
					absint( $post_id )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action">
				<?php esc_html_e( '&larr; Back to All Requests', 'trece-withdrawal-eu' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php settings_errors( 'trece_wdeu_detail' ); ?>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-1">

					<div id="post-body-content" style="position:relative;">

						<?php // Metabox: Customer Details. ?>
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Customer Details', 'trece-withdrawal-eu' ); ?></span></h2>
							<div class="inside">
								<table class="widefat fixed striped">
									<tbody>
										<tr>
											<th scope="row"><?php esc_html_e( 'Name', 'trece-withdrawal-eu' ); ?></th>
											<td><?php echo esc_html( $customer_name ); ?></td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Email', 'trece-withdrawal-eu' ); ?></th>
											<td>
												<a href="mailto:<?php echo esc_attr( $customer_email ); ?>">
													<?php echo esc_html( $customer_email ); ?>
												</a>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'IP Address', 'trece-withdrawal-eu' ); ?></th>
											<td><?php echo esc_html( $ip_address ); ?></td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'User Agent', 'trece-withdrawal-eu' ); ?></th>
											<td><code style="word-break:break-all;"><?php echo esc_html( $user_agent ); ?></code></td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>

						<?php // Metabox: Request Details. ?>
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Request Details', 'trece-withdrawal-eu' ); ?></span></h2>
							<div class="inside">
								<table class="widefat fixed striped">
									<tbody>
										<tr>
											<th scope="row"><?php esc_html_e( 'Order Number', 'trece-withdrawal-eu' ); ?></th>
											<td>
												<?php
												if ( $wc_order_id && function_exists( 'wc_get_order' ) ) {
													$edit_url = admin_url( 'post.php?post=' . absint( $wc_order_id ) . '&action=edit' );
													printf(
														'<a href="%s">#%s</a>',
														esc_url( $edit_url ),
														esc_html( $order_number )
													);
												} elseif ( $order_number ) {
													echo '#' . esc_html( $order_number );
												} else {
													echo '—';
												}
												?>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Order Date', 'trece-withdrawal-eu' ); ?></th>
											<td>
												<?php
												if ( $order_date ) {
													echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order_date ) ) );
												} else {
													echo '—';
												}
												?>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Scope', 'trece-withdrawal-eu' ); ?></th>
											<td><?php echo esc_html( ucfirst( $scope ) ); ?></td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Products Affected', 'trece-withdrawal-eu' ); ?></th>
											<td>
												<?php
												if ( is_array( $products ) && ! empty( $products ) ) {
													echo '<ul style="margin:0;">';
													foreach ( $products as $product ) {
														echo '<li>' . esc_html( $product ) . '</li>';
													}
													echo '</ul>';
												} elseif ( $products ) {
													echo esc_html( $products );
												} else {
													echo '—';
												}
												?>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Submitted At (UTC)', 'trece-withdrawal-eu' ); ?></th>
											<td>
												<?php
												if ( $submitted_at ) {
													echo esc_html( $submitted_at );
												} else {
													echo '—';
												}
												?>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Receipt Hash', 'trece-withdrawal-eu' ); ?></th>
											<td><code style="word-break:break-all;"><?php echo esc_html( $receipt_hash ); ?></code></td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>

						<?php // Metabox: Checkout Consents (only if WC order has consent data). ?>
						<?php $this->render_consents_metabox( $post_id, $wc_order_id ); ?>

						<?php // Metabox: Excluded Items (Art. 16). ?>
						<?php if ( ! empty( $excluded_items ) ) : ?>
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Excluded Items (Art. 16)', 'trece-withdrawal-eu' ); ?></span></h2>
							<div class="inside">
								<?php
								if ( is_array( $excluded_items ) ) {
									echo '<ul>';
									foreach ( $excluded_items as $item ) {
										echo '<li>' . esc_html( $item ) . '</li>';
									}
									echo '</ul>';
								} else {
									echo '<p>' . esc_html( $excluded_items ) . '</p>';
								}
								?>
							</div>
						</div>
						<?php endif; ?>

						<?php // Metabox: Activity Log (audit trail). ?>
						<?php
						$logs = Trece_WDEU_CPT::get_logs( $post_id );
						if ( ! empty( $logs ) ) :
							?>
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Activity Log', 'trece-withdrawal-eu' ); ?></span></h2>
							<div class="inside">
								<table class="widefat fixed striped">
									<thead>
										<tr>
											<th><?php esc_html_e( 'When (UTC)', 'trece-withdrawal-eu' ); ?></th>
											<th><?php esc_html_e( 'Event', 'trece-withdrawal-eu' ); ?></th>
											<th><?php esc_html_e( 'Actor', 'trece-withdrawal-eu' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( array_reverse( $logs ) as $log ) : ?>
											<tr>
												<td><?php echo esc_html( isset( $log['timestamp'] ) ? $log['timestamp'] : '' ); ?></td>
												<td>
													<?php echo esc_html( isset( $log['message'] ) ? $log['message'] : '' ); ?>
													<?php if ( ! empty( $log['payload']['comment'] ) ) : ?>
														<br /><em><?php echo esc_html( $log['payload']['comment'] ); ?></em>
													<?php endif; ?>
													<?php if ( ! empty( $log['payload']['ip'] ) ) : ?>
														<br /><small><?php echo esc_html__( 'IP:', 'trece-withdrawal-eu' ) . ' ' . esc_html( $log['payload']['ip'] ); ?></small>
													<?php endif; ?>
												</td>
												<td><?php echo esc_html( ucfirst( isset( $log['actor'] ) ? $log['actor'] : 'system' ) ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
						<?php endif; ?>

						<?php // Metabox: Status (with audit trail). ?>
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Status', 'trece-withdrawal-eu' ); ?></span></h2>
							<div class="inside">
								<p>
									<?php esc_html_e( 'Current status:', 'trece-withdrawal-eu' ); ?>
									<span class="trece-wdeu-status trece-wdeu-status-<?php echo esc_attr( $status ); ?>">
										<?php echo esc_html( ucfirst( $status ) ); ?>
									</span>
								</p>

								<form method="post" action="">
									<?php wp_nonce_field( 'trece_wdeu_update_status_' . $post_id, 'trece_wdeu_status_nonce' ); ?>
									<input type="hidden" name="trece_wdeu_post_id" value="<?php echo absint( $post_id ); ?>" />
									<input type="hidden" name="trece_wdeu_old_status" value="<?php echo esc_attr( $status ); ?>" />

									<table class="form-table" role="presentation">
										<tr>
											<th scope="row">
												<label for="trece-wdeu-new-status">
													<?php esc_html_e( 'New Status', 'trece-withdrawal-eu' ); ?>
												</label>
											</th>
											<td>
												<select id="trece-wdeu-new-status" name="trece_wdeu_new_status">
													<?php
													foreach ( array( 'pending', 'accepted', 'rejected', 'completed' ) as $s ) :
														?>
														<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>>
															<?php echo esc_html( ucfirst( $s ) ); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="trece-wdeu-admin-comment">
													<?php esc_html_e( 'Admin Comment', 'trece-withdrawal-eu' ); ?>
												</label>
											</th>
											<td>
												<textarea
													id="trece-wdeu-admin-comment"
													name="trece_wdeu_admin_comment"
													rows="4"
													class="large-text"
													placeholder="<?php esc_attr_e( 'Required when rejecting a request.', 'trece-withdrawal-eu' ); ?>"
												><?php echo esc_textarea( $admin_comment ); ?></textarea>
												<p class="description">
													<?php esc_html_e( 'A comment is required when rejecting a request and optional when completing.', 'trece-withdrawal-eu' ); ?>
												</p>
											</td>
										</tr>
									</table>

									<?php submit_button( __( 'Update Status', 'trece-withdrawal-eu' ), 'primary', 'trece_wdeu_update_status' ); ?>
								</form>

								<hr style="margin: 1.5em 0;" />

								<h3 style="margin-top: 0;"><?php esc_html_e( 'Audit Trail', 'trece-withdrawal-eu' ); ?></h3>
								<table class="widefat fixed striped">
									<tbody>
										<tr>
											<th scope="row"><?php esc_html_e( 'Submitted', 'trece-withdrawal-eu' ); ?></th>
											<td><?php echo esc_html( $submitted_at ? $submitted_at : '—' ); ?></td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Resolved', 'trece-withdrawal-eu' ); ?></th>
											<td><?php echo esc_html( $resolved_at ? $resolved_at : '—' ); ?></td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Email Sent', 'trece-withdrawal-eu' ); ?></th>
											<td>
												<?php
												if ( '1' === $email_sent ) {
													echo esc_html__( 'Yes', 'trece-withdrawal-eu' );
													if ( $email_sent_at ) {
														echo ' — ' . esc_html( $email_sent_at );
													}
												} else {
													esc_html_e( 'No', 'trece-withdrawal-eu' );
												}
												?>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>

					</div>

				</div><!-- #post-body -->
			</div><!-- #poststuff -->
		</div><!-- .wrap -->
		<?php
	}

	/**
	 * Render the Checkout Consents metabox.
	 *
	 * Only displays when the linked WC order holds consent meta
	 * (digital_content or service_early).
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id     Withdrawal post ID.
	 * @param int $wc_order_id WC order ID (may be empty).
	 */
	private function render_consents_metabox( $post_id, $wc_order_id ) {
		if ( ! $wc_order_id || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $wc_order_id );
		if ( ! $order ) {
			return;
		}

		$consent_types = array(
			'digital_content' => __( 'Digital Content Consent', 'trece-withdrawal-eu' ),
			'service_early'   => __( 'Early Service Commencement Consent', 'trece-withdrawal-eu' ),
		);

		$has_consents = false;
		$rows         = array();

		foreach ( $consent_types as $type => $label ) {
			$text      = $order->get_meta( '_trece_wdeu_consent_' . $type . '_text' );
			$accepted  = $order->get_meta( '_trece_wdeu_consent_' . $type . '_accepted' );
			$timestamp = $order->get_meta( '_trece_wdeu_consent_' . $type . '_timestamp' );
			$ip        = $order->get_meta( '_trece_wdeu_consent_' . $type . '_ip' );
			$ua        = $order->get_meta( '_trece_wdeu_consent_' . $type . '_user_agent' );

			if ( $text || $accepted || $timestamp ) {
				$has_consents = true;
				$rows[]       = array(
					'label'     => $label,
					'text'      => $text,
					'accepted'  => $accepted,
					'timestamp' => $timestamp,
					'ip'        => $ip,
					'ua'        => $ua,
				);
			}
		}

		if ( ! $has_consents ) {
			return;
		}

		?>
		<div class="postbox">
			<h2 class="hndle"><span><?php esc_html_e( 'Checkout Consents (Durable Proof)', 'trece-withdrawal-eu' ); ?></span></h2>
			<div class="inside">
				<?php foreach ( $rows as $row ) : ?>
					<div style="margin-bottom:16px;padding:12px;background:#f9f9f9;border-left:4px solid #0073aa;">
						<h4 style="margin:0 0 8px;"><?php echo esc_html( $row['label'] ); ?></h4>
						<table class="widefat fixed striped">
							<tbody>
								<tr>
									<th scope="row" style="width:140px;"><?php esc_html_e( 'Consent Text', 'trece-withdrawal-eu' ); ?></th>
									<td><em><?php echo esc_html( $row['text'] ); ?></em></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Decision', 'trece-withdrawal-eu' ); ?></th>
									<td>
										<?php
										if ( '1' === $row['accepted'] || 'yes' === $row['accepted'] ) {
											echo '<span class="trece-wdeu-status trece-wdeu-status-accepted">';
											esc_html_e( 'Accepted', 'trece-withdrawal-eu' );
											echo '</span>';
										} else {
											echo '<span class="trece-wdeu-status trece-wdeu-status-rejected">';
											esc_html_e( 'Declined', 'trece-withdrawal-eu' );
											echo '</span>';
										}
										?>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Timestamp', 'trece-withdrawal-eu' ); ?></th>
									<td><?php echo esc_html( $row['timestamp'] ? $row['timestamp'] : '—' ); ?></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'IP Address', 'trece-withdrawal-eu' ); ?></th>
									<td><?php echo esc_html( $row['ip'] ? $row['ip'] : '—' ); ?></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'User Agent', 'trece-withdrawal-eu' ); ?></th>
									<td><code style="word-break:break-all;"><?php echo esc_html( $row['ua'] ? $row['ua'] : '—' ); ?></code></td>
								</tr>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the status-update form submission.
	 *
	 * Validates the nonce, enforces capability checks, requires a comment
	 * when rejecting, persists the updated meta, fires the status-changed
	 * action, and sends the status-change email.
	 *
	 * @since 1.0.0
	 */
	public function handle_status_update() {
		if ( ! isset( $_POST['trece_wdeu_update_status'] ) ) {
			return;
		}

		$post_id = isset( $_POST['trece_wdeu_post_id'] ) ? absint( $_POST['trece_wdeu_post_id'] ) : 0;
		if ( ! $post_id ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['trece_wdeu_status_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trece_wdeu_status_nonce'] ) ), 'trece_wdeu_update_status_' . $post_id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'trece-withdrawal-eu' ) );
		}

		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'trece-withdrawal-eu' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'trece_withdrawal' !== $post->post_type ) {
			return;
		}

		$new_status = isset( $_POST['trece_wdeu_new_status'] )
			? sanitize_text_field( wp_unslash( $_POST['trece_wdeu_new_status'] ) )
			: '';

		if ( ! in_array( $new_status, array( 'pending', 'accepted', 'rejected', 'completed' ), true ) ) {
			add_settings_error(
				'trece_wdeu_detail',
				'trece_wdeu_invalid_status',
				__( 'Invalid status selected.', 'trece-withdrawal-eu' ),
				'error'
			);
			return;
		}

		$comment = isset( $_POST['trece_wdeu_admin_comment'] )
			? sanitize_textarea_field( wp_unslash( $_POST['trece_wdeu_admin_comment'] ) )
			: '';

		// Comment is REQUIRED when rejecting.
		if ( 'rejected' === $new_status && empty( trim( $comment ) ) ) {
			add_settings_error(
				'trece_wdeu_detail',
				'trece_wdeu_comment_required',
				__( 'A comment is required when rejecting a withdrawal request.', 'trece-withdrawal-eu' ),
				'error'
			);
			return;
		}

		$old_status = get_post_meta( $post_id, '_trece_wdeu_status', true );

		// Update meta.
		update_post_meta( $post_id, '_trece_wdeu_status', $new_status );
		update_post_meta( $post_id, '_trece_wdeu_resolved_at', current_time( 'mysql', true ) );

		if ( $comment ) {
			update_post_meta( $post_id, '_trece_wdeu_admin_comment', $comment );
		}

		/**
		 * Fires when a withdrawal request status is changed.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $post_id    Withdrawal post ID.
		 * @param string $new_status New status value.
		 * @param string $old_status Previous status value.
		 */
		do_action( 'trece_wdeu_status_changed', $post_id, $new_status, $old_status );

		// Audit trail.
		Trece_WDEU_CPT::add_log(
			$post_id,
			sprintf(
				/* translators: 1: old status, 2: new status */
				__( 'Status changed from "%1$s" to "%2$s".', 'trece-withdrawal-eu' ),
				$old_status ? $old_status : 'pending',
				$new_status
			),
			'status_' . $new_status,
			'admin',
			'' !== $comment ? array( 'comment' => $comment, 'user_id' => get_current_user_id() ) : array( 'user_id' => get_current_user_id() )
		);

		// Send email notification.
		if ( class_exists( 'Trece_WDEU_Email_Service' ) ) {
			$email_service = new Trece_WDEU_Email_Service();
			$email_service->send_status_change( $post_id, $new_status, $comment );
		}

		// Add WC order note if WooCommerce is active.
		$wc_order_id = get_post_meta( $post_id, '_trece_wdeu_wc_order_id', true );
		if ( $wc_order_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $wc_order_id );
			if ( $order ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: old status, 2: new status */
						__( 'Withdrawal request #%1$d status changed from "%2$s" to "%3$s".', 'trece-withdrawal-eu' ),
						$post_id,
						$old_status,
						$new_status
					)
				);
			}
		}

		// Redirect back with success message.
		$redirect_url = add_query_arg(
			array(
				'page'    => 'trece-withdrawal-eu',
				'action'  => 'view',
				'id'      => $post_id,
				'updated' => '1',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
