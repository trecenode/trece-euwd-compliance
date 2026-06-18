<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="trece-wdeu-excluded-notice trece-wdeu-excluded-notice--<?php echo esc_attr( str_replace('_', '-', $type) ); ?>">
    <h4><?php echo esc_html( $title ); ?></h4>
    <p><?php echo nl2br( esc_html( $body ) ); ?></p>
</div>
