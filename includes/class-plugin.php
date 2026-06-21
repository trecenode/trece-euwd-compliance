<?php
/**
 * Main plugin orchestrator.
 *
 * Implements the Singleton pattern, detects WooCommerce, loads every module
 * conditionally, and provides shared helpers consumed by the rest of the
 * plugin.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_Plugin
 *
 * Central bootstrap class for the Withdrawal EU Law plugin. Responsible for
 * loading all sub-modules at the correct hook priority and providing shared
 * utility methods such as settings retrieval and WooCommerce detection.
 *
 * @since 1.0.0
 */
class Trece_WDEU_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Trece_WDEU_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether WooCommerce is detected as active.
	 *
	 * @var bool
	 */
	private $woocommerce_active = false;

	/**
	 * Cached merged settings array.
	 *
	 * @var array|null
	 */
	private $settings_cache = null;

	/**
	 * Return the singleton instance, creating it on first call.
	 *
	 * @return Trece_WDEU_Plugin
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Private to enforce singleton usage. Hooks the main initialisation
	 * method to `plugins_loaded` so that WooCommerce has had a chance to
	 * load first.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 15 );
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \RuntimeException Always.
	 */
	public function __wakeup() {
		throw new \RuntimeException( esc_html__( 'Cannot unserialize singleton.', 'trece-withdrawal-eu' ) );
	}

	/*
	|----------------------------------------------------------------------
	| Initialisation
	|----------------------------------------------------------------------
	*/

	/**
	 * Detect WooCommerce and load every module.
	 *
	 * Fires on `plugins_loaded` at priority 15 so WooCommerce (priority 10)
	 * is already available.
	 *
	 * @return void
	 */
	public function init() {

		$this->woocommerce_active = class_exists( 'WooCommerce' );

		$this->load_always();

		if ( is_admin() ) {
			$this->load_admin();
		}

		if ( $this->woocommerce_active ) {
			$this->load_woocommerce();
		}

		// Conditional asset loading.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin_assets' ) );
	}

	/*
	|----------------------------------------------------------------------
	| Module Loaders
	|----------------------------------------------------------------------
	*/

	/**
	 * Load modules that are always required regardless of context.
	 *
	 * @return void
	 */
	private function load_always() {

		require_once TRECE_WDEU_PATH . 'includes/class-withdrawal-cpt.php';
		require_once TRECE_WDEU_PATH . 'includes/class-public-form.php';
		require_once TRECE_WDEU_PATH . 'includes/class-annex-form.php';
		require_once TRECE_WDEU_PATH . 'includes/class-shortcodes.php';
		require_once TRECE_WDEU_PATH . 'includes/class-email-service.php';
		require_once TRECE_WDEU_PATH . 'includes/class-gdpr.php';
		require_once TRECE_WDEU_PATH . 'includes/class-altcha.php';

		new Trece_WDEU_CPT();
		Trece_WDEU_Public_Form::init();
		Trece_WDEU_Annex_Form::init();
		Trece_WDEU_Shortcodes::init();
		Trece_WDEU_GDPR::init();
	}

	/**
	 * Load admin-only modules.
	 *
	 * @return void
	 */
	private function load_admin() {

		require_once TRECE_WDEU_PATH . 'includes/admin/class-admin-log.php';
		require_once TRECE_WDEU_PATH . 'includes/admin/class-admin-detail.php';
		require_once TRECE_WDEU_PATH . 'includes/admin/class-admin-export.php';
		require_once TRECE_WDEU_PATH . 'includes/admin/class-settings.php';

		new Trece_WDEU_Admin_Log();
		new Trece_WDEU_Admin_Detail();
		new Trece_WDEU_Admin_Export();
		new Trece_WDEU_Settings();
	}

	/**
	 * Load WooCommerce-specific modules.
	 *
	 * @return void
	 */
	private function load_woocommerce() {

		require_once TRECE_WDEU_PATH . 'includes/woocommerce/class-wc-checkout.php';
		require_once TRECE_WDEU_PATH . 'includes/woocommerce/class-wc-orders.php';
		require_once TRECE_WDEU_PATH . 'includes/woocommerce/class-wc-myaccount.php';
		require_once TRECE_WDEU_PATH . 'includes/woocommerce/class-wc-emails.php';
		require_once TRECE_WDEU_PATH . 'includes/woocommerce/class-wc-product.php';

		new Trece_WDEU_WC_Checkout();
		new Trece_WDEU_WC_Orders();
		new Trece_WDEU_WC_MyAccount();
		new Trece_WDEU_WC_Emails();
		new Trece_WDEU_WC_Product();
	}

	/*
	|----------------------------------------------------------------------
	| Asset Enqueueing
	|----------------------------------------------------------------------
	*/

	/**
	 * Conditionally enqueue front-end CSS and JS.
	 *
	 * Assets are loaded only on:
	 * - The designated withdrawal page.
	 * - Single-product pages that show an exclusion notice.
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets() {

		$settings = $this->get_settings();
		$load     = false;

		// Withdrawal page.
		if ( is_page( absint( $settings['withdrawal_page_id'] ) ) ) {
			$load = true;
		}

		// Single product pages (notices may appear).
		if ( ! $load && function_exists( 'is_product' ) && is_product() ) {
			$load = true;
		}

		if ( $load ) {
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			wp_enqueue_style(
				'trece-wdeu-public',
				TRECE_WDEU_URL . 'assets/css/public-form' . $suffix . '.css',
				array(),
				TRECE_WDEU_VERSION
			);

			wp_enqueue_script(
				'trece-wdeu-public',
				TRECE_WDEU_URL . 'assets/js/public-form' . $suffix . '.js',
				array(),
				TRECE_WDEU_VERSION,
				true
			);

			// ALTCHA widget, only on the withdrawal page when enabled.
			if ( is_page( absint( $settings['withdrawal_page_id'] ) )
				&& class_exists( 'Trece_WDEU_Altcha' )
				&& Trece_WDEU_Altcha::is_enabled() ) {
				wp_enqueue_script(
					'trece-wdeu-altcha',
					TRECE_WDEU_URL . 'assets/js/altcha.min.js',
					array(),
					TRECE_WDEU_VERSION,
					true
				);
			}
		}
	}

	/**
	 * Conditionally enqueue admin CSS and JS.
	 *
	 * Assets are loaded only on plugin admin screens.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function maybe_enqueue_admin_assets( $hook_suffix ) {

		// Only load on our own admin pages.
		$plugin_pages = array(
			'toplevel_page_trece-withdrawal-eu',
			'withdrawals_page_trece-wdeu-settings',
			'withdrawals_page_trece-wdeu-export',
			'withdrawals_page_trece-wdeu-detail',
		);

		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_style(
			'trece-wdeu-admin',
			TRECE_WDEU_URL . 'assets/css/admin' . $suffix . '.css',
			array(),
			TRECE_WDEU_VERSION
		);

		wp_enqueue_script(
			'trece-wdeu-admin',
			TRECE_WDEU_URL . 'assets/js/admin' . $suffix . '.js',
			array( 'jquery' ),
			TRECE_WDEU_VERSION,
			true
		);
	}

	/*
	|----------------------------------------------------------------------
	| Helpers
	|----------------------------------------------------------------------
	*/

	/**
	 * Return the merged plugin settings.
	 *
	 * The saved option array is merged over sensible defaults so that every
	 * key is always guaranteed to exist.
	 *
	 * @param bool $force_refresh Whether to bypass the internal cache.
	 *
	 * @return array
	 */
	public function get_settings( $force_refresh = false ) {

		if ( null !== $this->settings_cache && ! $force_refresh ) {
			return $this->settings_cache;
		}

		$defaults = array(
			'withdrawal_page_id'           => 0,
			'deadline_days'                => 14,
			'deadline_basis'               => 'order_date',
			'grace_days'                   => 0,
			'admin_email'                  => get_option( 'admin_email' ),
			'consent_digital_text'         => __(
				'I consent to the immediate supply of digital content and acknowledge that I will lose my right of withdrawal.',
				'trece-withdrawal-eu'
			),
			'consent_service_text'         => __(
				'I request that the service begins during the withdrawal period and acknowledge that, should I withdraw, I will be liable for the proportionate cost of the service already provided.',
				'trece-withdrawal-eu'
			),
			'excluded_notice_other_title'   => __( 'No right of withdrawal', 'trece-withdrawal-eu' ),
			'excluded_notice_other_body'    => __(
				'This product is exempt from the right of withdrawal under Article 16 of Directive 2011/83/EU.',
				'trece-withdrawal-eu'
			),
			'show_excluded_notices_on_products' => true,
			'trader_name'                  => '',
			'trader_address'               => '',
			'trader_email'                 => '',
			'trader_phone'                 => '',
			'trader_fax'                   => '',
			'eligible_statuses'            => array( 'processing', 'completed' ),
			'show_in_emails'               => true,
			'spam_protection_altcha'       => false,
		);

		$saved = get_option( 'trece_wdeu_settings', array() );

		$this->settings_cache = wp_parse_args( $saved, $defaults );

		return $this->settings_cache;
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return $this->woocommerce_active;
	}
}
