<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin menu + "API Connect" page.
 * Adds a submenu:
 *   WooCommerce → API Connect
 * This page:
 *   - Handles the Svelta callback.
 *   - Shows the “Connect / Disconnect” card.
 *   - Allows choosing which status triggers the Svelta webhook.
 */

add_action(
    'admin_menu',
    function () {
        add_submenu_page(
            'woocommerce',
            'API Connect',
            'API Connect',
            'manage_options',
            'wc-api-connect',
            'simple_connect_admin_page'
        );
    }
);

/**
 * Main admin page callback.
 */
function simple_connect_admin_page()
{
    // 1) Handle callback from Svelta (?api_key=...&callback_url=...).
    if (isset($_GET['api_key'], $_GET['callback_url'])) {
        $callback_api_key = sanitize_text_field(wp_unslash($_GET['api_key']));
        $callback_url     = esc_url_raw(wp_unslash($_GET['callback_url']));

        simple_connect_log('Received callback from Svelta with api_key and callback_url.');

        // Save api_key and callback_url in the options table.
        update_option(SVELTA_OPTION_API_KEY, $callback_api_key);
        update_option(SVELTA_OPTION_CALLBACK_URL, $callback_url);

        // If no trigger status is chosen yet, default to "Processing".
        if (! get_option(SVELTA_OPTION_TRIGGER_STATUS)) {
            update_option(SVELTA_OPTION_TRIGGER_STATUS, 'wc-processing');
        }

        // Create or update the Svelta webhook using the new data.
        $error = '';
        $ok    = simple_connect_ensure_webhook($callback_api_key, $callback_url, $error);
        update_option(SVELTA_OPTION_WEBHOOK_LAST_ERROR, $ok ? '' : $error);

        if ($ok) {
            simple_connect_log('Webhook created/updated successfully after connect.');
        } else {
            simple_connect_log('Failed to create/update webhook after connect: ' . $error);
        }

        // Send Woo REST API credentials to Svelta.
        simple_connect_notify_backend_on_connect($callback_api_key, $callback_url);

        // Redirect to a clean URL (without api_key and callback_url in the browser bar).
        $clean_url = remove_query_arg(array('api_key', 'callback_url'));
        wp_safe_redirect($clean_url);
        exit;
    }

    // 2) Handle trigger status settings save (from the dropdown).
    $settings_saved = false;
    if (isset($_POST['svelta_trigger_status'])) {
        check_admin_referer('simple_connect_settings');

        $new_status = sanitize_text_field(wp_unslash($_POST['svelta_trigger_status']));
        $choices    = simple_connect_get_trigger_status_choices();

        if (isset($choices[$new_status])) {
            update_option(SVELTA_OPTION_TRIGGER_STATUS, $new_status);
            $settings_saved = true;

            // When the trigger status changes, we must update the webhook topic.
            $api_key      = get_option(SVELTA_OPTION_API_KEY);
            $callback_url = get_option(SVELTA_OPTION_CALLBACK_URL);

            if (! empty($api_key) && ! empty($callback_url)) {
                $error = '';
                $ok    = simple_connect_ensure_webhook($api_key, $callback_url, $error);
                update_option(SVELTA_OPTION_WEBHOOK_LAST_ERROR, $ok ? '' : $error);

                if ($ok) {
                    simple_connect_log(
                        'Webhook updated after trigger status change (new topic based on ' . $new_status . ').'
                    );
                } else {
                    simple_connect_log(
                        'Failed to update webhook after trigger status change: ' . $error
                    );
                }
            }
        }
    }

    $trigger_status = get_option(SVELTA_OPTION_TRIGGER_STATUS, 'wc-processing');
    $status_choices = simple_connect_get_trigger_status_choices();

?>
    <div class="wrap">
        <h1>API Connect</h1>
        <p>Connect your WooCommerce store to Svelta so new orders are sent automatically.</p>

        <hr style="margin:20px 0;">
        <?php simple_connect_frontend_ui(true); ?>

        <hr style="margin:30px 0;">
        <h2>Svelta delivery settings</h2>
        <p>Choose which order status should trigger a new Delivery Request on Svelta.</p>

        <form method="post" style="max-width:560px;">
            <?php wp_nonce_field('simple_connect_settings'); ?>

            <div class="sc-card" style="margin-top:10px; text-align:left;">
                <div class="sc-card-inner">
                    <p style="margin-top:0; font-size:13px; color:#4b5563;">
                        When an order changes to this status, Svelta will receive a new delivery request.
                    </p>

                    <label for="svelta_trigger_status" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#111827;">
                        Trigger status
                    </label>

                    <select name="svelta_trigger_status" id="svelta_trigger_status" style="width:100%; max-width:260px; padding:7px 8px; border-radius:10px; border:1px solid #d1d5db; font-size:13px;">
                        <?php foreach ($status_choices as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($trigger_status, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <p style="margin-top:8px; font-size:11px; color:#6b7280;">
                        Default: <strong>Processing</strong>. You can also use the Svelta-specific statuses
                        <strong>Pending pickup</strong> and <strong>Pending delivery</strong> once they appear in WooCommerce.
                    </p>

                    <button type="submit" class="sc-btn-primary" style="margin-top:12px;">
                        Save settings
                    </button>

                    <?php if ($settings_saved) : ?>
                        <p style="margin-top:8px; font-size:11px; color:#16a34a;">
                            Settings saved. The Svelta webhook was updated to use the new trigger.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
<?php
}
