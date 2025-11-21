<?php
/*
Plugin Name: Simple Connect Plugin
Description: Connect WooCommerce to Svelta Courier and auto-create/update a single “order.created” webhook using Svelta’s callback URL + API key.
Version: 24.2
Author: Railijs Didzis Grieznis
*/

if (! defined('ABSPATH')) exit;

/* ---------------------------------------------------
 * CONSTANTS / OPTIONS
 * ---------------------------------------------------*/

// Webhook name (only for display in Woo admin).
if (! defined('SVELTA_WEBHOOK_NAME')) {
    define('SVELTA_WEBHOOK_NAME', 'Svelta Webhook');
}

// Options where we store Svelta callback data.
if (! defined('SVELTA_OPTION_API_KEY')) {
    define('SVELTA_OPTION_API_KEY', 'svelta_api_key');
}
if (! defined('SVELTA_OPTION_CALLBACK_URL')) {
    define('SVELTA_OPTION_CALLBACK_URL', 'svelta_callback_url');
}
if (! defined('SVELTA_OPTION_WEBHOOK_ID')) {
    define('SVELTA_OPTION_WEBHOOK_ID', 'svelta_webhook_id');
}
if (! defined('SVELTA_OPTION_WEBHOOK_LAST_ERROR')) {
    define('SVELTA_OPTION_WEBHOOK_LAST_ERROR', 'svelta_webhook_last_error');
}

/* ---------------------------------------------------
 * SVELTA START ENDPOINT (dynamic store_url)
 * ---------------------------------------------------*/

if (! defined('SVELTA_START_BASE_URL')) {
    // Svelta "Start" endpoint – we append ?store_url=<host>.
    define(
        'SVELTA_START_BASE_URL',
        'https://staging.clientapi.sveltacourier.com/api/WooCommerceAuth/Start?store_url='
    );
}

/**
 * Get the store host that we send to Svelta as store_url.
 * e.g. http://localhost/wp  -> localhost
 *      https://shop.example.com -> shop.example.com
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
 * Build the Svelta Start URL with the real store host.
 */
function simple_connect_get_svelta_start_url()
{
    $host = simple_connect_get_store_host();
    return SVELTA_START_BASE_URL . rawurlencode($host);
}

/* ---------------------------------------------------
 * SMALL HELPER: MASK API KEY FOR DEBUG UI
 * ---------------------------------------------------*/
function simple_connect_mask_key($key)
{
    $key = (string) $key;
    $len = strlen($key);

    if ($len <= 8) {
        return $key;
    }

    $start = substr($key, 0, 8);
    $end   = substr($key, -4);

    return $start . str_repeat('•', max(0, $len - 12)) . $end;
}

/* ---------------------------------------------------
 * WOO HELPERS: WEBHOOK CREATION
 * ---------------------------------------------------*/

/**
 * Load WooCommerce webhook classes/functions.
 *
 * @param string|null $error_out Detailed error/debug info on failure.
 * @return bool
 */
function simple_connect_load_wc_webhook_bits(&$error_out = null)
{
    $error_out = '';

    // Quick success path.
    if (class_exists('WC_Webhook') && function_exists('wc_get_webhook') && class_exists('WC_Data_Store')) {
        return true;
    }

    $debug = array();
    $base  = '';

    if (defined('WC_ABSPATH')) {
        $base    = trailingslashit(WC_ABSPATH);
        $debug[] = 'base=WC_ABSPATH:' . $base;
    } elseif (function_exists('WC') && WC() && method_exists(WC(), 'plugin_path')) {
        $base    = trailingslashit(WC()->plugin_path());
        $debug[] = 'base=WC()->plugin_path:' . $base;
    } else {
        $debug[] = 'no_base_resolved';
    }

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
        $debug[] = 'class_exists_WC_Webhook=' . (class_exists('WC_Webhook') ? 'yes' : 'no');
        $debug[] = 'func_wc_get_webhook=' . (function_exists('wc_get_webhook') ? 'yes' : 'no');
        $debug[] = 'class_exists_WC_Data_Store=' . (class_exists('WC_Data_Store') ? 'yes' : 'no');
        $error_out = implode(' | ', $debug);
        return false;
    }

    return true;
}

