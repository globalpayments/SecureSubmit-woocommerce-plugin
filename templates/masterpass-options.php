<?php
if (!defined('ABSPATH')) {
    exit();
}
?>

<h3><?php esc_html_e('MasterPass', 'wc_securesubmit'); ?></h3>
<p><?php esc_html_e('MasterPass description', 'wc_securesubmit'); ?></p>
<?php if (in_array(get_woocommerce_currency(), array('USD'))): ?>
    <table class="form-table">
        <?php $this->generate_settings_html(); ?>
    </table>
<?php else: ?>
    <div class="inline error">
        <p>
            <strong><?php esc_html_e('Gateway Disabled', 'wc_securesubmit'); ?></strong>
            <?php echo esc_html('Choose US Dollars as your store currency to enable MasterPass.', 'wc_securesubmit'); ?>
        </p>
    </div>
<?php endif; ?>
