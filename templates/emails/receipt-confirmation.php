<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php esc_html_e('Hello,', 'trece-withdrawal-eu'); ?>

<?php printf(esc_html__('We have received your request to withdraw from the contract with %s.', 'trece-withdrawal-eu'), $site_name); ?>

<?php esc_html_e('Below is the complete content of your declaration:', 'trece-withdrawal-eu'); ?>

------------------------------------------------------
<?php esc_html_e('Full name', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['customer_name']); ?>

<?php esc_html_e('Email address', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['customer_email']); ?>

<?php if (!empty($withdrawal['order_number'])) : ?>
<?php esc_html_e('Order number', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['order_number']); ?>
<?php endif; ?>

<?php esc_html_e('Order date', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['order_date']); ?>

<?php esc_html_e('Scope', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['scope']); ?>

<?php if ($withdrawal['scope'] === 'partial') : ?>
<?php esc_html_e('Products affected', 'trece-withdrawal-eu'); ?>:
<?php echo esc_html($withdrawal['products']); ?>
<?php endif; ?>

------------------------------------------------------

<?php esc_html_e('Date and time of submission (UTC)', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['submitted_at']); ?>

<?php esc_html_e('Receipt hash (SHA-256)', 'trece-withdrawal-eu'); ?>:
<?php echo esc_html($hash); ?>


<?php esc_html_e('This cryptographic hash is provided as a durable medium proof of your request. It can be recomputed from the exact data fields submitted if a dispute arises.', 'trece-withdrawal-eu'); ?>


<?php esc_html_e('We will process your request shortly. You will be notified when the status changes.', 'trece-withdrawal-eu'); ?>

<?php esc_html_e('Thank you,', 'trece-withdrawal-eu'); ?>

<?php echo esc_html($site_name); ?>
