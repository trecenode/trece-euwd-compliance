<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Trece_WDEU_Email_Service {
    public static function send_receipt_email($withdrawal_id) {
        $withdrawal = Trece_WDEU_CPT::get_withdrawal($withdrawal_id);
        if (!$withdrawal) return;

        $site_name = get_bloginfo('name');
        $hash = $withdrawal['receipt_hash'];
        $to = $withdrawal['customer_email'];

        $subject = sprintf(__('Withdrawal confirmation — %s', 'trece-withdrawal-eu'), $site_name);
        
        ob_start();
        include TRECE_WDEU_PATH . 'templates/emails/receipt-confirmation.php';
        $message = ob_get_clean();

        $message = apply_filters('trece_wdeu_receipt_email_content', $message, $withdrawal, $hash);

        wp_mail($to, $subject, $message);

        update_post_meta($withdrawal_id, '_trece_wdeu_email_sent', 1);
        update_post_meta($withdrawal_id, '_trece_wdeu_email_sent_at', current_time('mysql', true));
    }

    public static function send_admin_notification($withdrawal_id) {
        $withdrawal = Trece_WDEU_CPT::get_withdrawal($withdrawal_id);
        if (!$withdrawal) return;

        $settings = Trece_WDEU_Plugin::instance()->get_settings();
        $to = !empty($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
        
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('New withdrawal request #%d — %s', 'trece-withdrawal-eu'), $withdrawal_id, $site_name);

        $reply_to = str_replace(["\r", "\n"], '', $withdrawal['customer_email']);
        $headers = ['Reply-To: ' . $reply_to];

        $admin_url = admin_url('admin.php?page=trece-withdrawal-eu&action=view&id=' . $withdrawal_id);

        ob_start();
        include TRECE_WDEU_PATH . 'templates/emails/admin-notification.php';
        $message = ob_get_clean();

        wp_mail($to, $subject, $message, $headers);
    }

    public static function send_status_change_email($withdrawal_id, $new_status, $comment = '') {
        $withdrawal = Trece_WDEU_CPT::get_withdrawal($withdrawal_id);
        if (!$withdrawal) return;

        $site_name = get_bloginfo('name');
        $to = $withdrawal['customer_email'];
        $subject = sprintf(__('Update on your withdrawal request #%d — %s', 'trece-withdrawal-eu'), $withdrawal_id, $site_name);

        ob_start();
        include TRECE_WDEU_PATH . 'templates/emails/status-change.php';
        $message = ob_get_clean();

        wp_mail($to, $subject, $message);
    }

    public static function calculate_hash($data) {
        $receipt_data = implode('|', [
            $data['customer_name'] ?? '',
            $data['customer_email'] ?? '',
            $data['order_number'] ?? '',
            $data['order_date'] ?? '',
            $data['scope'] ?? '',
            $data['products'] ?? '',
            $data['submitted_at'] ?? ''
        ]);
        return hash('sha256', $receipt_data);
    }
}
