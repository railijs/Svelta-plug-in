<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Disconnect from Svelta
 * This action is triggered from the "Disconnect" button on the API Connect page.
 */

add_action('wp_ajax_svelta_disconnect', 'simple_connect_svelta_disconnect');
function simple_connect_svelta_disconnect()
{
    check_ajax_referer('simple_connect_nonce', 'nonce');

    if (! current_user_can('manage_options')) {
        wp_send_json_error(
            array(
                'message' => 'Insufficient permissions',
            )
        );
    }

    $callback_url = get_option(SVELTA_OPTION_CALLBACK_URL);

    // Try to disable any webhook that uses the Svelta callback URL.
    $dummy_error = '';
    if ($callback_url && simple_connect_load_wc_webhook_bits($dummy_error) && class_exists('WC_Data_Store')) {
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

                if ($wh instanceof WC_Webhook && rtrim($wh->get_delivery_url(), '/') === rtrim($callback_url, '/')) {
                    $wh->set_status('disabled');
                    $wh->save();
                }
            }
        }
    }

    // Clear saved Svelta data (keep site key + REST key so reconnect is easier).
    delete_option(SVELTA_OPTION_API_KEY);
    delete_option(SVELTA_OPTION_CALLBACK_URL);
    delete_option(SVELTA_OPTION_WEBHOOK_ID);
    delete_option(SVELTA_OPTION_WEBHOOK_LAST_ERROR);

    simple_connect_log('Disconnected from Svelta (config cleared, webhook disabled).');

    wp_send_json_success();
}
