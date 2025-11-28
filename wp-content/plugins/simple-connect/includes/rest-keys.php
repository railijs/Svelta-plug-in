<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce REST API key creation + notifying Svelta about credentials.
 *
 * This version:
 *  - Checks the WooCommerce API keys table to see if the stored key still exists.
 *  - If it was revoked, it creates a brand-new key directly in the DataBase.
 *  - Does NOT rely on WC_REST_API_Keys_Controller at all.
 */

/**
 * Get or create the WooCommerce REST API key Svelta will use.
 *
 * Behaviour:
 *  - If a key is stored in wp_options, verify it still exists in wp_woocommerce_api_keys.
 *    - If it exists -> reuse it.
 *    - If it was revoked -> delete stored options and create a new key.
 *  - If no key is stored, create a new one.
 *
 * @return array|null {
 *   @type int    $key_id
 *   @type string $consumer_key
 *   @type string $consumer_secret
 * }
 */
function simple_connect_get_or_create_wc_rest_key()
{
    global $wpdb;

    // Read any previously stored key.
    $existing_id = (int) get_option(SVELTA_OPTION_REST_KEY_ID);
    $existing_ck = get_option(SVELTA_OPTION_REST_CONSUMER_KEY);
    $existing_cs = get_option(SVELTA_OPTION_REST_CONSUMER_SECRET);

    $table = $wpdb->prefix . 'woocommerce_api_keys';

    /*
     * 1) If we *think* we have a key saved, verify that it still exists in the DB.
     *    If it doesn't exist (e.g. revoked in the UI), clear our options so
     *    we can auto-create a new key.
     */
    if ($existing_id && $existing_ck && $existing_cs) {
        // Check table exists first (older / broken installs safety).
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        );

        if ($table_exists) {
            $db_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT key_id FROM {$table} WHERE key_id = %d",
                    $existing_id
                )
            );

            if ($db_id) {
                // Key still exists – keep using it.
                simple_connect_log('Using existing Woo REST key (ID ' . $existing_id . ').');

                return array(
                    'key_id'          => $existing_id,
                    'consumer_key'    => $existing_ck,
                    'consumer_secret' => $existing_cs,
                );
            }

            // If we get here, Woo no longer has this key – clean up our options.
            simple_connect_log(
                'Stored Woo REST key (ID ' . $existing_id . ') not found in DB, will create a new one.'
            );
        } else {
            // Table missing – just reuse existing options to avoid locking the user out.
            simple_connect_log(
                'woocommerce_api_keys table not found; reusing stored key values without validation.'
            );

            return array(
                'key_id'          => $existing_id,
                'consumer_key'    => $existing_ck,
                'consumer_secret' => $existing_cs,
            );
        }

        delete_option(SVELTA_OPTION_REST_KEY_ID);
        delete_option(SVELTA_OPTION_REST_CONSUMER_KEY);
        delete_option(SVELTA_OPTION_REST_CONSUMER_SECRET);

        $existing_id = 0;
        $existing_ck = '';
        $existing_cs = '';
    }

    /*
     * 2) No valid key exists – create a fresh one directly in the DB.
     *    (Same pattern WooCommerce uses internally via wc_rand_hash + wc_api_hash.)
     */

    // These helpers are normally loaded by WooCommerce, but just in case:
    if (! function_exists('wc_rand_hash') || ! function_exists('wc_api_hash')) {
        if (defined('WC_ABSPATH')) {
            // Core functions live here.
            include_once WC_ABSPATH . 'includes/wc-core-functions.php';
        }
    }

    if (! function_exists('wc_rand_hash') || ! function_exists('wc_api_hash')) {
        simple_connect_log('WooCommerce helper functions (wc_rand_hash / wc_api_hash) not available, REST key not created.');
        return null;
    }

    // Use current user as owner; fallback to user ID 1.
    $user_id = get_current_user_id();
    if (! $user_id) {
        $user_id = 1;
    }

    $user = get_user_by('id', $user_id);

    // Description is similar to Woo's own description format.
    if (! function_exists('wc_trim_string')) {
        // wc_trim_string is also in wc-core-functions.php which we already tried to include.
        simple_connect_log('wc_trim_string not available, but continuing with a simple description.');
    }

    $login       = $user ? $user->user_login : 'Svelta';
    $clean_login = function_exists('wc_clean') ? wc_clean($login) : $login;
    $trimmed     = function_exists('wc_trim_string')
        ? wc_trim_string($clean_login, 170)
        : substr($clean_login, 0, 170);

    $description = sprintf(
        '%s - API (%s)',
        $trimmed,
        gmdate('Y-m-d H:i:s')
    );

    $permissions     = 'read_write';
    $consumer_key    = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();

    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'         => $user_id,
            'description'     => $description,
            'permissions'     => $permissions,
            'consumer_key'    => wc_api_hash($consumer_key),
            'consumer_secret' => $consumer_secret,
            'truncated_key'   => substr($consumer_key, -7),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s')
    );

    if (! $inserted) {
        simple_connect_log('Failed to insert new Woo REST key row into ' . $table . '.');
        return null;
    }

    $key_id = (int) $wpdb->insert_id;

    // Persist the key details so we reuse the same key on every page load.
    update_option(SVELTA_OPTION_REST_KEY_ID, $key_id);
    update_option(SVELTA_OPTION_REST_CONSUMER_KEY, $consumer_key);
    update_option(SVELTA_OPTION_REST_CONSUMER_SECRET, $consumer_secret);

    simple_connect_log('Created Woo REST key via direct DB insert (ID ' . $key_id . ').');

    return array(
        'key_id'          => $key_id,
        'consumer_key'    => $consumer_key,
        'consumer_secret' => $consumer_secret,
    );
}

/**
 * Notify Svelta backend with the WooCommerce REST API credentials.
 *
 * This part is essentially the same as before – we just depend on the
 * new simple_connect_get_or_create_wc_rest_key() above.
 */
function simple_connect_notify_backend_on_connect($svelta_api_key, $callback_url)
{
    if (empty($svelta_api_key)) {
        return;
    }

    // Make sure we have a Woo REST key before calling Svelta.
    $wc_rest = simple_connect_get_or_create_wc_rest_key();
    if (! $wc_rest) {
        simple_connect_log(
            'Unable to send plugin credentials to Svelta because Woo REST key could not be created.'
        );
        return;
    }

    $body = array(
        'client_id'     => $wc_rest['consumer_key'],
        'client_secret' => $wc_rest['consumer_secret'],
        'redirect_url'  => home_url(), // This is the main store URL.
    );

    $args = array(
        'timeout' => 10,
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key'    => $svelta_api_key, // api_key from Svelta callback.
        ),
        'body'    => wp_json_encode($body),
    );

    $response = wp_remote_post(SVELTA_BACKEND_RECEIVE_CREDS_URL, $args);

    if (is_wp_error($response)) {
        simple_connect_log(
            'Failed to send plugin credentials to Svelta (ReceivePluginCredentials): '
                . $response->get_error_message()
        );
        return;
    }

    $code = (int) wp_remote_retrieve_response_code($response);

    if (200 === $code) {
        simple_connect_log(
            'Successfully sent plugin credentials to Svelta (ReceivePluginCredentials, HTTP 200).'
        );
    } else {
        $body_raw = wp_remote_retrieve_body($response);
        simple_connect_log(
            'Svelta ReceivePluginCredentials responded with HTTP '
                . $code
                . '. Body: '
                . substr($body_raw, 0, 200)
        );
    }
}
