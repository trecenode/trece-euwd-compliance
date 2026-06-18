<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Trece_WDEU_GDPR {
    public static function init() {
        add_filter('wp_privacy_personal_data_exporters', [__CLASS__, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [__CLASS__, 'register_eraser']);
        add_action('admin_init', [__CLASS__, 'add_privacy_policy_content']);
    }

    public static function register_exporter($exporters) {
        $exporters['trece_wdeu'] = [
            'exporter_friendly_name' => __('Withdrawal Requests', 'trece-withdrawal-eu'),
            'callback' => [__CLASS__, 'exporter_callback'],
        ];
        return $exporters;
    }

    public static function register_eraser($erasers) {
        $erasers['trece_wdeu'] = [
            'eraser_friendly_name' => __('Withdrawal Requests', 'trece-withdrawal-eu'),
            'callback' => [__CLASS__, 'eraser_callback'],
        ];
        return $erasers;
    }

    public static function exporter_callback($email_address, $page = 1) {
        $export_items = [];
        $withdrawals = Trece_WDEU_CPT::get_withdrawals_by_email($email_address);

        foreach ($withdrawals as $w) {
            $data = [
                ['name' => __('Request ID', 'trece-withdrawal-eu'), 'value' => $w['id']],
                ['name' => __('Order', 'trece-withdrawal-eu'), 'value' => $w['order_number']],
                ['name' => __('Date Submitted', 'trece-withdrawal-eu'), 'value' => $w['submitted_at']],
                ['name' => __('Scope', 'trece-withdrawal-eu'), 'value' => $w['scope']],
                ['name' => __('Status', 'trece-withdrawal-eu'), 'value' => $w['status']],
            ];
            $export_items[] = [
                'group_id' => 'trece_wdeu',
                'group_label' => __('Withdrawal Requests', 'trece-withdrawal-eu'),
                'item_id' => 'withdrawal-' . $w['id'],
                'data' => $data,
            ];
        }

        return [
            'data' => $export_items,
            'done' => true,
        ];
    }

    public static function eraser_callback($email_address, $page = 1) {
        $withdrawals = Trece_WDEU_CPT::get_withdrawals_by_email($email_address);
        $items_removed = false;
        $items_retained = false;
        $messages = [];

        foreach ($withdrawals as $w) {
            $id = $w['id'];
            update_post_meta($id, '_trece_wdeu_customer_name', 'Anonymized');
            update_post_meta($id, '_trece_wdeu_customer_email', 'deleted@anonymized.invalid');
            update_post_meta($id, '_trece_wdeu_ip_address', '');
            update_post_meta($id, '_trece_wdeu_user_agent', '');
            $items_removed = true;
        }

        return [
            'items_removed' => $items_removed,
            'items_retained' => $items_retained,
            'messages' => $messages,
            'done' => true,
        ];
    }

    public static function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) return;

        $content = '<p>' . __('When you request a withdrawal, we store your name, email address, order details, IP address, and browser user agent. This data is kept as durable proof of your request to comply with consumer protection laws (Directive 2023/2673).', 'trece-withdrawal-eu') . '</p>';
        wp_add_privacy_policy_content(__('Withdrawal Requests', 'trece-withdrawal-eu'), $content);
    }
}
