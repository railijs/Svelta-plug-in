<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Backend helper: call Svelta Delivery endpoint.
 * Shared helper for:
 *   - Status (check if DR exists)
 *   - Create (create DR)
 *   - Cancel (cancel DR)
 */

function simple_connect_call_svelta_delivery_endpoint($endpoint, $order_id, $extra, &$error_msg = '')
{
    $error_msg = '';

    if (! simple_connect_has_svelta_connection()) {
        $error_msg = 'Store is not connected to Svelta.';
        return null;
    }

    $site_key = simple_connect_get_or_create_site_key();

    $body = array_merge(
        array(
            'site_api_key' => $site_key,
            'store_url'    => home_url(),
            'order_id'     => (int) $order_id,
        ),
        is_array($extra) ? $extra : array()
    );

    $args = array(
        'timeout' => 10,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body'    => wp_json_encode($body),
    );

    $response = wp_remote_post($endpoint, $args);

    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        return null;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if (200 !== (int) $code) {
        $error_msg = 'Svelta responded with HTTP ' . $code;
        return null;
    }

    if (null === $data && JSON_ERROR_NONE !== json_last_error()) {
        $error_msg = 'Could not decode Svelta response.';
        return null;
    }

    return $data;
}
