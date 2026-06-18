<?php
/**
 * Template: My Account – Right of Withdrawal
 *
 * Displays a table of eligible orders with their withdrawal status
 * and action buttons within the WooCommerce My Account area.
 *
 * This template can be overridden by copying it to
 * yourtheme/trece-withdrawal-eu/myaccount-withdrawal.php
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 *
 * @var array $orders_data {
 *     Array of order data arrays.
 *
 *     @type WC_Order $order             Order object.
 *     @type int      $order_id          Order ID.
 *     @type string   $order_number      Display order number.
 *     @type WC_DateTime $order_date     Order creation date.
 *     @type string   $order_status      Order status slug.
 *     @type string   $order_url         URL to view the order.
 *     @type bool     $deadline_open     Whether the withdrawal deadline is still open.
 *     @type string|null $withdrawal_status Withdrawal request status or null.
 *     @type string   $withdrawal_url    URL to the withdrawal form or account page.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="trece-wdeu-myaccount">

	<?php if ( empty( $orders_data ) ) : ?>

		<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
			<?php esc_html_e( 'You have no orders eligible for withdrawal.', 'trece-withdrawal-eu' ); ?>
		</div>

	<?php else : ?>

		<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table trece-wdeu-orders-table">
			<thead>
				<tr>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number">
						<?php esc_html_e( 'Order', 'trece-withdrawal-eu' ); ?>
					</th>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date">
						<?php esc_html_e( 'Date', 'trece-withdrawal-eu' ); ?>
					</th>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status">
						<?php esc_html_e( 'Status', 'trece-withdrawal-eu' ); ?>
					</th>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-withdrawal">
						<?php esc_html_e( 'Withdrawal', 'trece-withdrawal-eu' ); ?>
					</th>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions">
						<?php esc_html_e( 'Action', 'trece-withdrawal-eu' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders_data as $data ) : ?>
					<tr class="woocommerce-orders-table__row order">
						<?php /* Order Number */ ?>
						<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number"
							data-title="<?php esc_attr_e( 'Order', 'trece-withdrawal-eu' ); ?>">
							<a href="<?php echo esc_url( $data['order_url'] ); ?>">
								<?php
								/* translators: %s: order number */
								echo esc_html( sprintf( __( '#%s', 'trece-withdrawal-eu' ), $data['order_number'] ) );
								?>
							</a>
						</td>

						<?php /* Date */ ?>
						<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date"
							data-title="<?php esc_attr_e( 'Date', 'trece-withdrawal-eu' ); ?>">
							<time datetime="<?php echo esc_attr( $data['order_date'] ? $data['order_date']->date( 'c' ) : '' ); ?>">
								<?php echo esc_html( $data['order_date'] ? wc_format_datetime( $data['order_date'] ) : '—' ); ?>
							</time>
						</td>

						<?php /* Order Status */ ?>
						<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status"
							data-title="<?php esc_attr_e( 'Status', 'trece-withdrawal-eu' ); ?>">
							<?php echo esc_html( wc_get_order_status_name( $data['order_status'] ) ); ?>
						</td>

						<?php /* Withdrawal Status */ ?>
						<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-withdrawal"
							data-title="<?php esc_attr_e( 'Withdrawal', 'trece-withdrawal-eu' ); ?>">
							<?php if ( $data['withdrawal_status'] ) : ?>
								<span class="trece-wdeu-status trece-wdeu-status--<?php echo esc_attr( $data['withdrawal_status'] ); ?>">
									<?php echo esc_html( ucfirst( $data['withdrawal_status'] ) ); ?>
								</span>
							<?php else : ?>
								<span class="trece-wdeu-status trece-wdeu-status--none">—</span>
							<?php endif; ?>
						</td>

						<?php /* Actions */ ?>
						<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions"
							data-title="<?php esc_attr_e( 'Action', 'trece-withdrawal-eu' ); ?>">
							<?php if ( $data['withdrawal_status'] ) : ?>
								<?php if ( ! empty( $data['withdrawal_url'] ) ) : ?>
									<a href="<?php echo esc_url( $data['withdrawal_url'] ); ?>"
									   class="woocommerce-button button trece-wdeu-btn trece-wdeu-btn--view">
										<?php esc_html_e( 'View request', 'trece-withdrawal-eu' ); ?>
									</a>
								<?php endif; ?>
							<?php elseif ( $data['deadline_open'] && ! empty( $data['withdrawal_url'] ) ) : ?>
								<a href="<?php echo esc_url( $data['withdrawal_url'] ); ?>"
								   class="woocommerce-button button trece-wdeu-btn trece-wdeu-btn--withdraw">
									<?php esc_html_e( 'Withdraw', 'trece-withdrawal-eu' ); ?>
								</a>
							<?php else : ?>
								<span class="trece-wdeu-deadline-expired">
									<?php esc_html_e( 'Deadline expired', 'trece-withdrawal-eu' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>

</div>
