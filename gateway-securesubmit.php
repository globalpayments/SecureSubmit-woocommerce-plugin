<?php
/*
Plugin Name: Global Payments SecureSubmit Gateway
Plugin URI: https://developer.heartlandpaymentsystems.com/SecureSubmit/
Description: Global Payments gateway for eCommerce.
Version: 4.0.0
WC tested up to: 9.8.1
Author: SecureSubmit
Author URI: https://developer.heartlandpaymentsystems.com/SecureSubmit/
*/

class HeartlandSecureSubmitGateway
{
    const SECURESUBMIT_GATEWAY_CLASS = 'WC_Gateway_SecureSubmit';

    public function __construct()
    {
        add_action('init', array($this, 'init'), 0);
        add_action('woocommerce_load', array($this, 'activate'));
        add_action('wp_enqueue_scripts', array($this, 'loadScripts'));
        add_action('admin_init', array($this, 'cleanupMasterPassSettings'));
    }

    public function init()
    {
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
            }
        });

        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        load_plugin_textdomain('wc_securesubmit', false, dirname(plugin_basename(__FILE__)) . '/languages');

        $this->loadClasses();

        $securesubmit = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS, 'instance'));
        add_filter('woocommerce_payment_gateways', array($this, 'addGateways'));
        add_action('woocommerce_after_my_account', array($this, 'savedCards'));
        add_action('woocommerce_order_actions', array($securesubmit->capture, 'addOrderAction'));
        add_action('woocommerce_order_action_' . $securesubmit->id . '_capture', array($securesubmit, 'process_capture'));

        // MasterPass - REMOVED: MasterPass has been deprecated and removed to prevent PHP 8.1+ warnings.
        // The program is migrating to Click to Pay in a future solution.
        // Legacy MasterPass settings are automatically cleaned up on plugin load.

        $giftCards         = new WC_Gateway_SecureSubmit_GiftCards;
        $giftCardPlacement = new giftCardOrderPlacement;

        if ($giftCards->allow_gift_cards) {
            add_filter('woocommerce_gateway_title',                   array($giftCards, 'update_gateway_title_checkout'), 10, 2);
            add_filter('woocommerce_gateway_description',             array($giftCards, 'update_gateway_description_checkout'), 10, 2);
            add_action('wp_head',                                     array($giftCards, 'set_ajax_url'));
            add_action('wp_ajax_nopriv_use_gift_card',                array($giftCards, 'applyGiftCard'));
            add_action('wp_ajax_use_gift_card',                       array($giftCards, 'applyGiftCard'));
            add_action('wp_ajax_nopriv_remove_gift_card',             array($giftCards, 'removeGiftCard'));
            add_action('wp_ajax_remove_gift_card',                    array($giftCards, 'removeGiftCard'));
            add_action('woocommerce_review_order_before_order_total', array($giftCards, 'addGiftCards'));
            add_action('woocommerce_cart_totals_before_order_total',  array($giftCards, 'addGiftCards'));
            add_filter('woocommerce_calculated_total',                array($giftCards, 'updateOrderTotal'), 10, 2);
            add_action('wp_enqueue_scripts',                          array($giftCards, 'removeGiftCardCode'));

            // Process checkout with gift cards
            add_filter('woocommerce_get_order_item_totals',    array($giftCardPlacement, 'addItemsToOrderDisplay'), PHP_INT_MAX, 2);
            add_action('woocommerce_checkout_order_processed', array($giftCardPlacement, 'processGiftCardsZeroTotal'), PHP_INT_MAX, 2);

            // Display gift cards used after checkout and on email
            add_filter('woocommerce_get_order_item_totals', array($giftCardPlacement, 'addItemsToPostOrderDisplay'), PHP_INT_MAX, 2);
        }
    }

    /**
     * Handle behaviors that only should occur at plugin activation.
     */
    public function activate()
    {
        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            return;
        }

        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        $this->loadClasses();
        // MasterPass order review page creation removed - deprecated
        // call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_MasterPass', 'createOrderReviewPage'));

        add_filter('woocommerce_payment_gateways', array($this, 'addGateway'));
        add_action('woocommerce_after_my_account', array($this, 'savedCards'));
    }

    /**
     * Adds payment options to WooCommerce to be enabled by store admin.
     *
     * @param array $methods
     *
     * @return array
     */
    public function addGateways($methods)
    {
        // MasterPass gateway removed - deprecated and moving to Click to Pay
        // $methods[] = self::SECURESUBMIT_GATEWAY_CLASS . '_MasterPass';

        if (class_exists('WC_Subscriptions_Order')) {
            $klass = self::SECURESUBMIT_GATEWAY_CLASS . '_Subscriptions';
            if (!function_exists('wcs_create_renewal_order')) {
                $klass .= '_Deprecated';
            }
            $methods[] = $klass;
        } else {
            $methods[] = self::SECURESUBMIT_GATEWAY_CLASS;
        }

        return $methods;
    }

    /**
     * Handles "Manage saved cards" interface to user.
     */
    public function savedCards()
    {
        $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false);

        if (!$cards) {
            return;
        }

        if (isset($_POST['delete_card']) && wp_verify_nonce($_POST['_wpnonce'], "secure_submit_del_card")) {
            $card = $cards[(int)$_POST['delete_card']];
            delete_user_meta(get_current_user_id(), '_secure_submit_card', $card);
            unset($cards[(int)$_POST['delete_card']]);
        }

        if (!$cards) {
            return;
        }

        $path = plugin_dir_path(__FILE__);
        include $path . 'templates/saved-cards.php';
    }

    public function loadScripts()
    {
        if (!is_account_page()) {
            return;
        }
        // SecureSubmit custom CSS
        wp_enqueue_style('woocommerce_securesubmit', plugins_url('assets/css/securesubmit.css', __FILE__), array(), '1.0');
    }

    protected function loadClasses()
    {
        include_once('classes/class-util.php');
        include_once('classes/class-wc-gateway-securesubmit.php');
        include_once('classes/class-wc-gateway-securesubmit-subscriptions.php');
        include_once('classes/class-wc-gateway-securesubmit-subscriptions-deprecated.php');
        // MasterPass class loading removed - deprecated
        // include_once('classes/class-wc-gateway-securesubmit-masterpass.php');
        include_once('classes/class-wc-gateway-securesubmit-giftcards.php');
        include_once('classes/class-giftcard-order-placement.php');
        include_once('classes/class-masterpass-removal-notice.php');
    }

    /**
     * Clean up legacy MasterPass settings to prevent errors and warnings.
     * This runs once after the MasterPass removal update.
     */
    public function cleanupMasterPassSettings()
    {
        // Check if cleanup has already been performed
        $cleanup_done = get_option('securesubmit_masterpass_cleanup_done', false);

        if ($cleanup_done) {
            return;
        }

        // Remove MasterPass gateway settings
        delete_option('woocommerce_securesubmit_masterpass_settings');

        // Remove any MasterPass-related user meta
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '%masterpass%'"
        );

        // Mark cleanup as done
        update_option('securesubmit_masterpass_cleanup_done', true);
    }
}
new HeartlandSecureSubmitGateway();
