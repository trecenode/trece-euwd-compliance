<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<form method="post" action="" class="trece-wdeu-form trece-wdeu-step2">
    <input type="hidden" name="trece_wdeu_action" value="submit_withdrawal">
    <input type="hidden" name="trece_wdeu_step" value="2">
    <input type="hidden" name="trece_wdeu_token" value="<?php echo esc_attr( $token ); ?>">
    <?php wp_nonce_field( 'trece_wdeu_step2', 'trece_wdeu_nonce' ); ?>
    
    <h3><?php esc_html_e( 'Review your withdrawal request', 'trece-withdrawal-eu' ); ?></h3>

    <dl>
        <dt><?php esc_html_e( 'Full name', 'trece-withdrawal-eu' ); ?></dt>
        <dd><?php echo esc_html( $data['customer_name'] ); ?></dd>

        <dt><?php esc_html_e( 'Email address', 'trece-withdrawal-eu' ); ?></dt>
        <dd><?php echo esc_html( $data['customer_email'] ); ?></dd>

        <?php if ( ! empty( $data['order_number'] ) ) : ?>
            <dt><?php esc_html_e( 'Order number', 'trece-withdrawal-eu' ); ?></dt>
            <dd><?php echo esc_html( $data['order_number'] ); ?></dd>
        <?php endif; ?>

        <dt><?php esc_html_e( 'Order date', 'trece-withdrawal-eu' ); ?></dt>
        <dd><?php echo esc_html( $data['order_date'] ); ?></dd>

        <dt><?php esc_html_e( 'Scope', 'trece-withdrawal-eu' ); ?></dt>
        <dd><?php echo esc_html( $data['scope'] === 'full' ? __( 'Full order', 'trece-withdrawal-eu' ) : __( 'Partial order', 'trece-withdrawal-eu' ) ); ?></dd>
    </dl>

    <?php if ( is_array( $data['products'] ) ) : // Structured line items (WooCommerce order). ?>

        <?php if ( ! empty( $data['products'] ) ) : ?>
            <fieldset class="trece-wdeu-field">
                <legend><?php esc_html_e( 'Items to withdraw', 'trece-withdrawal-eu' ); ?></legend>
                <?php foreach ( $data['products'] as $item_name ) : ?>
                    <label style="display:block;">
                        <input type="checkbox" name="withdraw_items[]" value="<?php echo esc_attr( $item_name ); ?>" checked>
                        <?php echo esc_html( $item_name ); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>

        <?php if ( ! empty( $data['excluded_items'] ) && is_array( $data['excluded_items'] ) ) : ?>
            <div class="trece-wdeu-field">
                <strong><?php esc_html_e( 'Excluded from withdrawal (Art. 16)', 'trece-withdrawal-eu' ); ?></strong>
                <ul>
                    <?php foreach ( $data['excluded_items'] as $item_name ) : ?>
                        <li><?php echo esc_html( $item_name ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    <?php elseif ( $data['scope'] === 'partial' && ! empty( $data['products'] ) ) : // Free-text fallback. ?>
        <dl>
            <dt><?php esc_html_e( 'Products affected', 'trece-withdrawal-eu' ); ?></dt>
            <dd><?php echo nl2br( esc_html( $data['products'] ) ); ?></dd>
        </dl>
    <?php endif; ?>

    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
        <button type="submit" class="trece-wdeu-btn trece-wdeu-btn-primary">
            <?php esc_html_e( 'Confirm withdrawal', 'trece-withdrawal-eu' ); ?>
        </button>
        <a href="<?php echo esc_url( remove_query_arg( 'auto_withdraw' ) ); ?>" rel="noopener nofollow" class="trece-wdeu-btn trece-wdeu-btn-secondary">
            <?php esc_html_e( 'Back to edit', 'trece-withdrawal-eu' ); ?>
        </a>
    </div>
</form>
