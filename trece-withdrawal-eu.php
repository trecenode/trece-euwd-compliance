<?php
/**
 * Plugin Name:       Withdrawal EU Law
 * Plugin URI:        https://13node.com/plugins/withdrawal-eu-law
 * Description:       Implements the EU Directive 2023/2673 electronic withdrawal function for WooCommerce stores. Provides a compliant withdrawal button, consent management, and automated confirmation emails.
 * Version:           1.0.0
 * Author:            13Node
 * Author URI:        https://13node.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       trece-withdrawal-eu
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.6
 *
 * @package Trece_Withdrawal_EU
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'TRECE_WDEU_VERSION', '1.0.0' );

/**
 * Plugin directory path (with trailing slash).
 *
 * @var string
 */
define( 'TRECE_WDEU_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL (with trailing slash).
 *
 * @var string
 */
define( 'TRECE_WDEU_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 *
 * @var string
 */
define( 'TRECE_WDEU_BASENAME', plugin_basename( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| HPOS Compatibility
|--------------------------------------------------------------------------
|
| Declare compatibility with WooCommerce High-Performance Order Storage.
|
*/
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/*
|--------------------------------------------------------------------------
| Activation Hook
|--------------------------------------------------------------------------
|
| Create the withdrawal page with shortcode, save settings, and flush
| rewrite rules on first activation.
|
*/
register_activation_hook( __FILE__, 'trece_wdeu_activate' );

/**
 * Plugin activation callback.
 *
 * Creates the withdrawal form page, stores its ID in the plugin settings,
 * and flushes rewrite rules so the CPT permalinks work immediately.
 *
 * @return void
 */
function trece_wdeu_activate() {

	$settings = get_option( 'trece_wdeu_settings', array() );

	// Only create the page if one has not already been assigned.
	$page_id = isset( $settings['withdrawal_page_id'] ) ? absint( $settings['withdrawal_page_id'] ) : 0;

	if ( ! $page_id || false === get_post_status( $page_id ) ) {

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Withdrawal Request', 'trece-withdrawal-eu' ),
				'post_content' => '<!-- wp:shortcode -->[trece_withdrawal_form]<!-- /wp:shortcode -->',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
			)
		);

		if ( ! is_wp_error( $page_id ) ) {
			$settings['withdrawal_page_id'] = $page_id;
			update_option( 'trece_wdeu_settings', $settings );
		}
	}

	// Flush rewrite rules so the CPT is accessible immediately.
	flush_rewrite_rules();
}

/*
|--------------------------------------------------------------------------
| Deactivation Hook
|--------------------------------------------------------------------------
*/
register_deactivation_hook( __FILE__, 'trece_wdeu_deactivate' );

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function trece_wdeu_deactivate() {
	flush_rewrite_rules();
}

/*
|--------------------------------------------------------------------------
| Load Text Domain
|--------------------------------------------------------------------------
*/
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain(
			'trece-withdrawal-eu',
			false,
			dirname( TRECE_WDEU_BASENAME ) . '/languages'
		);
	}
);

/*
|--------------------------------------------------------------------------
| Bootstrap the Plugin
|--------------------------------------------------------------------------
|
| Require the main plugin class and initialise the singleton instance.
|
*/
require_once TRECE_WDEU_PATH . 'includes/class-plugin.php';

Trece_WDEU_Plugin::instance();
