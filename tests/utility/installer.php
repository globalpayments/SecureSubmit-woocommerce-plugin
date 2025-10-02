<?php
/**
 * Test installer
 *
 * PHP version 5.2+
 *
 * @category Tests
 * @package  WC_Gateway_SecureSubmit
 * @author   Heartland <EntApp_DevPortal@e-hps.com>
 * @license  Custom <https://github.com/hps/heartland-woocommerce-plugin/blob/master/LICENSE.md>
 * @link     https://github.com/hps/heartland-woocommerce-plugin
 */

/**
 * Test installer
 *
 * @category Tests
 * @package  WC_Gateway_SecureSubmit
 * @author   Heartland <EntApp_DevPortal@e-hps.com>
 * @license  Custom <https://github.com/hps/heartland-woocommerce-plugin/blob/master/LICENSE.md>
 * @link     https://github.com/hps/heartland-woocommerce-plugin
 */
class WC_Gateway_SecureSubmit_Tests_Utility_Installer
{
    /**
     * Installs Secure Submit as a payment gateway for eCommerce
     *
     * @return false
     */
    public static function installSecureSubmit()
    {
        $instance = WC_Gateway_SecureSubmit::instance();
        $instance->enabled = 'yes';
        $instance->secret_key = 'skapi_cert_MTyMAQBiHVEAewvIzXVFcmUd2UcyBge_eCpaASUp0A'; #gitleaks:allow
        $instance->public_key = 'pkapi_cert_jKc1FtuyAydZhZfbB3';
    }
}
