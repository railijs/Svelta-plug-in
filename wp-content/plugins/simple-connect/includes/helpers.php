<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Generic helpers (store host, start URL, logging, site key, connection checks).
 */

/**
 * Get the host part of the store URL that we send to Svelta as store_url.
 * Example:
 *   home_url() = https://shop.example.com
 *   store_host = shop.example.com
 */
function simple_connect_get_store_host()
{
    $full_url = home_url();
    $host     = parse_url($full_url, PHP_URL_HOST);

    if (empty($host)) {
        $host = $full_url;
    }

    return $host;
}

/**
 * Build the Svelta Start URL that we redirect the merchant to.
 */
function simple_connect_get_svelta_start_url()
{
    $host = simple_connect_get_store_host();
    return SVELTA_START_BASE_URL . rawurlencode($host);
}

/**
 * Logging helper to debug.log (if WP_DEBUG is enabled).
 */
function simple_connect_log($message)
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Simple Connect] ' . $message);
    }
}

/**
 * Site key (internal identifier for this store).
 * Generated once and used for all Delivery API calls.
 */
function simple_connect_get_or_create_site_key()
{
    $existing = get_option(SVELTA_OPTION_SITE_KEY);
    if (! empty($existing)) {
        return $existing;
    }

    // Generate a random 40-character string with no special characters.
    $key = wp_generate_password(40, false, false);
    update_option(SVELTA_OPTION_SITE_KEY, $key);

    return $key;
}

/**
 * Check if the store is currently "connected" to Svelta.
 * We require both api_key and callback_url to be present.
 */
function simple_connect_has_svelta_connection()
{
    $api_key      = get_option(SVELTA_OPTION_API_KEY);
    $callback_url = get_option(SVELTA_OPTION_CALLBACK_URL);

    return ! empty($api_key) && ! empty($callback_url);
}

/**
 * List of possible statuses that can be chosen as the Svelta trigger.
 */
function simple_connect_get_trigger_status_choices()
{
    return array(
        'wc-processing'       => 'Processing',
        'wc-pending-pickup'   => 'Pending pickup',
        'wc-pending-delivery' => 'Pending delivery',
        'wc-completed'        => 'Completed',
    );
}
