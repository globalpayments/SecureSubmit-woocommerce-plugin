<?php
if (!defined('ABSPATH')) {
    exit();
}
?>

<?php $masterpass = WC_Gateway_SecureSubmit_MasterPass::instance(); ?>
<div class="securesubmit-connect-with-masterpass">
  <h2><?php esc_html_e('MasterPass', 'wc_securesubmit'); ?></h2>

  <?php $longAccessToken = get_user_meta(get_current_user_id(), '_masterpass_long_access_token', true); ?>
  <?php if ($longAccessToken): ?>
    <form method="POST">
        <?php wp_nonce_field('masterpass_remove_long_access_token'); ?>
        <input type="hidden" name="forget_masterpass" value="true">
        <input type="submit" value="Disconnect">
    </form>
  <?php else: ?>
    <button type="button"
            class="button"
            onclick="jQuery(this).attr('disabled', 'disabled').val('Processing'); jQuery(this).parents('form').submit(); return false;"
            id="securesubmit-connect-with-masterpass">
      <img src="https://www.mastercard.com/mc_us/wallet/img/en/US/mp_connect_with_button_034px.png"
             alt="Connect with MasterPass" />
    </button>

    <script data-cfasync="false" type="text/javascript">var wc_securesubmit_masterpass_params = {ajaxUrl: '<?php echo esc_html(admin_url('admin-ajax.php')); ?>'};</script>
    <?php if ('production' === $masterpass->environment): ?>
        <script data-cfasync="false" type="text/javascript" src="https://www.masterpass.com/lightbox/Switch/integration/MasterPass.client.js"></script>
    <?php else: ?>
        <script data-cfasync="false" type="text/javascript" src="https://sandbox.masterpass.com/lightbox/Switch/integration/MasterPass.client.js"></script>
    <?php endif;?>
    <script data-cfasync="false" type="text/javascript" src="<?php echo esc_html(plugins_url('assets/js/masterpass.js', dirname(__FILE__))); ?>"></script>
  <?php endif; ?>
</div>