/**
 * Create or update the Svelta webhook based on API key + callback URL.
 * - delivery_url = callback_url (from Svelta)
 * - secret       = api_key      (from Svelta)
 * - topic        = order.created
 *
 * Returns true on success, false on failure.
 * On failure, $error_msg will contain a message.
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

    $delivery_url     = $callback_url;
    $saved_webhook_id = (int) get_option(SVELTA_OPTION_WEBHOOK_ID);
    $webhook          = null;

    // --- 1) Try to find an existing "Svelta" webhook among ALL webhooks ---
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

                // Reuse this webhook if:
                // - it's the one we stored previously, OR
                // - it has the same delivery URL, OR
                // - its name looks like a Svelta webhook.
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

    // --- 2) Fallback: directly load by stored ID if we still didn't find one ---
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

    // --- 3) If still none, create a new webhook ---
    if (! $webhook) {
        $webhook = new WC_Webhook();
        $webhook->set_name(SVELTA_WEBHOOK_NAME . ' (order.created)');
    }

    // --- 4) Configure webhook fields ---
    $webhook->set_status('active');
    $webhook->set_topic('order.created');
    $webhook->set_delivery_url($delivery_url);

    if (! empty($api_key)) {
        $webhook->set_secret($api_key);
    }

    if (method_exists($webhook, 'set_api_version')) {
        $webhook->set_api_version(3);
    }

    // --- 5) Save ---
    $saved_id = $webhook->save();

    if (! $saved_id) {
        $error_msg = 'Webhook save returned no ID.';
        return false;
    }

    update_option(SVELTA_OPTION_WEBHOOK_ID, (int) $saved_id);

    return true;
}

/**
 * Simple "connected?" check for the UI:
 * we only require that we have an API key and callback URL saved.
 */
function simple_connect_has_svelta_connection()
{
    $api_key      = get_option(SVELTA_OPTION_API_KEY);
    $callback_url = get_option(SVELTA_OPTION_CALLBACK_URL);

    return ! empty($api_key) && ! empty($callback_url);
}

/* ---------------------------------------------------
 * ADMIN MENU
 * ---------------------------------------------------*/
add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'API Connect',
        'API Connect',
        'manage_options',
        'wc-api-connect',
        'simple_connect_admin_page'
    );
});

/* ---------------------------------------------------
 * ADMIN PAGE
 * ---------------------------------------------------*/
function simple_connect_admin_page()
{
    // Handle callback from Svelta: ?api_key=...&callback_url=...
    if (isset($_GET['api_key'], $_GET['callback_url'])) {
        $callback_api_key = sanitize_text_field(wp_unslash($_GET['api_key']));
        $callback_url     = esc_url_raw(wp_unslash($_GET['callback_url']));

        // Save in options so we can use them later (even when URL params are gone).
        update_option(SVELTA_OPTION_API_KEY, $callback_api_key);
        update_option(SVELTA_OPTION_CALLBACK_URL, $callback_url);

        // Immediately create / update webhook.
        $error = '';
        $ok    = simple_connect_ensure_webhook($callback_api_key, $callback_url, $error);
        update_option(SVELTA_OPTION_WEBHOOK_LAST_ERROR, $ok ? '' : $error);

        // 🔑 IMPORTANT: redirect to clean URL so api_key/callback_url
        // are not in the address bar and don't re-trigger this block.
        $clean_url = remove_query_arg(array('api_key', 'callback_url'));
        wp_safe_redirect($clean_url);
        exit;
    }

?>
    <div class="wrap">
        <h1>API Connect</h1>
        <p>Enable or disable the Svelta connection and preview the Connect UI.</p>

        <hr>
        <?php simple_connect_admin_ui(); ?>

        <hr style="margin:40px 0;">
        <h2>Admin Test Area</h2>
        <?php simple_connect_frontend_ui(true); ?>
    </div>
<?php
}

/* ---------------------------------------------------
 * FRONTEND (SHOW ON HOMEPAGE)
 * ---------------------------------------------------*/
add_filter('the_content', function ($content) {
    if (! is_front_page() && ! is_home()) {
        return $content;
    }

    ob_start();
    simple_connect_frontend_ui(false);
    return $content . ob_get_clean();
});

/* ---------------------------------------------------
 * SHARED STYLES
 * ---------------------------------------------------*/
