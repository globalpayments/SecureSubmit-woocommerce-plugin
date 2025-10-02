<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

class WC_Gateway_SecureSubmit extends WC_Payment_Gateway
{
    private static $_alreadyRanChecks = false;
    private static $_instance = null;
    public $capture = null;
    public $payment = null;
    public $refund  = null;
    public $reverse = null;
    private $pluginVersion = '3.0.5';
    public $secret_key;
    public $public_key;
    public $custom_error;
    public $paymentaction;
    /**
     * 
     * @var null|string
     */
    public ?string $default_order_status;
    public $txndescriptor;
    public $enable_anti_fraud;
    public $fraud_address;
    public $allow_fraud;
    public $email_fraud;
    public $fraud_text;
    public $fraud_velocity_attempts;
    public $fraud_velocity_timeout;
    public $allow_card_saving;
    public $gift_card_title;
    public $allow_gift_cards;
    public $app_id;
    public $app_key;

    public function __construct()
    {
        // includes
        require_once 'includes/Hps.php';
        require_once 'wc-gateway-securesubmit/class-capture.php';
        require_once 'wc-gateway-securesubmit/class-payment.php';
        require_once 'wc-gateway-securesubmit/class-refund.php';
        require_once 'wc-gateway-securesubmit/class-reverse.php';

        // properties
        $this->id                      = 'securesubmit';
        $this->method_title            = __('SecureSubmit', 'wc_securesubmit');
        $this->icon                    = plugins_url('/assets/images/cards.png', dirname(__FILE__));
        $this->has_fields              = true;
        $this->initFormFields();
        $this->init_settings();
        $this->title                   = $this->getSetting('title');
        $this->description             = $this->getSetting('description');
        $this->enabled                 = $this->getSetting('enabled');
        $this->secret_key              = $this->getSetting('secret_key');
        $this->public_key              = $this->getSetting('public_key');
        $this->custom_error            = $this->getSetting('custom_error');
        $this->paymentaction           = $this->getSetting('paymentaction');
        $this->default_order_status    = $this->getSetting('default_order_status');
        $this->txndescriptor           = $this->getSetting('txndescriptor');
        $this->enable_anti_fraud       = ($this->getSetting('enable_anti_fraud') == 'yes' ? true : false);
        $this->allow_fraud             = $this->getSetting('allow_fraud');
        $this->email_fraud             = $this->getSetting('email_fraud');
        $this->fraud_address           = $this->getSetting('fraud_address');
        $this->fraud_text              = $this->getSetting('fraud_text');
        $this->fraud_velocity_attempts = $this->getSetting('fraud_velocity_attempts');
        $this->fraud_velocity_timeout  = $this->getSetting('fraud_velocity_timeout');
        $this->allow_card_saving       = ($this->getSetting('allow_card_saving') == 'yes' ? true : false);
        $this->allow_gift_cards        = ($this->getSetting('gift_cards') == 'yes' ? true : false);
        $this->gift_card_title         = $this->getSetting('gift_cards_gateway_title');
        $this->app_id                  = $this->getSetting('app_id');
        $this->app_key                 = $this->getSetting('app_key');
        $this->supports                = array(
                                            'products',
                                            'refunds'
                                         );

        // actions
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('admin_notices', array($this, 'checks'));
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('script_loader_tag', array($this, 'utf8'), 10, 2);

        // class references
        $this->capture = new WC_Gateway_SecureSubmit_Capture($this);
        $this->payment = new WC_Gateway_SecureSubmit_Payment($this);
        $this->refund  = new WC_Gateway_SecureSubmit_Refund($this);
        $this->reverse = new WC_Gateway_SecureSubmit_Reverse($this);
    }

    public static function instance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function utf8($tag, $handle)
    {
        if (!in_array($handle, array('securesubmit', 'woocommerce_securesubmit', 'hps_wc_securesubmit_library', 'woocommerce_securesubmit_removegiftcard')) || strpos($tag, 'utf-8') !== false) {
            return $tag;
        }
        return str_replace(' src', ' data-cfasync="false" charset="utf-8" src', $tag);
    }

    public function checks() : void
    {
        if (WC_Gateway_SecureSubmit::$_alreadyRanChecks || $this->getSetting('enabled') == 'no') 
            return;

        if (!$this->getSetting('secret_key')) {
            echo '<div class="error"><p>Heartland SecureSubmit Gateway error: Please enter your secret key</p></div>';
        } elseif (!$this->getSetting('public_key')) {
            echo '<div class="error"><p>Heartland SecureSubmit Gateway error: Please enter your public key</p></div>';
        }

        WC_Gateway_SecureSubmit::$_alreadyRanChecks = true;
        return;
    }

