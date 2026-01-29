<?php
/**
 * Admin notice for MasterPass removal
 */

if (!defined('ABSPATH')) {
    exit();
}

class WC_SecureSubmit_MasterPass_Removal_Notice
{
    public function __construct()
    {
        add_action('admin_notices', array($this, 'showRemovalNotice'));
        add_action('admin_init', array($this, 'dismissNotice'));
    }

    /**
     * Display admin notice about MasterPass removal
     */
    public function showRemovalNotice()
    {
        // Only show to users who can manage WooCommerce
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Check if notice has been dismissed
        if (get_option('securesubmit_masterpass_removal_notice_dismissed', false)) {
            return;
        }

        // Only show on WooCommerce admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'woocommerce') === false) {
            return;
        }

        $dismiss_url = add_query_arg(array(
            'securesubmit_dismiss_masterpass_notice' => '1',
            'nonce' => wp_create_nonce('securesubmit_dismiss_masterpass_notice')
        ));

        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php _e('SecureSubmit Gateway - MasterPass Removed', 'wc_securesubmit'); ?></strong>
            </p>
            <p>
                <?php _e('The MasterPass payment method has been removed from the SecureSubmit Gateway to prevent PHP 8.1+ compatibility issues.', 'wc_securesubmit'); ?>
            </p>
            <p>
                <?php _e('The MasterPass program is migrating to Click to Pay, which will be available in a future update. Your other payment methods continue to work normally.', 'wc_securesubmit'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url($dismiss_url); ?>" class="button button-primary">
                    <?php _e('Dismiss this notice', 'wc_securesubmit'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle notice dismissal
     */
    public function dismissNotice()
    {
        if (!isset($_GET['securesubmit_dismiss_masterpass_notice'])) {
            return;
        }

        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'securesubmit_dismiss_masterpass_notice')) {
            return;
        }

        update_option('securesubmit_masterpass_removal_notice_dismissed', true);
        
        // Redirect to remove the query parameter
        wp_redirect(remove_query_arg(array('securesubmit_dismiss_masterpass_notice', 'nonce')));
        exit;
    }
}

// Initialize the notice
new WC_SecureSubmit_MasterPass_Removal_Notice();