function simple_connect_styles()
{ ?>
    <style>
        :root {
            --sv-purple: #7A4BFF;
            --sv-purple-dark: #6539d6;
            --sv-teal: #15d7c6;
            --sv-gray-200: #e5e7eb;
            --sv-gray-100: #f3f4f6;
            --sv-gray-50: #fafafa;
            --sv-gray-900: #111827;
            --sv-gray-700: #4b5563;
            --sv-gray-500: #6b7280;
        }

        .sc-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 999px;
            border: none;
            color: #fff;
            cursor: pointer;
            transition:
                background .15s ease,
                transform .1s ease,
                box-shadow .1s ease,
                opacity .15s ease;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--sv-purple);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12);
        }

        .sc-btn:hover {
            background: var(--sv-purple-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
        }

        .sc-btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 8px rgba(15, 23, 42, 0.16);
        }

        .sc-btn:disabled {
            opacity: 0.75;
            cursor: default;
            transform: none;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.1);
        }

        .sc-btn-teal {
            background: var(--sv-teal);
        }

        .sc-btn-teal:hover {
            background: #0f9f93;
        }

        .sc-btn-red {
            background: #ef4444;
        }

        .sc-btn-red:hover {
            background: #b91c1c;
        }

        .sc-connect-area {
            margin: 40px auto;
            text-align: center;
            max-width: 520px;
        }

        .sc-card {
            background: #fff;
            padding: 22px 22px 20px;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.07);
        }

        .sc-logo {
            width: 60%;
            margin: 0 auto 10px auto;
            display: block;
        }

        .sc-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--sv-gray-900);
        }

        .sc-subtitle {
            font-size: 12px;
            color: var(--sv-gray-500);
            margin-bottom: 16px;
        }

        .sc-desc {
            font-size: 13px;
            color: var(--sv-gray-700);
            margin-bottom: 12px;
            text-align: left;
        }

        .sc-store-url {
            font-size: 11px;
            color: var(--sv-gray-500);
            margin-bottom: 18px;
            text-align: left;
        }

        .sc-store-url code {
            font-size: 11px;
            background: var(--sv-gray-50);
            padding: 2px 6px;
            border-radius: 999px;
            border: 1px solid var(--sv-gray-200);
        }

        .sc-error {
            padding: 10px 12px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            font-size: 13px;
            text-align: left;
        }

        .sc-admin-card {
            background: #fff;
            padding: 18px 20px;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.4);
            max-width: 480px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .sc-admin-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .sc-admin-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--sv-gray-900);
        }

        .sc-status-badge {
            padding: 5px 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 11px;
            border: 1px solid var(--sv-gray-200);
        }

        .sc-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .sc-status-active {
            background: #dcfce7;
            color: #047857;
            border-color: #a7f3d0;
        }

        .sc-status-inactive {
            background: var(--sv-gray-100);
            color: #4b5563;
            border-color: var(--sv-gray-200);
        }

        .sc-status-active .sc-status-dot {
            background: #22c55e;
        }

        .sc-status-inactive .sc-status-dot {
            background: #4b5563;
        }

        .sc-result-box {
            background: var(--sv-gray-50);
            border: 1px solid var(--sv-gray-200);
            padding: 14px 12px;
            border-radius: 12px;
            text-align: left;
            font-size: 13px;
        }

        .sc-result-code {
            display: block;
            padding: 6px 8px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-top: 4px;
            overflow-wrap: anywhere;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
        }

        .sc-disconnect-wrap {
            margin-top: 12px;
            text-align: right;
        }

        .sc-disconnect-btn {
            background: transparent;
            border: none;
            padding: 0;
            font-size: 12px;
            color: #b91c1c;
            cursor: pointer;
            text-decoration: underline;
        }

        .sc-disconnect-btn:disabled {
            opacity: 0.7;
            cursor: default;
            text-decoration: none;
        }

        .sc-webhook-note {
            margin-top: 6px;
            font-size: 11px;
            color: #6b7280;
        }

        .sc-webhook-note-error {
            color: #b91c1c;
        }
    </style>
<?php }

/* ---------------------------------------------------
 * ADMIN UI BOX
 * ---------------------------------------------------*/
