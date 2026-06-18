<?php
/**
 * Uninstall handler for Withdrawal EU Law.
 *
 * Removes all data created by the plugin:
 * - All `trece_withdrawal` custom-post-type posts and their meta.
 * - The `trece_wdeu_settings` option.
 * - The automatically-generated withdrawal page.
 *
 * @package Trece_Withdrawal_EU
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Remove the Withdrawal Page
|--------------------------------------------------------------------------
*/
$trece_wdeu_settings = get_option( 'trece_wdeu_settings', array() );

if ( ! empty( $trece_wdeu_settings['withdrawal_page_id'] ) ) {
	$page_id = absint( $trece_wdeu_settings['withdrawal_page_id'] );

	if ( $page_id && false !== get_post_status( $page_id ) ) {
		wp_delete_post( $page_id, true );
	}
}

/*
|--------------------------------------------------------------------------
| Remove All Withdrawal CPT Posts and Meta
|--------------------------------------------------------------------------
*/
$trece_wdeu_posts = get_posts(
	array(
		'post_type'      => 'trece_withdrawal',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

if ( ! empty( $trece_wdeu_posts ) ) {
	foreach ( $trece_wdeu_posts as $trece_wdeu_post_id ) {
		wp_delete_post( $trece_wdeu_post_id, true );
	}
}

/*
|--------------------------------------------------------------------------
| Remove Plugin Options
|--------------------------------------------------------------------------
*/
delete_option( 'trece_wdeu_settings' );

/*
|--------------------------------------------------------------------------
| Flush Rewrite Rules
|--------------------------------------------------------------------------
*/
flush_rewrite_rules();
