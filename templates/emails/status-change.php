<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php esc_html_e('Hello,', 'trece-withdrawal-eu'); ?>

<?php printf(esc_html__('The status of your withdrawal request (#%d) has been updated.', 'trece-withdrawal-eu'), $withdrawal['id']); ?>


<?php esc_html_e('New status:', 'trece-withdrawal-eu'); ?> <?php echo esc_html(strtoupper($new_status)); ?>


<?php if (!empty($comment)) : ?>
<?php esc_html_e('Message from the shop:', 'trece-withdrawal-eu'); ?>

<?php echo esc_html($comment); ?>

<?php endif; ?>

<?php esc_html_e('Thank you,', 'trece-withdrawal-eu'); ?>

<?php echo esc_html($site_name); ?>
