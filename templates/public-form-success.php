<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="trece-wdeu-success">
    <h3><?php esc_html_e( 'Your withdrawal request has been submitted successfully.', 'trece-withdrawal-eu' ); ?></h3>
    <p><?php printf( esc_html__( 'Your request reference number is #%d.', 'trece-withdrawal-eu' ), $withdrawal_id ); ?></p>
    
    <div style="margin: 1.5rem 0;">
        <strong><?php esc_html_e( 'Receipt hash (SHA-256):', 'trece-withdrawal-eu' ); ?></strong><br>
        <div class="trece-wdeu-hash"><?php echo esc_html( $hash ); ?></div>
        <small><?php esc_html_e( 'This cryptographic hash ensures the integrity of your request. A confirmation has been sent to your email address.', 'trece-withdrawal-eu' ); ?></small>
    </div>

    <p><?php esc_html_e( 'We will process your request and issue the refund within 14 days of confirmation.', 'trece-withdrawal-eu' ); ?></p>
</div>
