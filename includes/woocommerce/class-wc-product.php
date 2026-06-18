<?php
/**
 * WooCommerce Product & Category Fields
 *
 * Adds withdrawal-status metadata to products and product categories,
 * plus a front-end notice for excluded products.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_WC_Product
 *
 * Handles:
 * - Product-level withdrawal-status select (General tab).
 * - Category-level withdrawal-status select (add / edit term screens).
 * - Front-end excluded-product notice on single-product pages.
 */
class Trece_WDEU_WC_Product {

	/**
	 * Allowed withdrawal statuses and their labels.
	 *
	 * @var array
	 */
	private static $statuses = array();

	/**
	 * Constructor – register hooks.
	 */
	public function __construct() {

		// Product fields.
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_field' ) );

		// Category fields.
		add_action( 'product_cat_add_form_fields', array( $this, 'add_category_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_category_field' ) );
		add_action( 'created_product_cat', array( $this, 'save_category_field' ) );
		add_action( 'edited_product_cat', array( $this, 'save_category_field' ) );

		// Front-end excluded-product notice.
		add_action( 'woocommerce_single_product_summary', array( $this, 'show_excluded_notice' ), 25 );
	}

	/* ------------------------------------------------------------------
	 * Status Options Helper
	 * ----------------------------------------------------------------*/

	/**
	 * Return the available withdrawal-status options.
	 *
	 * @param bool $include_inherit Whether to prepend an "Inherit from parent" option.
	 *
	 * @return array Associative array value => label.
	 */
	public static function get_status_options( $include_inherit = false ) {

		$options = array(
			'standard'        => __( 'Standard (14-day right of withdrawal)', 'trece-withdrawal-eu' ),
			'digital_content' => __( 'Digital content (Art. 16(m))', 'trece-withdrawal-eu' ),
			'service_early'   => __( 'Service started early (Art. 14(4)(a))', 'trece-withdrawal-eu' ),
			'other_article16' => __( 'Other Article 16 exception', 'trece-withdrawal-eu' ),
		);

		if ( $include_inherit ) {
			$options = array( '' => __( 'Inherit from parent', 'trece-withdrawal-eu' ) ) + $options;
		}

		return $options;
	}

	/* ------------------------------------------------------------------
	 * Product Fields
	 * ----------------------------------------------------------------*/

