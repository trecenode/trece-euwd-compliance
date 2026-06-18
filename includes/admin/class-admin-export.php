<?php
/**
 * Admin Export – CSV export for withdrawal requests.
 *
 * Provides a filtered export page and streams a sanitised CSV file
 * with all relevant withdrawal data.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_Admin_Export
 *
 * Registers the "Export Withdrawals" submenu, renders the filter form,
 * and handles the CSV download with formula-injection protection.
 *
 * @since 1.0.0
 */
class Trece_WDEU_Admin_Export {

	/**
	 * Constructor – register hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ) );
		add_action( 'admin_init', array( $this, 'handle_export' ) );
	}

	/**
	 * Register the "Export Withdrawals" submenu page.
	 *
	 * @since 1.0.0
	 */
	public function register_submenu() {
		add_submenu_page(
			'trece-withdrawal-eu',
			__( 'Export Withdrawals', 'trece-withdrawal-eu' ),
			__( 'Export Withdrawals', 'trece-withdrawal-eu' ),
			'manage_options',
			'trece-wdeu-export',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the export page with filter form.
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export Withdrawal Requests', 'trece-withdrawal-eu' ); ?></h1>

			<div class="postbox" style="max-width:600px;margin-top:20px;">
				<div class="inside">
					<form method="post" action="">
						<?php wp_nonce_field( 'trece_wdeu_export', 'trece_wdeu_export_nonce' ); ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="trece-wdeu-export-status">
										<?php esc_html_e( 'Status', 'trece-withdrawal-eu' ); ?>
									</label>
								</th>
								<td>
									<select id="trece-wdeu-export-status" name="trece_wdeu_export_status">
										<option value=""><?php esc_html_e( 'All statuses', 'trece-withdrawal-eu' ); ?></option>
										<option value="pending"><?php esc_html_e( 'Pending', 'trece-withdrawal-eu' ); ?></option>
										<option value="accepted"><?php esc_html_e( 'Accepted', 'trece-withdrawal-eu' ); ?></option>
										<option value="rejected"><?php esc_html_e( 'Rejected', 'trece-withdrawal-eu' ); ?></option>
										<option value="completed"><?php esc_html_e( 'Completed', 'trece-withdrawal-eu' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="trece-wdeu-export-from">
										<?php esc_html_e( 'Date From', 'trece-withdrawal-eu' ); ?>
									</label>
								</th>
								<td>
									<input type="date" id="trece-wdeu-export-from" name="trece_wdeu_export_from" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="trece-wdeu-export-to">
										<?php esc_html_e( 'Date To', 'trece-withdrawal-eu' ); ?>
									</label>
								</th>
								<td>
									<input type="date" id="trece-wdeu-export-to" name="trece_wdeu_export_to" />
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'Export to CSV', 'trece-withdrawal-eu' ), 'primary', 'trece_wdeu_do_export' ); ?>
					</form>
				</div>
			</div>

			<div class="notice notice-info inline" style="max-width:600px;">
				<p>
					<?php esc_html_e( 'The exported CSV includes all meta fields and is protected against spreadsheet formula injection.', 'trece-withdrawal-eu' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the CSV export when the form is submitted.
	 *
	 * Runs on admin_init so headers can be sent before any output.
	 *
	 * @since 1.0.0
	 */
	public function handle_export() {
		if ( ! isset( $_POST['trece_wdeu_do_export'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['trece_wdeu_export_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trece_wdeu_export_nonce'] ) ), 'trece_wdeu_export' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'trece-withdrawal-eu' ) );
		}

		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export data.', 'trece-withdrawal-eu' ) );
		}

		// Build query arguments.
		$meta_query = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$status = isset( $_POST['trece_wdeu_export_status'] )
			? sanitize_text_field( wp_unslash( $_POST['trece_wdeu_export_status'] ) )
			: '';

