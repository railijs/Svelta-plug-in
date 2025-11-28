<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Webhook creation / management.
 * This plugin always tries to keep exactly ONE Svelta webhook:
 * - Topic: action.woocommerce_order_status_<chosen_status>
 * - Delivery URL: callback_url from Svelta
 * - Secret: api_key from Svelta
 */

/**
 * Ensure WooCommerce webhook classes/functions are loaded.
 */
function simple_connect_load_wc_webhook_bits(&$error_out = null)
{
    $error_out = '';

    // Fast path: classes already loaded.
    if (class_exists('WC_Webhook') && function_exists('wc_get_webhook') && class_exists('WC_Data_Store')) {
        return true;
    }

    $debug = array();
    $base  = '';

    // Try to resolve WooCommerce base path.
    if (defined('WC_ABSPATH')) {
        $base    = trailingslashit(WC_ABSPATH);
        $debug[] = 'base=WC_ABSPATH:' . $base;
    } elseif (function_exists('WC') && WC() && method_exists(WC(), 'plugin_path')) {
        $base    = trailingslashit(WC()->plugin_path());
        $debug[] = 'base=WC()->plugin_path:' . $base;
    } else {
        $debug[] = 'no_base_resolved';
    }

    // Load webhook classes from WooCommerce, if possible.
    if ($base) {
        $class_file = $base . 'includes/class-wc-webhook.php';
        $func_file  = $base . 'includes/wc-webhook-functions.php';

        $debug[] = 'class_file_exists=' . (file_exists($class_file) ? 'yes' : 'no');
        $debug[] = 'func_file_exists=' . (file_exists($func_file) ? 'yes' : 'no');

        if (file_exists($class_file) && ! class_exists('WC_Webhook')) {
            include_once $class_file;
        }

        if (file_exists($func_file) && ! function_exists('wc_get_webhook')) {
            include_once $func_file;
        }
    }

    $ok = class_exists('WC_Webhook') && function_exists('wc_get_webhook') && class_exists('WC_Data_Store');

    if (! $ok) {
        $debug[]   = 'class_exists_WC_Webhook=' . (class_exists('WC_Webhook') ? 'yes' : 'no');
        $debug[]   = 'func_wc_get_webhook=' . (function_exists('wc_get_webhook') ? 'yes' : 'no');
        $debug[]   = 'class_exists_WC_Data_Store=' . (class_exists('WC_Data_Store') ? 'yes' : 'no');
        $error_out = implode(' | ', $debug);
        return false;
    }

    return true;
}

/**
 * Create or update the Svelta webhook so that:
 * - it uses the correct topic (based on the chosen trigger status),
 * - it sends data to the correct callback URL (from Svelta),
 * - it uses the Svelta api_key as the secret.
 */
function simple_connect_ensure_webhook($api_key, $callback_url, &$error_msg = null)
{
    $error_msg = '';

    if (empty($callback_url)) {
        $error_msg = 'Callback URL is empty.';
        return false;
    }

    $loader_error = '';
    if (! simple_connect_load_wc_webhook_bits($loader_error)) {
        $error_msg = 'WooCommerce webhook classes/functions are not available: ' . $loader_error;
        return false;
    }

    // Read the current trigger status from options (e.g. wc-processing, wc-completed).
    $trigger_status = get_option(SVELTA_OPTION_TRIGGER_STATUS);
    if (empty($trigger_status)) {
        $trigger_status = 'wc-processing';
        update_option(SVELTA_OPTION_TRIGGER_STATUS, $trigger_status);
    }

    // Convert "wc-processing" → "processing" etc.
    $status_slug = preg_replace('/^wc-/', '', $trigger_status);
    if (empty($status_slug)) {
        $status_slug = 'processing';
    }

    // Final webhook topic, e.g. action.woocommerce_order_status_completed
    $topic = 'action.woocommerce_order_status_' . $status_slug;

    $delivery_url     = $callback_url;
    $saved_webhook_id = (int) get_option(SVELTA_OPTION_WEBHOOK_ID);
    $webhook          = null;

    // Try to find an existing Svelta webhook by scanning WooCommerce webhooks.
    if (class_exists('WC_Data_Store')) {
        try {
            $data_store = WC_Data_Store::load('webhook');
            $ids        = $data_store->get_webhooks_ids('');
        } catch (Exception $e) {
            $ids = array();
        }

        if (is_array($ids)) {
            foreach ($ids as $id) {
                try {
                    $wh = wc_get_webhook($id);
                } catch (Exception $e) {
                    $wh = null;
                }

                if (! $wh instanceof WC_Webhook) {
                    continue;
                }

                $name = $wh->get_name();

                // We consider a webhook as “ours” if:
                // - its ID matches the stored one, OR
                // - its delivery URL matches Svelta's callback URL, OR
                // - its name contains "Svelta Webhook".
                if (
                    ($saved_webhook_id > 0 && $wh->get_id() === $saved_webhook_id) ||
                    rtrim($wh->get_delivery_url(), '/') === rtrim($delivery_url, '/') ||
                    (is_string($name) && stripos($name, 'svelta webhook') !== false)
                ) {
                    $webhook = $wh;
                    break;
                }
            }
        }
    }

    // Fallback: try loading by stored ID directly.
    if (! $webhook && $saved_webhook_id > 0) {
        try {
            $maybe = wc_get_webhook($saved_webhook_id);
        } catch (Exception $e) {
            $maybe = null;
        }

        if ($maybe instanceof WC_Webhook && $maybe->get_id()) {
            $webhook = $maybe;
        }
    }

    // If we still didn't find anything, create a brand new webhook.
    if (! $webhook) {
        $webhook = new WC_Webhook();
        $webhook->set_name(SVELTA_WEBHOOK_NAME . ' (status trigger)');
    }

    // Configure the webhook with our current settings.
    $webhook->set_status('active');
    $webhook->set_topic($topic);
    $webhook->set_delivery_url($delivery_url);

    if (! empty($api_key)) {
        $webhook->set_secret($api_key);
    }

    if (method_exists($webhook, 'set_api_version')) {
        $webhook->set_api_version(3);
    }

    // Save the webhook and store its ID for reuse.
    $saved_id = $webhook->save();

    if (! $saved_id) {
        $error_msg = 'Webhook save returned no ID.';
        return false;
    }

    update_option(SVELTA_OPTION_WEBHOOK_ID, (int) $saved_id);

    simple_connect_log(
        'Svelta webhook saved (ID ' . $saved_id . ', topic ' . $topic . ').'
    );

    return true;
}
