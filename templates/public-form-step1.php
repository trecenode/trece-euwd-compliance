<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<h2 class="trece-wdeu-form-heading"><?php esc_html_e( 'Withdrawal Form', 'trece-withdrawal-eu' ); ?></h2>
<p class="trece-wdeu-form-intro">
    <?php esc_html_e( 'Use this form if you ordered without having registered with us. Otherwise login and use the button links to create you withdraw request.', 'trece-withdrawal-eu' ); ?>
</p>

<form method="post" action="" class="trece-wdeu-form trece-wdeu-step1">
    <input type="hidden" name="trece_wdeu_action" value="submit_withdrawal">
    <input type="hidden" name="trece_wdeu_step" value="1">
    <?php wp_nonce_field( 'trece_wdeu_step1', 'trece_wdeu_nonce' ); ?>
    
    <!-- Honeypot -->
    <div class="trece-wdeu-honeypot" style="display:none !important;">
        <label for="trece_wdeu_website">Website</label>
        <input type="text" id="trece_wdeu_website" name="trece_wdeu_website" value="">
    </div>

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="trece-wdeu-error-message">
            <ul>
                <?php foreach ( $errors as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="trece-wdeu-field trece-wdeu-field-required">
        <label for="customer_name"><?php esc_html_e( 'Full name', 'trece-withdrawal-eu' ); ?></label>
        <input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr( $_POST['customer_name'] ?? '' ); ?>" required>
    </div>

    <div class="trece-wdeu-field trece-wdeu-field-required">
        <label for="customer_email"><?php esc_html_e( 'Email address', 'trece-withdrawal-eu' ); ?></label>
        <input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr( $_POST['customer_email'] ?? '' ); ?>" required>
    </div>

    <?php if ( $is_woocommerce ) : ?>
        <div class="trece-wdeu-field trece-wdeu-field-required">
            <label for="order_number"><?php esc_html_e( 'Order number', 'trece-withdrawal-eu' ); ?></label>
            <input type="text" id="order_number" name="order_number" value="<?php echo esc_attr( $_POST['order_number'] ?? $prefill_order ); ?>" required>
        </div>
    <?php endif; ?>

    <div class="trece-wdeu-field trece-wdeu-field-required">
        <label for="order_date"><?php esc_html_e( 'Order date', 'trece-withdrawal-eu' ); ?></label>
        <input type="date" id="order_date" name="order_date" value="<?php echo esc_attr( $_POST['order_date'] ?? '' ); ?>" required>
    </div>

    <div class="trece-wdeu-field">
        <label><?php esc_html_e( 'Scope', 'trece-withdrawal-eu' ); ?></label>
        <div class="trece-wdeu-radio-group">
            <label>
                <input type="radio" name="scope" value="full" <?php checked( $_POST['scope'] ?? 'full', 'full' ); ?>>
                <?php esc_html_e( 'Full order', 'trece-withdrawal-eu' ); ?>
            </label>
            <label>
                <input type="radio" name="scope" value="partial" <?php checked( $_POST['scope'] ?? '', 'partial' ); ?>>
                <?php esc_html_e( 'Partial order', 'trece-withdrawal-eu' ); ?>
            </label>
        </div>
    </div>

    <div class="trece-wdeu-field" id="trece_wdeu_products_wrapper" style="<?php echo (isset($_POST['scope']) && $_POST['scope'] === 'partial') ? '' : 'display:none;'; ?>">
        <label for="products"><?php esc_html_e( 'Products affected', 'trece-withdrawal-eu' ); ?></label>
        <textarea id="products" name="products" rows="3"><?php echo esc_textarea( $_POST['products'] ?? '' ); ?></textarea>
    </div>

    <div class="trece-wdeu-checkbox trece-wdeu-field-required">
        <input type="checkbox" id="privacy_policy" name="privacy_policy" value="1" required>
        <label for="privacy_policy">
            <?php esc_html_e( 'I accept the privacy policy', 'trece-withdrawal-eu' ); ?>
        </label>
    </div>

    <?php if ( class_exists( 'Trece_WDEU_Altcha' ) && Trece_WDEU_Altcha::is_enabled() ) :
        $trece_wdeu_altcha_challenge = Trece_WDEU_Altcha::create_challenge();
        ?>
        <div class="trece-wdeu-field trece-wdeu-altcha">
            <altcha-widget
                auto="onsubmit"
                challenge="<?php echo esc_attr( wp_json_encode( $trece_wdeu_altcha_challenge ) ); ?>"
            ></altcha-widget>
        </div>
    <?php endif; ?>

    <button type="submit" class="trece-wdeu-btn trece-wdeu-btn-primary">
        <?php esc_html_e( 'Continue to review', 'trece-withdrawal-eu' ); ?>
    </button>
</form>