	/**
	 * Render the withdrawal-status select in the General product-data tab.
	 *
	 * @return void
	 */
	public function add_product_field() {

		echo '<div class="options_group">';

		woocommerce_wp_select(
			array(
				'id'          => '_trece_wdeu_withdrawal_status',
				'label'       => __( 'Withdrawal status', 'trece-withdrawal-eu' ),
				'description' => __( 'Defines how the right of withdrawal applies to this product under EU consumer law.', 'trece-withdrawal-eu' ),
				'desc_tip'    => true,
				'options'     => self::get_status_options(),
			)
		);

		echo '</div>';

		// When "Virtual" is checked, default the withdrawal status to
		// "Digital content (Art. 16(m))" — still editable before saving.
		?>
		<script>
		jQuery( function ( $ ) {
			$( '#_virtual' ).on( 'change', function () {
				var $status = $( '#_trece_wdeu_withdrawal_status' );

				if ( this.checked && 'standard' === $status.val() ) {
					$status.val( 'digital_content' ).trigger( 'change' );
				}
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Save the product-level withdrawal-status meta.
	 *
	 * @param int $post_id Product (post) ID.
	 *
	 * @return void
	 */
	public function save_product_field( $post_id ) {

		if ( ! isset( $_POST['_trece_wdeu_withdrawal_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles the nonce.
			return;
		}

		$allowed = array_keys( self::get_status_options() );
		$value   = sanitize_text_field( wp_unslash( $_POST['_trece_wdeu_withdrawal_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( in_array( $value, $allowed, true ) ) {
			update_post_meta( $post_id, '_trece_wdeu_withdrawal_status', $value );
		}
	}

	/* ------------------------------------------------------------------
	 * Category Fields
	 * ----------------------------------------------------------------*/

	/**
	 * Render the field on the "Add new category" screen.
	 *
	 * @return void
	 */
	public function add_category_field() {

		$options = self::get_status_options( true );
		?>
		<div class="form-field">
			<label for="trece_wdeu_withdrawal_status">
				<?php esc_html_e( 'Withdrawal status', 'trece-withdrawal-eu' ); ?>
			</label>
			<select name="_trece_wdeu_withdrawal_status" id="trece_wdeu_withdrawal_status">
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>">
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php esc_html_e( 'Defines how the right of withdrawal applies to products in this category. Leave as "Inherit from parent" to use the parent category\'s value.', 'trece-withdrawal-eu' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the field on the "Edit category" screen.
	 *
	 * @param WP_Term $term Current term object.
	 *
	 * @return void
	 */
	public function edit_category_field( $term ) {

		$current = get_term_meta( $term->term_id, '_trece_wdeu_withdrawal_status', true );
		$options = self::get_status_options( true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="trece_wdeu_withdrawal_status">
					<?php esc_html_e( 'Withdrawal status', 'trece-withdrawal-eu' ); ?>
				</label>
			</th>
			<td>
				<select name="_trece_wdeu_withdrawal_status" id="trece_wdeu_withdrawal_status">
					<?php foreach ( $options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"
							<?php selected( $current, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Defines how the right of withdrawal applies to products in this category. Leave as "Inherit from parent" to use the parent category\'s value.', 'trece-withdrawal-eu' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save category withdrawal-status term meta.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return void
	 */
	public function save_category_field( $term_id ) {

		if ( ! isset( $_POST['_trece_wdeu_withdrawal_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP handles the nonce for term saves.
			return;
		}

		$allowed = array_keys( self::get_status_options( true ) );
		$value   = sanitize_text_field( wp_unslash( $_POST['_trece_wdeu_withdrawal_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( in_array( $value, $allowed, true ) ) {
			update_term_meta( $term_id, '_trece_wdeu_withdrawal_status', $value );
		} else {
			delete_term_meta( $term_id, '_trece_wdeu_withdrawal_status' );
		}
	}

	/* ------------------------------------------------------------------
	 * Front-end Excluded-Product Notice
	 * ----------------------------------------------------------------*/

	/**
	 * Show an informational notice on the single product page when the
	 * product is excluded from the right of withdrawal.
	 *
	 * @return void
	 */
	public function show_excluded_notice() {

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$status = self::get_product_withdrawal_status( $product->get_id() );

		// Only "other Article 16 exception" products get a product-page notice.
		// digital_content is handled via the mandatory checkout consent checkbox instead.
		if ( 'other_article16' !== $status ) {
			return;
		}

		$settings = Trece_WDEU_Plugin::instance()->get_settings();

		if ( empty( $settings['show_excluded_notices_on_products'] ) ) {
			return;
		}

		$title = $settings['excluded_notice_other_title'];
		$body  = $settings['excluded_notice_other_body'];

		$html = '<div class="trece-wdeu-excluded-notice">';
		$html .= '<strong>' . esc_html( $title ) . '</strong>';
		$html .= '<p>' . esc_html( $body ) . '</p>';
		$html .= '</div>';

		/**
		 * Filter the excluded-product notice HTML.
		 *
		 * @since 1.0.0
		 *
		 * @param string $html    Notice markup.
		 * @param string $status  Withdrawal status value.
		 * @param int    $product_id Product ID.
		 */
		$html = apply_filters( 'trece_wdeu_excluded_notice_html', $html, $status, $product->get_id() );

		// Enqueue CSS only when notice is shown.
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_enqueue_style(
			'trece-wdeu-product-notice',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/product-notice' . $suffix . '.css',
			array(),
			TRECE_WDEU_VERSION
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above; filterable.
	}

	/* ------------------------------------------------------------------
	 * Static Helper – Product Withdrawal Status Resolution
	 * ----------------------------------------------------------------*/

	/**
	 * Determine the effective withdrawal status for a product.
	 *
	 * Resolution order:
	 *  1. Product-level meta `_trece_wdeu_withdrawal_status`.
	 *  2. Direct product categories (term meta), then parent categories
	 *     walking up the hierarchy (inheritance).
	 *  3. Default: 'standard'.
	 *
	 * @param int $product_id Product (post) ID.
	 *
	 * @return string One of standard|digital_content|service_early|other_article16.
	 */
	public static function get_product_withdrawal_status( $product_id ) {

		// 1. Product-level meta.
		$status = get_post_meta( $product_id, '_trece_wdeu_withdrawal_status', true );

		if ( ! empty( $status ) ) {
			return $status;
		}

		// 2. Category-level meta with ancestor walk.
		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$cat_status = self::resolve_category_status( $term_id );

				if ( ! empty( $cat_status ) ) {
					return $cat_status;
				}
			}
		}

		// 3. Default.
		return 'standard';
	}

	/**
	 * Resolve withdrawal status from a category, walking up ancestor terms.
	 *
	 * @param int $term_id Term ID to start from.
	 *
	 * @return string|null Status value or null if none found.
	 */
	private static function resolve_category_status( $term_id ) {

		$status = get_term_meta( $term_id, '_trece_wdeu_withdrawal_status', true );

		if ( ! empty( $status ) ) {
			return $status;
		}

		// Walk up parent categories.
		$ancestors = get_ancestors( $term_id, 'product_cat', 'taxonomy' );

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor_status = get_term_meta( $ancestor_id, '_trece_wdeu_withdrawal_status', true );

			if ( ! empty( $ancestor_status ) ) {
				return $ancestor_status;
			}
		}

		return null;
	}
}