function simple_connect_admin_ui()
{
    $is_active = get_option('simple_connect_active', false);
    simple_connect_styles(); ?>

    <div class="sc-admin-card">
        <div class="sc-admin-header">
            <div class="sc-admin-title">Svelta Connection</div>
            <div class="sc-status-badge <?php echo $is_active ? 'sc-status-active' : 'sc-status-inactive'; ?>">
                <span class="sc-status-dot"></span>
                <span><?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
            </div>
        </div>

        <p style="margin:0 0 12px; font-size:12px; color:var(--sv-gray-600);">
            When active, shop owners can connect their store so new orders are sent to Svelta automatically.
        </p>

        <div>
            <?php if (! $is_active) : ?>
                <button class="sc-btn sc-btn-teal" onclick="toggleSC('activate')">
                    Activate
                </button>
            <?php else : ?>
                <button class="sc-btn sc-btn-red" onclick="toggleSC('deactivate')">
                    Deactivate
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSC(action) {
            jQuery.post(ajaxurl, {
                action: 'simple_connect_toggle',
                nonce: '<?php echo wp_create_nonce("simple_connect_nonce"); ?>',
                status: action
            }, function() {
                location.reload();
            });
        }
    </script>

<?php }

/* ---------------------------------------------------
 * FRONTEND / ADMIN CONNECT CARD
 * ---------------------------------------------------*/
function simple_connect_frontend_ui($is_admin_test = false)
{
    $is_active = get_option('simple_connect_active', false);

    $auth_url   = simple_connect_get_svelta_start_url();
    $store_host = simple_connect_get_store_host();

    $existing_callback = get_option(SVELTA_OPTION_CALLBACK_URL);
    $webhook_error     = get_option(SVELTA_OPTION_WEBHOOK_LAST_ERROR);
    $stored_api_key    = get_option(SVELTA_OPTION_API_KEY);
    $stored_webhook_id = (int) get_option(SVELTA_OPTION_WEBHOOK_ID);

    // Saved connection (from previous callback).
    $has_saved_connection = simple_connect_has_svelta_connection();

    // Just returned from Svelta in THIS request?
    $just_connected = isset($_GET['api_key'], $_GET['callback_url']);

    // What callback URL should we display?
    if ($just_connected) {
        $display_callback = esc_url_raw(wp_unslash($_GET['callback_url']));
    } else {
        $display_callback = $existing_callback;
    }

    // For UI: connected if we *either* just came back, or have saved values.
    $show_connected_box = ($just_connected || $has_saved_connection) && ! empty($display_callback);

    simple_connect_styles(); ?>

    <div class="sc-connect-area">

        <?php if (! $is_active && ! $is_admin_test) : ?>
            <div class="sc-error">
                Svelta connection is currently disabled. Please contact the shop admin.
            </div>
        <?php else : ?>

            <div class="sc-card">

                <img class="sc-logo"
                    src="https://www.svelta.io/wp-content/uploads/2024/08/Svelta-ltd-01-1024x301.png"
                    alt="Svelta logo">

                <div class="sc-title">Connect to Svelta</div>
                <div class="sc-subtitle">Send new WooCommerce orders automatically to Svelta</div>

                <p class="sc-desc">
                    Click Connect to go to Svelta and link this store. Once connected,
                    new orders will be sent to Svelta automatically.
                </p>

                <p class="sc-store-url">
                    Store URL being sent to Svelta:
                    <code><?php echo esc_html($store_host); ?></code>
                </p>

                <button class="sc-btn"
                    type="button"
                    onclick="window.location.href='<?php echo esc_url($auth_url); ?>'">
                    <span class="sc-btn-label">
                        <?php echo $show_connected_box ? 'Reconnect to Svelta' : 'Connect to Svelta'; ?>
                    </span>
                </button>

                <div class="sc-connect-status" style="margin-top:16px; font-size:13px; color:#4b5563;">

                    <?php if ($show_connected_box && $display_callback) : ?>
                        <div class="sc-result-box">
                            <strong>Your store is now connected to Svelta.</strong><br><br>
                            <span>Orders from this store will be sent to:</span>
                            <code class="sc-result-code">
                                <?php echo esc_html($display_callback); ?>
                            </code>

                            <?php if ($is_admin_test) : ?>

                                <div class="sc-webhook-note">
                                    <strong>Debug (only visible to admins):</strong><br>
                                    API key:&nbsp;
                                    <?php
                                    if ($stored_api_key) {
                                        echo '<code>' . esc_html(simple_connect_mask_key($stored_api_key)) . '</code>';
                                    } else {
                                        echo '<em>not set</em>';
                                    }
                                    ?><br>
                                    Callback URL:&nbsp;
                                    <?php
                                    if ($existing_callback) {
                                        echo '<code>' . esc_html($existing_callback) . '</code>';
                                    } else {
                                        echo '<em>not set</em>';
                                    }
                                    ?><br>
                                    Webhook ID:&nbsp;
                                    <?php
                                    if ($stored_webhook_id) {
                                        echo '<code>' . esc_html($stored_webhook_id) . '</code>';
                                    } else {
                                        echo '<em>not set</em>';
                                    }
                                    ?>
                                </div>

                                <?php if (! empty($webhook_error)) : ?>
                                    <div class="sc-webhook-note sc-webhook-note-error">
                                        Webhook error: <?php echo esc_html($webhook_error); ?>
                                    </div>
                                <?php elseif ($stored_webhook_id) : ?>
                                    <div class="sc-webhook-note">
                                        Webhook for <code>order.created</code> has been created or updated
                                        in WooCommerce → Settings → Advanced → Webhooks.
                                    </div>
                                <?php else : ?>
                                    <div class="sc-webhook-note">
                                        Webhook has not been created yet (no ID stored). After refreshing this
                                        page or visiting another admin page, this message should disappear and
                                        a Webhook ID should appear above.
                                    </div>
                                <?php endif; ?>

                                <div class="sc-disconnect-wrap">
                                    <button type="button"
                                        class="sc-disconnect-btn sc-disconnect-btn-js">
                                        Disconnect from Svelta
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>

            </div>

            <?php if ($is_admin_test) : ?>
                <script>
                    (function($) {
                        $(document).ready(function() {
                            $('.sc-disconnect-btn-js').on('click', function(e) {
                                e.preventDefault();
                                var btn = $(this);
                                if (!confirm('Disconnect this store from Svelta?')) return;

                                btn.prop('disabled', true).text('Disconnecting...');
                                $.post(ajaxurl, {
                                    action: 'svelta_disconnect',
                                    nonce: '<?php echo wp_create_nonce("simple_connect_nonce"); ?>'
                                }, function(resp) {
                                    if (resp && resp.success) {
                                        location.reload();
                                    } else {
                                        alert('Failed to disconnect from Svelta.');
                                        btn.prop('disabled', false).text('Disconnect from Svelta');
                                    }
                                }).fail(function() {
                                    alert('Network error. Please try again.');
                                    btn.prop('disabled', false).text('Disconnect from Svelta');
                                });
                            });
                        });
                    })(jQuery);
                </script>
            <?php endif; ?>

        <?php endif; ?>

    </div>

<?php }