    public function is_available()
    {
        global $woocommerce;

        if ($this->enabled == "yes") {
            if ($woocommerce->version < '1.5.8') {
                return false;
            }

            // we will be adding more currencies in the near future, but today we are bound to USD
            if (!in_array(get_option('woocommerce_currency'), array('USD'))) {
                return false;
            }

            if (!$this->secret_key) {
                return false;
            }

            if (!$this->public_key) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function initFormFields()
    {
        $path = dirname(plugin_dir_path(__FILE__));
        $this->form_fields = include $path . '/etc/securesubmit-options.php';
    }

    public function admin_options()
    {
        $path = dirname(plugin_dir_path(__FILE__));
        include $path . '/templates/admin-options.php';
    }

    public function payment_fields()
    {
        $path = dirname(plugin_dir_path(__FILE__));
        include $path . '/templates/payment-fields.php';
    }

    // @codingStandardsIgnoreStart PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function payment_scripts()
    {
        // @codingStandardsIgnoreEnd PSR1.Methods.CamelCapsMethodName.NotCamelCaps
        if (!is_checkout() || is_wc_endpoint_url( 'order-received' )) {
            return;
        }

        $isCert = false !== strpos($this->public_key, '_cert_');
        $gpUrl = 'https://js.globalpay.com/v1/globalpayments.js';

        // SecureSubmit tokenization library
        wp_enqueue_script('gp_library', $gpUrl, array(), 'false', true);

        $isCert = false !== strpos($this->public_key, '_cert_');

        // SecureSubmit js controller for eCommerce
        wp_enqueue_script('woocommerce_securesubmit', plugins_url('assets/js/securesubmit.js', dirname(__FILE__)), array('jquery'), $this->pluginVersion, true);
        // SecureSubmit custom CSS
        wp_enqueue_style('woocommerce_securesubmit', plugins_url('assets/css/securesubmit.css', dirname(__FILE__)), array(), $this->pluginVersion);

        $securesubmit_params = array(
            'key'         => $this->public_key,
            'images_dir'  => $isCert ? 
                'https://js-cert.globalpay.com/v1/images' : 'https://js.globalpay.com/v1/images'
        );

        wp_localize_script('woocommerce_securesubmit', 'wc_securesubmit_params', $securesubmit_params);
    }

    public function process_payment($orderId)
    {
        if (!empty($this->app_key) && !empty($this->app_id)) $this->tryTransOptimization($orderId);
        
        return $this->payment->call($orderId);
    }

    public function process_capture($order)
    {
        $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $payment_action = wc_get_order($orderId)->get_meta('_heartland_order_payment_action');
        } else {
            $payment_action = get_post_meta($orderId, '_heartland_order_payment_action', true);
        }

        if ($payment_action != 'verify' && !$this->isTransactionActiveOnGateway($orderId)) {
            $this->displayUserError('Payment already captured');
            return;
        }
        return $this->capture->call($order);
    }

    public function process_refund($orderId, $amount = null, $reason = '')
    {
        if ($amount !== null) {
            $amount = wc_format_decimal($amount, 2);
        }
        if ($this->isTransactionActiveOnGateway($orderId)) {
            return $this->reverse->call($orderId, $amount, $reason);
        } else {
            return $this->refund->call($orderId, $amount, $reason);
        }
    }

    public function getOrderTransactionId($order)
    {
        $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');
        $transactionId = false;
        $args = array(
            'post_id' => $orderId,
            'approve' => 'approve',
        );

        remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));

        $comments = get_comments($args);
        foreach ($comments as $comment) {
            if (strpos($comment->comment_content, '(Transaction ID: ') !== false) {
                $explodedComment = explode(': ', $comment->comment_content);
                $transactionId = substr($explodedComment[1], 0, -1); // trim) from comment
            }
        }
        unset($comments);
        unset($comment);

