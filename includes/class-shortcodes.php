<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Trece_WDEU_Shortcodes {
    public static function init() {
        add_shortcode('trece_withdrawal_link', [__CLASS__, 'render_link']);
    }

    public static function render_link($atts) {
        $settings = Trece_WDEU_Plugin::instance()->get_settings();
        $page_id = $settings['withdrawal_page_id'] ?? 0;
        if (!$page_id) return '';

        $url = get_permalink($page_id);
        $text = apply_filters('trece_wdeu_link_text', __('Right of Withdrawal', 'trece-withdrawal-eu'));

        return sprintf(
            '<a href="%s" rel="noopener nofollow" class="trece-wdeu-link">%s</a>',
            esc_url($url),
            esc_html($text)
        );
    }
}
