<?php
/**
 * Payment gateway payment functions
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
 * Payment gateway payment functions
 *
 * @category Tests
 * @package  WC_Gateway_SecureSubmit
 * @author   Heartland <EntApp_DevPortal@e-hps.com>
 * @license  Custom <https://github.com/hps/heartland-woocommerce-plugin/blob/master/LICENSE.md>
 * @link     https://github.com/hps/heartland-woocommerce-plugin
 */
class WC_Gateway_SecureSubmit_Tests_Integration_Gateway_PaymentTest
    extends WC_Gateway_SecureSubmit_Tests_Utility_UnitTestCase
{
    const TOKEN_FIELD_NAME     = 'securesubmit_token';
    const LAST_FOUR_FIELD_NAME = 'last_four';
    const EXP_MONTH_FIELD_NAME = 'exp_month';
    const EXP_YEAR_FIELD_NAME  = 'exp_year';
    const CARD_TYPE_FIELD_NAME = 'card_type';
    const SAVE_CARD_FIELD_NAME = 'save_card';

    protected $tokenService = null;
    protected $targetClass  = 'WC_Gateway_SecureSubmit';

    public function setUp()
    {
        parent::setUp();

        $this->tokenService = new HpsTokenService(
            $this->instance->public_key
        );

        $this->creditCard = new HpsCreditCard();
        $this->creditCard->number   = '4012002000060016';
        $this->creditCard->expMonth = '12';
        $this->creditCard->expYear  = '2025';
        $this->creditCard->cvv      = '123';
    }

    /** @group external-http */
    public function testSuccessSale()
    {
        $this->getTokenAndSetPOST();

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create('securesubmit');
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('success', $result['result']);
        $this->assertNotEquals('', $result['redirect']);
        $this->assertEquals(array(), $notices);
    }

    /** @group external-http */
    public function testSuccessAuth()
    {
        $this->getTokenAndSetPOST();
        $this->instance->paymentaction = 'authorize';

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create('securesubmit');
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('success', $result['result']);
        $this->assertNotEquals('', $result['redirect']);
        $this->assertEquals(array(), $notices);
    }

    /** @group external-http */
    public function testSuccessSaveCardNoUser()
    {
        $this->getTokenAndSetPOST();
        $_POST[self::SAVE_CARD_FIELD_NAME] = 'true';

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create('securesubmit');
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('success', $result['result']);
        $this->assertNotEquals('', $result['redirect']);
        $this->assertEquals(array(), $notices);
    }

    /** @group external-http */
    public function testSuccessSaveCardUser()
    {
        $this->getTokenAndSetPOST();
        $_POST[self::SAVE_CARD_FIELD_NAME] = 'true';
        $user = $this->factory->user->create();
        wp_set_current_user($user);

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create('securesubmit');
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();
        $cards = get_user_meta($user, '_secure_submit_card');

        $this->assertEquals('success', $result['result']);
        $this->assertNotEquals('', $result['redirect']);
        $this->assertEquals(array(), $notices);
        $this->assertNotEquals(array(), $cards);
    }

    /** @group external-http */
    public function testSuccessSaveCardUserMasterCard()
    {
        $this->creditCard->number = '5473500000000014';
        $this->getTokenAndSetPOST();
        $_POST[self::SAVE_CARD_FIELD_NAME] = 'true';
        $_POST[self::CARD_TYPE_FIELD_NAME] = 'mastercard';
        $user = $this->factory->user->create();
        wp_set_current_user($user);

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create('securesubmit');
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();
        $cards = get_user_meta($user, '_secure_submit_card', false);

        $this->assertEquals('success', $result['result']);
        $this->assertNotEquals('', $result['redirect']);
        $this->assertEquals(array(), $notices);
        $this->assertNotEquals(array(), $cards);
    }

    /** @group external-http */
    public function testSuccessUseSavedCard()
    {
        $this->getTokenAndSetPOST();
        $user = $this->factory->user->create();
        wp_set_current_user($user);
        add_user_meta(
            $user,
            '_secure_submit_card',
            array(
                'token_value' => $_POST[self::TOKEN_FIELD_NAME],
            )
        );
        $_POST = null;
        $_POST['secure_submit_card'] = 0;

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create('securesubmit');
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('success', $result['result']);
        $this->assertNotEquals('', $result['redirect']);
        $this->assertEquals(array(), $notices);
    }

    /** @group external-http */
    public function testSuccessAllowSuspicious()
    {
        $sk = $this->instance->secret_key;
        $this->instance->secret_key = 'skapi_cert_MdCMAQCNNmsFJjbyinb90B96W0p4mOtyk6iW-hVDYw'; #gitleaks:allow
        $this->instance->allow_fraud = 'yes';
        $this->tokenService = new HpsTokenService(
            'pkapi_cert_5p1OdRXdfAFedIfT78'
        );
        $this->getTokenAndSetPOST();

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create(
            'securesubmit',
            15001
        );
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('success', $result['result']);
        $this->assertNotEquals('', $result['redirect']);
        $this->assertEquals(array(), $notices);
        $this->instance->secret_key = $sk;
        $this->instance->allow_fraud = 'no';
    }

    /** @group external-http */
    public function testSuccessAllowSuspiciousSendEmail()
    {
        $sk = $this->instance->secret_key;
        $this->instance->secret_key = 'skapi_cert_MdCMAQCNNmsFJjbyinb90B96W0p4mOtyk6iW-hVDYw'; #gitleaks:allow
        $this->instance->allow_fraud = 'yes';
        $this->instance->email_fraud = 'yes';
        $this->instance->fraud_address = 'bob@example.com';
        $this->tokenService = new HpsTokenService(
            'pkapi_cert_5p1OdRXdfAFedIfT78'
        );
        $this->getTokenAndSetPOST();

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create(
            'securesubmit',
            15001
        );
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('success', $result['result']);
        $this->assertNotEquals('', $result['redirect']);
        $this->assertEquals(array(), $notices);
        $this->instance->secret_key = $sk;
        $this->instance->allow_fraud = 'no';
        $this->instance->email_fraud = 'no';
        $this->instance->fraud_address = '';
    }

    /** @group external-http */
    public function testFailNoToken()
    {
        $this->getTokenAndSetPOST();
        $_POST[self::TOKEN_FIELD_NAME] = null;

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create('securesubmit');
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertNotEquals(array(), $notices);
    }

    public function testFailBadSavedCard()
    {
        $_POST['secure_submit_card'] = 1;
        $user = $this->factory->user->create();
        wp_set_current_user($user);

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create('securesubmit');
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertNotEquals(array(), $notices);
    }

    /** @group external-http */
    public function testFailDisallowSuspicious()
    {
        $sk = $this->instance->secret_key;
        $this->instance->secret_key = 'skapi_cert_MdCMAQCNNmsFJjbyinb90B96W0p4mOtyk6iW-hVDYw'; #gitleaks:allow
        $this->tokenService = new HpsTokenService(
            'pkapi_cert_5p1OdRXdfAFedIfT78'
        );
        $this->getTokenAndSetPOST();

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create(
            'securesubmit',
            15001
        );
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertNotEquals(array(), $notices);
        $this->instance->secret_key = $sk;
    }

    /** @group external-http */
    public function testFailDisallowSuspiciousSendEmail()
    {
        $sk = $this->instance->secret_key;
        $this->instance->secret_key = 'skapi_cert_MdCMAQCNNmsFJjbyinb90B96W0p4mOtyk6iW-hVDYw'; #gitleaks:allow
        $this->instance->email_fraud = 'yes';
        $this->instance->fraud_address = 'bob@example.com';
        $this->tokenService = new HpsTokenService(
            'pkapi_cert_5p1OdRXdfAFedIfT78'
        );
        $this->getTokenAndSetPOST();

        $order = WC_Gateway_SecureSubmit_Tests_Utility_Order::create(
            'securesubmit',
            15001
        );
        $result = $this->instance->process_payment($order->id);
        $notices = wc_get_notices();

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertNotEquals(array(), $notices);
        $this->instance->secret_key = $sk;
        $this->instance->email_fraud = 'no';
        $this->instance->fraud_address = '';
    }

    protected function getTokenAndSetPOST()
    {
        $token = $this->tokenService->getToken($this->creditCard);
        $_POST[self::TOKEN_FIELD_NAME]     = $token->token_value;
        $_POST[self::LAST_FOUR_FIELD_NAME] = substr($this->creditCard->number, -4);
        $_POST[self::EXP_MONTH_FIELD_NAME] = $this->creditCard->expMonth;
        $_POST[self::EXP_YEAR_FIELD_NAME]  = $this->creditCard->expYear;
        $_POST[self::CARD_TYPE_FIELD_NAME] = 'Visa';
    }
}
