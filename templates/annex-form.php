<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="trece-wdeu-model-form">
    <p><em><?php esc_html_e('(Complete and return this form only if you wish to withdraw from the contract)', 'trece-withdrawal-eu'); ?></em></p>

    <p>— <?php esc_html_e('To', 'trece-withdrawal-eu'); ?>:<br>
    <strong><?php echo esc_html($trader['name']); ?></strong><br>
    <?php echo nl2br(esc_html($trader['address'])); ?><br>
    <?php if (!empty($trader['email'])) : ?><?php echo esc_html($trader['email']); ?><br><?php endif; ?>
    <?php if (!empty($trader['phone'])) : ?><?php echo esc_html($trader['phone']); ?><br><?php endif; ?>
    <?php if (!empty($trader['fax'])) : ?><?php echo esc_html($trader['fax']); ?><br><?php endif; ?>
    </p>

    <p>— <?php esc_html_e('I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*)/for the provision of the following service (*),', 'trece-withdrawal-eu'); ?></p>
    
    <p>_______________________________________________________</p>
    
    <p>— <?php esc_html_e('Ordered on (*)/received on (*),', 'trece-withdrawal-eu'); ?></p>
    
    <p>_______________________________________________________</p>

    <p>— <?php esc_html_e('Name of consumer(s),', 'trece-withdrawal-eu'); ?></p>
    
    <p>_______________________________________________________</p>

    <p>— <?php esc_html_e('Address of consumer(s),', 'trece-withdrawal-eu'); ?></p>
    
    <p>_______________________________________________________</p>

    <p>— <?php esc_html_e('Signature of consumer(s) (only if this form is notified on paper),', 'trece-withdrawal-eu'); ?></p>
    
    <p>_______________________________________________________</p>

    <p>— <?php esc_html_e('Date', 'trece-withdrawal-eu'); ?></p>
    
    <p>_______________________________________________________</p>

    <p><small><em>(*) <?php esc_html_e('Delete as appropriate.', 'trece-withdrawal-eu'); ?></em></small></p>
</div>
