<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php esc_html_e('Hello,', 'trece-withdrawal-eu'); ?>

<?php printf(esc_html__('A new withdrawal request (#%d) has been submitted on %s.', 'trece-withdrawal-eu'), $withdrawal['id'], $site_name); ?>

<?php esc_html_e('Customer details:', 'trece-withdrawal-eu'); ?>

- <?php esc_html_e('Name', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['customer_name']); ?>

- <?php esc_html_e('Email', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['customer_email']); ?>


<?php esc_html_e('Order details:', 'trece-withdrawal-eu'); ?>

- <?php esc_html_e('Order number', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['order_number'] ?? 'N/A'); ?>

- <?php esc_html_e('Scope', 'trece-withdrawal-eu'); ?>: <?php echo esc_html($withdrawal['scope']); ?>


<?php esc_html_e('You can review and process this request in the WordPress admin:', 'trece-withdrawal-eu'); ?>

<?php echo esc_url($admin_url); ?>
