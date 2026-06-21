<?php
/**
 * Admin Settings page.
 *
 * Registers the top-level "Withdrawals" admin menu and the "Settings"
 * submenu. Uses the WordPress Settings API for all fields. Also provides
 * the entry point for sibling admin classes (Admin_Log, Admin_Export) to
 * attach their own submenus.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_Settings
 *
 * Builds the plugin settings page using the WordPress Settings API.
 * Sections: General, Deadline, Checkout Consents, Excluded Notices,
 * Trader Info (Annex I.B), Email, Advanced.
 *
 * @since 1.0.0
 */
class Trece_WDEU_Settings {

	/**
	 * Option name in wp_options.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'trece_wdeu_settings';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'trece-wdeu-settings';

	/**
	 * Menu capability.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Constructor.
	 *
	 * Hooks menu registration and Settings API initialisation.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/*
	|----------------------------------------------------------------------
	| Menu Registration
	|----------------------------------------------------------------------
	*/

	/**
	 * Register the "Settings" submenu under the existing "Withdrawals" parent.
	 *
	 * The top-level "Withdrawals" menu is registered by
	 * {@see Trece_WDEU_Admin_Log::register_menu()}; this class only attaches
	 * its submenu to that parent slug.
	 *
	 * @return void
	 */
	public function register_menus() {
		add_submenu_page(
			'trece-withdrawal-eu',
			__( 'Settings — Withdrawal EU Law', 'trece-withdrawal-eu' ),
			__( 'Settings', 'trece-withdrawal-eu' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/*
	|----------------------------------------------------------------------
	| Settings API Registration
	|----------------------------------------------------------------------
	*/

	/**
	 * Register the option, sections, and fields with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {

		register_setting(
			'trece_wdeu_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		// ── Sections ───────────────────────────────────────────────────
		$this->add_section( 'general', __( 'General', 'trece-withdrawal-eu' ) );
		$this->add_section( 'applicability', __( 'Applicability (Country)', 'trece-withdrawal-eu' ) );
		$this->add_section( 'deadline', __( 'Deadline', 'trece-withdrawal-eu' ) );
		$this->add_section( 'checkout_consents', __( 'Checkout Consents', 'trece-withdrawal-eu' ) );
		$this->add_section( 'excluded_notices', __( 'Excluded Notices', 'trece-withdrawal-eu' ) );
		$this->add_section( 'trader_info', __( 'Trader Info (Annex I.B)', 'trece-withdrawal-eu' ) );
		$this->add_section( 'email', __( 'Email', 'trece-withdrawal-eu' ) );
		$this->add_section( 'spam_protection', __( 'Spam Protection', 'trece-withdrawal-eu' ) );
		$this->add_section( 'advanced', __( 'Advanced', 'trece-withdrawal-eu' ) );

		// ── General ────────────────────────────────────────────────────
		$this->add_field( 'general', 'withdrawal_page_id', __( 'Withdrawal Page [MANDATORY]', 'trece-withdrawal-eu' ), 'render_page_dropdown' );

		// ── Applicability ──────────────────────────────────────────────
		$this->add_field( 'applicability', 'use_billing_country', __( 'Restrict by billing country [OPTIONAL]', 'trece-withdrawal-eu' ), 'render_checkbox_field' );
		$this->add_field( 'applicability', 'allowed_countries', __( 'Allowed countries [OPTIONAL]', 'trece-withdrawal-eu' ), 'render_countries_field' );

		// ── Deadline ───────────────────────────────────────────────────
		$this->add_field( 'deadline', 'deadline_days', __( 'Deadline Days [MANDATORY]', 'trece-withdrawal-eu' ), 'render_number_field' );
		$this->add_field( 'deadline', 'deadline_basis', __( 'Deadline Basis [RECOMMENDED]', 'trece-withdrawal-eu' ), 'render_deadline_basis_field' );
		$this->add_field( 'deadline', 'grace_days', __( 'Grace Days [OPTIONAL]', 'trece-withdrawal-eu' ), 'render_number_field' );

		// ── Checkout Consents ──────────────────────────────────────────
		$this->add_field( 'checkout_consents', 'consent_digital_text', __( 'Digital Content Consent Text [MANDATORY]', 'trece-withdrawal-eu' ), 'render_textarea_field' );
		$this->add_field( 'checkout_consents', 'consent_service_text', __( 'Service Early Performance Consent Text [MANDATORY]', 'trece-withdrawal-eu' ), 'render_textarea_field' );

		// ── Excluded Notices ───────────────────────────────────────────
		$this->add_field( 'excluded_notices', 'show_excluded_notices_on_products', __( 'Show Exclusion Notice on Product Page [RECOMMENDED]', 'trece-withdrawal-eu' ), 'render_checkbox_field' );
		$this->add_field( 'excluded_notices', 'excluded_notice_other_title', __( 'Other Exclusion Notice Title [RECOMMENDED]', 'trece-withdrawal-eu' ), 'render_text_field' );
		$this->add_field( 'excluded_notices', 'excluded_notice_other_body', __( 'Other Exclusion Notice Body [RECOMMENDED]', 'trece-withdrawal-eu' ), 'render_textarea_field' );

		// ── Trader Info ────────────────────────────────────────────────
		$this->add_field( 'trader_info', 'trader_name', __( 'Trader Name [MANDATORY]', 'trece-withdrawal-eu' ), 'render_text_field' );
		$this->add_field( 'trader_info', 'trader_address', __( 'Trader Address [MANDATORY]', 'trece-withdrawal-eu' ), 'render_textarea_field' );
		$this->add_field( 'trader_info', 'trader_email', __( 'Trader Email [MANDATORY]', 'trece-withdrawal-eu' ), 'render_email_field' );
		$this->add_field( 'trader_info', 'trader_phone', __( 'Trader Phone [OPTIONAL]', 'trece-withdrawal-eu' ), 'render_text_field' );
		$this->add_field( 'trader_info', 'trader_fax', __( 'Trader Fax [OPTIONAL]', 'trece-withdrawal-eu' ), 'render_text_field' );

		// ── Email ──────────────────────────────────────────────────────
		$this->add_field( 'email', 'admin_email', __( 'Admin Notification Email [MANDATORY]', 'trece-withdrawal-eu' ), 'render_email_field' );
		$this->add_field( 'email', 'show_in_emails', __( 'Show Withdrawal Link in Order Emails [RECOMMENDED]', 'trece-withdrawal-eu' ), 'render_checkbox_field' );

		// ── Spam Protection ────────────────────────────────────────────
		$this->add_field( 'spam_protection', 'spam_protection_altcha', __( 'Enable ALTCHA challenge on the public withdrawal form [RECOMMENDED]', 'trece-withdrawal-eu' ), 'render_checkbox_field' );

		// ── Advanced ───────────────────────────────────────────────────
		$this->add_field( 'advanced', 'eligible_statuses', __( 'Eligible Order Statuses [RECOMMENDED]', 'trece-withdrawal-eu' ), 'render_eligible_statuses_field' );
	}

	/*
	|----------------------------------------------------------------------
	| Page Rendering
	|----------------------------------------------------------------------
	*/

	/**
	 * Render the Settings page.
	 *
	 * @return void
	 */
	public function render_page() {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'trece-withdrawal-eu' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Withdrawal EU Law — Settings', 'trece-withdrawal-eu' ); ?></h1>

			<div class="notice notice-warning inline" style="margin-bottom:20px;">
				<p>
					<strong><?php echo esc_html__( 'Legal Disclaimer', 'trece-withdrawal-eu' ); ?>:</strong>
					<?php
					echo esc_html__(
						'This plugin provides technical tools to facilitate compliance with EU Directive 2023/2673. It does not constitute legal advice and does not guarantee legal compliance. Please consult a legal advisor.',
						'trece-withdrawal-eu'
					);
					?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'trece_wdeu_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/*
	|----------------------------------------------------------------------
	| Field Renderers
	|----------------------------------------------------------------------
	*/

	/**
	 * Render a text input field.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_text_field( $args ) {

		$value = $this->get_field_value( $args['field'] );
		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
	}

	/**
	 * Render an email input field.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_email_field( $args ) {

		$value = $this->get_field_value( $args['field'] );
		printf(
			'<input type="email" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
	}

	/**
	 * Render a number input field.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_number_field( $args ) {

		$value = $this->get_field_value( $args['field'] );
		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="0" step="1" class="small-text" />',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
	}

	/**
	 * Render a textarea field.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_textarea_field( $args ) {

		$value = $this->get_field_value( $args['field'] );
		printf(
			'<textarea id="%1$s" name="%2$s[%1$s]" rows="4" cols="60" class="large-text">%3$s</textarea>',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME ),
			esc_textarea( $value )
		);
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_checkbox_field( $args ) {

		$value = $this->get_field_value( $args['field'] );
		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME ),
			checked( $value, true, false ),
			esc_html__( 'Enabled', 'trece-withdrawal-eu' )
		);
	}

	/**
	 * Render the page dropdown for the withdrawal page selection.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_page_dropdown( $args ) {

		$value = absint( $this->get_field_value( $args['field'] ) );
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION_NAME . '[' . $args['field'] . ']',
				'id'                => $args['field'],
				'selected'          => $value,
				'show_option_none'  => __( '— Select a page —', 'trece-withdrawal-eu' ),
				'option_none_value' => 0,
			)
		);
	}

	/**
	 * Render the deadline basis dropdown.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_deadline_basis_field( $args ) {

		$value   = $this->get_field_value( $args['field'] );
		$options = array(
			'order_date'      => __( 'Order date', 'trece-withdrawal-eu' ),
			'completion_date' => __( 'Completion date', 'trece-withdrawal-eu' ),
		);

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME )
		);

		foreach ( $options as $option_value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Render the eligible WooCommerce order statuses multi-checkbox.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_eligible_statuses_field( $args ) {

		$saved_statuses = $this->get_field_value( $args['field'] );

		if ( ! is_array( $saved_statuses ) ) {
			$saved_statuses = array( 'processing', 'completed' );
		}

		$all_statuses = array(
			'pending'    => __( 'Pending payment', 'trece-withdrawal-eu' ),
			'processing' => __( 'Processing', 'trece-withdrawal-eu' ),
			'on-hold'    => __( 'On hold', 'trece-withdrawal-eu' ),
			'completed'  => __( 'Completed', 'trece-withdrawal-eu' ),
			'refunded'   => __( 'Refunded', 'trece-withdrawal-eu' ),
		);

		// Merge WooCommerce registered statuses if available.
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$wc_statuses = wc_get_order_statuses();

			foreach ( $wc_statuses as $slug => $label ) {
				$clean_slug = str_replace( 'wc-', '', $slug );

				if ( ! isset( $all_statuses[ $clean_slug ] ) ) {
					$all_statuses[ $clean_slug ] = $label;
				}
			}
		}

		echo '<fieldset>';

		foreach ( $all_statuses as $status_slug => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s /> %5$s</label>',
				esc_attr( self::OPTION_NAME ),
				esc_attr( $args['field'] ),
				esc_attr( $status_slug ),
				checked( in_array( $status_slug, $saved_statuses, true ), true, false ),
				esc_html( $label )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Render the allowed-countries multiple select.
	 *
	 * Defaults to the EU countries enabled in WooCommerce when nothing is saved.
	 *
	 * @param array $args Field arguments containing 'field' key.
	 *
	 * @return void
	 */
	public function render_countries_field( $args ) {

		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			echo '<p class="description">' . esc_html__( 'WooCommerce is required for country restrictions.', 'trece-withdrawal-eu' ) . '</p>';
			return;
		}

		$selected = $this->get_field_value( $args['field'] );

		if ( ! is_array( $selected ) || empty( $selected ) ) {
			$enabled  = WC()->countries->get_allowed_countries();
			$selected = array_intersect( array_keys( $enabled ), WC()->countries->get_european_union_countries() );
		}

		$countries = WC()->countries->get_countries();

		printf(
			'<select multiple size="10" id="%1$s" name="%2$s[%1$s][]" class="wc-enhanced-select" style="min-width:350px;">',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME )
		);

		foreach ( $countries as $code => $name ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $code ),
				selected( in_array( $code, (array) $selected, true ), true, false ),
				esc_html( $name )
			);
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Only orders billed to these countries are offered the withdrawal flow. Takes effect only when "Restrict by billing country" is enabled.', 'trece-withdrawal-eu' ) . '</p>';
	}

	/*
	|----------------------------------------------------------------------
	| Sanitisation
	|----------------------------------------------------------------------
	*/

	/**
	 * Sanitize the entire settings array before saving.
	 *
	 * @param array $input Raw form data.
	 *
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {

		$clean = array();

		// ── General ────────────────────────────────────────────────────
		$clean['withdrawal_page_id'] = isset( $input['withdrawal_page_id'] ) ? absint( $input['withdrawal_page_id'] ) : 0;

		// ── Applicability ──────────────────────────────────────────────
		$clean['use_billing_country'] = ! empty( $input['use_billing_country'] );

		if ( isset( $input['allowed_countries'] ) && is_array( $input['allowed_countries'] ) ) {
			$clean['allowed_countries'] = array_values( array_map( 'sanitize_text_field', $input['allowed_countries'] ) );
		} else {
			$clean['allowed_countries'] = array();
		}

		// ── Deadline ───────────────────────────────────────────────────
		$clean['deadline_days'] = isset( $input['deadline_days'] ) ? absint( $input['deadline_days'] ) : 14;

		$valid_basis            = array( 'order_date', 'completion_date' );
		$clean['deadline_basis'] = isset( $input['deadline_basis'] ) && in_array( $input['deadline_basis'], $valid_basis, true )
			? $input['deadline_basis']
			: 'order_date';

		$clean['grace_days'] = isset( $input['grace_days'] ) ? absint( $input['grace_days'] ) : 0;

		// ── Checkout Consents ──────────────────────────────────────────
		$clean['consent_digital_text'] = isset( $input['consent_digital_text'] )
			? sanitize_textarea_field( $input['consent_digital_text'] )
			: '';

		$clean['consent_service_text'] = isset( $input['consent_service_text'] )
			? sanitize_textarea_field( $input['consent_service_text'] )
			: '';

		// ── Excluded Notices ───────────────────────────────────────────
		$clean['show_excluded_notices_on_products'] = ! empty( $input['show_excluded_notices_on_products'] );

		$clean['excluded_notice_other_title'] = isset( $input['excluded_notice_other_title'] )
			? sanitize_text_field( $input['excluded_notice_other_title'] )
			: '';

		$clean['excluded_notice_other_body'] = isset( $input['excluded_notice_other_body'] )
			? sanitize_textarea_field( $input['excluded_notice_other_body'] )
			: '';

		// ── Trader Info ────────────────────────────────────────────────
		$clean['trader_name']    = isset( $input['trader_name'] ) ? sanitize_text_field( $input['trader_name'] ) : '';
		$clean['trader_address'] = isset( $input['trader_address'] ) ? sanitize_textarea_field( $input['trader_address'] ) : '';
		$clean['trader_email']   = isset( $input['trader_email'] ) ? sanitize_email( $input['trader_email'] ) : '';
		$clean['trader_phone']   = isset( $input['trader_phone'] ) ? sanitize_text_field( $input['trader_phone'] ) : '';
		$clean['trader_fax']     = isset( $input['trader_fax'] ) ? sanitize_text_field( $input['trader_fax'] ) : '';

		// ── Email ──────────────────────────────────────────────────────
		$clean['admin_email']    = isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : get_option( 'admin_email' );
		$clean['show_in_emails'] = ! empty( $input['show_in_emails'] );

		// ── Spam Protection ────────────────────────────────────────────
		$clean['spam_protection_altcha'] = ! empty( $input['spam_protection_altcha'] );

		// ── Advanced ───────────────────────────────────────────────────
		if ( isset( $input['eligible_statuses'] ) && is_array( $input['eligible_statuses'] ) ) {
			$clean['eligible_statuses'] = array_map( 'sanitize_text_field', $input['eligible_statuses'] );
		} else {
			$clean['eligible_statuses'] = array( 'processing', 'completed' );
		}

		return $clean;
	}

	/*
	|----------------------------------------------------------------------
	| Internal Helpers
	|----------------------------------------------------------------------
	*/

	/**
	 * Register a settings section.
	 *
	 * @param string $id    Section identifier.
	 * @param string $title Section display title.
	 *
	 * @return void
	 */
	private function add_section( $id, $title ) {

		add_settings_section(
			'trece_wdeu_section_' . $id,
			$title,
			'__return_null',
			self::PAGE_SLUG
		);
	}

	/**
	 * Register a settings field.
	 *
	 * @param string $section  Section identifier (without prefix).
	 * @param string $field    Field / option key.
	 * @param string $label    Human-readable label.
	 * @param string $callback Method name on this class for rendering.
	 *
	 * @return void
	 */
	private function add_field( $section, $field, $label, $callback ) {

		add_settings_field(
			'trece_wdeu_field_' . $field,
			$label,
			array( $this, $callback ),
			self::PAGE_SLUG,
			'trece_wdeu_section_' . $section,
			array( 'field' => $field )
		);
	}

	/**
	 * Retrieve the current value for a settings field.
	 *
	 * Falls back to the merged defaults from the plugin singleton.
	 *
	 * @param string $field The settings key.
	 *
	 * @return mixed
	 */
	private function get_field_value( $field ) {

		$settings = Trece_WDEU_Plugin::instance()->get_settings();

		return isset( $settings[ $field ] ) ? $settings[ $field ] : '';
	}
}