		if ( $status && in_array( $status, array( 'pending', 'accepted', 'rejected', 'completed' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_trece_wdeu_status',
				'value' => $status,
			);
		}

		$date_from = isset( $_POST['trece_wdeu_export_from'] )
			? sanitize_text_field( wp_unslash( $_POST['trece_wdeu_export_from'] ) )
			: '';

		$date_to = isset( $_POST['trece_wdeu_export_to'] )
			? sanitize_text_field( wp_unslash( $_POST['trece_wdeu_export_to'] ) )
			: '';

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

		$query_args = array(
			'post_type'      => 'trece_withdrawal',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$query = new WP_Query( $query_args );

		// Set CSV headers.
		$filename = 'withdrawals-export-' . gmdate( 'Y-m-d-His' ) . '.csv';

		// Ensure no previous output.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// BOM for UTF-8 in Excel.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		// Header row.
		fputcsv(
			$output,
			array(
				'ID',
				'Customer Name',
				'Customer Email',
				'Order Number',
				'Order Date',
				'Scope',
				'Products',
				'Status',
				'Submitted At',
				'Resolved At',
				'Admin Comment',
				'Email Sent',
				'Email Sent At',
				'Receipt Hash',
				'IP Address',
				'Excluded Items',
			)
		);

		// Data rows.
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$pid = get_the_ID();

				$products       = get_post_meta( $pid, '_trece_wdeu_products', true );
				$excluded_items = get_post_meta( $pid, '_trece_wdeu_excluded_items', true );

				// Stored JSON-encoded — decode before flattening.
				if ( is_string( $products ) && '' !== $products ) {
					$decoded  = json_decode( $products, true );
					$products = is_array( $decoded ) ? $decoded : $products;
				}
				if ( is_string( $excluded_items ) && '' !== $excluded_items ) {
					$decoded        = json_decode( $excluded_items, true );
					$excluded_items = is_array( $decoded ) ? $decoded : $excluded_items;
				}

				// Flatten arrays for CSV.
				if ( is_array( $products ) ) {
					$products = implode( '; ', $products );
				}
				if ( is_array( $excluded_items ) ) {
					$excluded_items = implode( '; ', $excluded_items );
				}

				$email_sent_raw = get_post_meta( $pid, '_trece_wdeu_email_sent', true );

				$row = array(
					$pid,
					get_post_meta( $pid, '_trece_wdeu_customer_name', true ),
					get_post_meta( $pid, '_trece_wdeu_customer_email', true ),
					get_post_meta( $pid, '_trece_wdeu_order_number', true ),
					get_post_meta( $pid, '_trece_wdeu_order_date', true ),
					get_post_meta( $pid, '_trece_wdeu_scope', true ),
					$products,
					get_post_meta( $pid, '_trece_wdeu_status', true ),
					get_post_meta( $pid, '_trece_wdeu_submitted_at', true ),
					get_post_meta( $pid, '_trece_wdeu_resolved_at', true ),
					get_post_meta( $pid, '_trece_wdeu_admin_comment', true ),
					'1' === $email_sent_raw ? 'Yes' : 'No',
					get_post_meta( $pid, '_trece_wdeu_email_sent_at', true ),
					get_post_meta( $pid, '_trece_wdeu_receipt_hash', true ),
					get_post_meta( $pid, '_trece_wdeu_ip_address', true ),
					$excluded_items,
				);

				// Sanitize each cell against CSV/formula injection.
				$row = array_map( array( $this, 'sanitize_csv_cell' ), $row );

				fputcsv( $output, $row );
			}
			wp_reset_postdata();
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Sanitize a CSV cell value against formula injection.
	 *
	 * Prepends a single-quote to any cell whose first character is one of
	 * the known trigger characters for spreadsheet formulas.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Cell value.
	 * @return string Sanitized value.
	 */
	private function sanitize_csv_cell( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return $value;
		}

		$first_char      = $value[0];
		$dangerous_chars = array( '=', '+', '-', '@', "\t", "\r" );

		if ( in_array( $first_char, $dangerous_chars, true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}
}