        add_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));
        return $transactionId;
    }

    public function throwUserError($error) {
        if ($customMessage = $this->custom_error) {
            $error = sprintf($customMessage, $error);
        }

        throw new Exception(esc_html($error, 'wc_securesubmit'));
    }

    public function displayUserError($message)
    {
        global $woocommerce;
        $message = sprintf(
            /* translators:%s: message */
            esc_html__('%s .' ,'wc_securesubmit' ),
            (string)$message);
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, 'error');
        } else if (isset($woocommerce) && property_exists($woocommerce, 'add_error')) {
            $woocommerce->add_error($message);
        }
    }

    public function getCreditService()
    {
        $config = new HpsServicesConfig();
        $config->secretApiKey = $this->secret_key;
        $config->versionNumber = '1510';
        $config->developerId = '002914';

        return new HpsFluentCreditService($config);
    }

    public function getOrderAddress($order)
    {
        $hpsaddress = new HpsAddress();
        $hpsaddress->address = WC_SecureSubmit_Util::getData($order, 'get_billing_address_1', 'billing_address_1');
        $hpsaddress->city = WC_SecureSubmit_Util::getData($order, 'get_billing_city', 'billing_city');
        $hpsaddress->state = WC_SecureSubmit_Util::getData($order, 'get_billing_state', 'billing_state');
        $hpsaddress->zip = WC_SecureSubmit_Util::getData($order, 'get_billing_postcode', 'billing_postcode');
        $hpsaddress->country = WC_SecureSubmit_Util::getData($order, 'get_billing_country', 'billing_country');
        return $hpsaddress;
    }

    public function getOrderCardHolder($order, $hpsaddress)
    {
        $cardHolder = new HpsCardHolder();
        $cardHolder->firstName = WC_SecureSubmit_Util::getData($order, 'get_billing_first_name', 'billing_first_name');
        $cardHolder->lastName = WC_SecureSubmit_Util::getData($order, 'get_billing_last_name', 'billing_last_name');
        $cardHolder->phone = WC_SecureSubmit_Util::getData($order, 'get_billing_phone', 'billing_phone');
        $cardHolder->email = WC_SecureSubmit_Util::getData($order, 'get_billing_email', 'billing_email');
        $cardHolder->address = $hpsaddress;
        return $cardHolder;
    }

    protected function isTransactionActiveOnGateway($orderId)
    {
        $order = wc_get_order($orderId);
        $transactionId = $this->getOrderTransactionId($order);

        if (empty($transactionId)) {
            $this->displayUserError('Unable to capture payment');
            return;
        }

        $transaction = $this->getCreditService()->get($transactionId)->execute();
        return $transaction->transactionStatus == 'A';
    }

    protected function getSetting($setting)
    {
        $value = null;
        if (isset($this->settings[$setting])) {
            $value = $this->settings[$setting];
        }
        return $value;
    }

    public function cleanValue($value)
    {
        if (function_exists('wc_clean')) {
            return wc_clean($value);
        } elseif (function_exists('woocommerce_clean')) {
            return woocommerce_clean($value);
        }
        return $value;
    }

    private function tryTransOptimization($orderId) {
        try {
            // generate bearer token
            $nonce = (string) time();
            $secret = hash("sha512", $nonce . $this->app_key);

            if (strpos($this->public_key, '_cert_') !== false) {
                $tokenUrl = "https://apis-cert.globalpay.com/accesstoken";
                $eddUrl = "https://apis-cert.globalpay.com/edd-trans-optimization";
            } else {
                $tokenUrl = "https://apis.globalpay.com/accesstoken";
                $eddUrl = "https://apis.globalpay.com/edd-trans-optimization";
            }

            $tokenBody = array(
                "app_id" => $this->app_id,
                "secret" => $secret,
                "grant_type" => "client_credentials",
                "nonce" => $nonce
            );

            $bearerTokenResponse = wp_remote_post(
                $tokenUrl,
                array(
                    'headers' => array (
                        'Content-Type' => 'application/json'
                    ),
                    'body' => wp_json_encode($tokenBody)
                )
            );

            $bearerTokenVal = json_decode($bearerTokenResponse['body'])->token;

            if (empty($bearerTokenVal)) return;

            // now handle edd-trans-optimization
            $order = wc_get_order($orderId);
            $currencyNumeric = get_woocommerce_currency() === "CAD" ? "124" : "840";
            $giftCardsApplied = WC()->session->get('securesubmit_gift_card_applied') !== null;

            $eddBody = array(
                "additionalTransactionData" => array(
                    "bankIdentificationNumber" => $_POST["bin"],
                    "lastFourCardNumber" => $_POST["last_four"],
                    "purchaseAmount" => $order->get_total(),
                    "purchaseCurrency" => $currencyNumeric
                ),
                "deviceData" => array(
                    "browserIP" => WC_Geolocation::get_ip_address(),
                    // "deviceId" => "00:1B:44:11:3A:B7", // don't think there's
                    // "deviceLongitude" => "-90.0715", // a good way to get
                    // "deviceLatitude" => "29.9511" // these three values
                ),
                "customerData" => array(
                    "customerFirstName" => $order->get_billing_first_name(),
                    "customerLastName" => $order->get_billing_last_name(),
                    "customerEmailAddress" => $order->get_billing_email(),
                    "homePhone" => $order->get_billing_phone(),
                    "mobilePhone" => $order->get_billing_phone(),
                    "workPhone" => $order->get_billing_phone()
                ),
                "orderData" => array(
                    "alternatePaymentIndicator" => $giftCardsApplied ? "03" : "01"
                ),
                "shippingData" => array(
                    "shipAddrLine1" => $order->get_shipping_address_1(),
                    "shipAddrLine2" => $order->get_shipping_address_2(),
                    // "shipAddrLine3" => "Room 14", // WooComm only supports two lines
                    "shipAddrCity" => $order->get_shipping_city(),
                    "shipAddrState" => $order->get_shipping_state(),
                    "shipAddrPostCode" => $order->get_shipping_postcode(),
                    "shipAddrCountry" => $currencyNumeric,
                    "shipAddrCountry" => $order->get_shipping_country(),
                    "shipNameIndicator" => true
                )
            );

            $eddResponse = wp_remote_post(
                $eddUrl,
                array(
                    'headers' => array (
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $bearerTokenVal
                    ),
                    'body' => wp_json_encode($eddBody)
                )
            );

            $eddReferenceId = json_decode($eddResponse['body'])->additionalTransactionDataReferenceId;

            if (!empty($eddReferenceId))
                $order->add_order_note(
                    sprintf(
                        /* translators:%s: eddReferenceId */
                        esc_html__('Transaction sent for Enhanced Data Collection. Reference ID: %s '  ,'wc_securesubmit' ),
                        $eddReferenceId)
               );
        } catch(Exception $e) {
            // consumption
        }        
    }
}