/* ---------------------------------------------------
 * AJAX: Toggle Activation
 * ---------------------------------------------------*/
add_action('wp_ajax_simple_connect_toggle', function () {
    check_ajax_referer('simple_connect_nonce', 'nonce');
    update_option('simple_connect_active', ($_POST['status'] === 'activate'));
    wp_send_json_success();
});

/* ---------------------------------------------------
 * AJAX: Disconnect from Svelta
 * ---------------------------------------------------*/
add_action('wp_ajax_svelta_disconnect', 'simple_connect_svelta_disconnect');

function simple_connect_svelta_disconnect()
{
    check_ajax_referer('simple_connect_nonce', 'nonce');

    if (! current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    // Remember callback URL before deleting it so we can disable webhook.
    $callback_url = get_option(SVELTA_OPTION_CALLBACK_URL);

    // Try to disable matching webhook via data store.
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
                    $wh->set_status('disabled'); // keep it but disable.
                    $wh->save();
                }
            }
        }
    }

    // Clear saved Svelta data.
    delete_option(SVELTA_OPTION_API_KEY);
    delete_option(SVELTA_OPTION_CALLBACK_URL);
    delete_option(SVELTA_OPTION_WEBHOOK_ID);
    delete_option(SVELTA_OPTION_WEBHOOK_LAST_ERROR);

    wp_send_json_success();
}
