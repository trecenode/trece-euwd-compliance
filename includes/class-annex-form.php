<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Trece_WDEU_Annex_Form {
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'handle_print_view']);
    }

    private static function get_trader_data() {
        $settings = Trece_WDEU_Plugin::instance()->get_settings();
        return apply_filters('trece_wdeu_annex_trader_data', [
            'name' => $settings['trader_name'] ?? '',
            'address' => $settings['trader_address'] ?? '',
            'email' => $settings['trader_email'] ?? '',
            'phone' => $settings['trader_phone'] ?? '',
            'fax' => $settings['trader_fax'] ?? ''
        ]);
    }

    public static function handle_print_view() {
        if (isset($_GET['print']) && $_GET['print'] === 'annex') {
            $trader = self::get_trader_data();

            echo '<!DOCTYPE html><html><head><title>Annex I.B</title>';
            echo '<style>body { font-family: sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }</style>';
            echo '</head><body onload="window.print()">';
            include TRECE_WDEU_PATH . 'templates/annex-form.php';
            echo '</body></html>';
            exit;
        }
    }

    public static function render() {
        $trader = self::get_trader_data();

        ob_start();
        echo '<details class="trece-wdeu-annex">';
        echo '<summary>' . esc_html__('Model withdrawal form', 'trece-withdrawal-eu') . '</summary>';
        echo '<div class="trece-wdeu-annex-content">';
        include TRECE_WDEU_PATH . 'templates/annex-form.php';
        $print_url = add_query_arg('print', 'annex');
        echo '<p><br><a href="'.esc_url($print_url).'" target="_blank" class="trece-wdeu-btn trece-wdeu-btn-secondary">' . esc_html__('Print Form', 'trece-withdrawal-eu') . '</a></p>';
        echo '</div></details>';
        return ob_get_clean();
    }
}
