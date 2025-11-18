<?php
/*
Plugin Name: Simple Connect Plugin
Description: WooCommerce admin activation + frontend Connect UI with WooCommerce REST API key creation and Svelta credential exchange (2-step GUID flow). Clean, simple Svelta-inspired design.
Version: 15.0
Author: Railijs Didzis Grieznis
*/

if (!defined('ABSPATH')) exit;

/* ---------------------------------------------------
 * AUTH URL VARIABLE (boss requested, not used yet)
 * ---------------------------------------------------*/
$SVELTA_AUTH_URL = "https://dev-rgqqxqhvtdhub1ou.us.auth0.com/u/login?client_id=Ekpx91BKIfP3bjzRHf3NtSMEylfABcJi";

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
{ ?>
    <div class="wrap">
        <h1>API Connect</h1>
        <p>Enable or disable the Svelta connection system on the frontend.</p>
        <hr>
        <?php simple_connect_admin_ui(); ?>

        <hr style="margin:40px 0;">
        <h2>Admin Test Area</h2>
        <p>This simulates the frontend Connect UI.</p>
        <?php simple_connect_frontend_ui(true); ?>
    </div>
<?php }

/* ---------------------------------------------------
 * FRONTEND (SHOW ON HOMEPAGE)
 * ---------------------------------------------------*/
add_filter('the_content', function ($content) {
    if (!is_front_page() && !is_home()) return $content;
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
            --sv-teal: #15d7c6;
            --sv-gray-200: #e5e7eb;
            --sv-gray-50: #fafafa;
            --sv-gray-900: #1f2937;
            --sv-gray-700: #4b5563;
        }

        .sc-btn {
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            color: #fff;
            cursor: pointer;
            transition: .2s ease;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .sc-btn:hover {
            opacity: .9;
        }

        .sc-btn-purple {
            background: var(--sv-purple);
        }

        .sc-btn-teal {
            background: var(--sv-teal);
        }

        .sc-btn-red {
            background: #ef4444;
        }

        .sc-btn-ghost {
            background: transparent;
            color: var(--sv-purple);
            border: 1px solid var(--sv-purple);
        }

        .sc-connect-area {
            margin: 30px auto;
            text-align: center;
            max-width: 600px;
        }

        .sc-card {
            background: var(--sv-gray-50);
            padding: 32px 28px;
            border-radius: 16px;
            border: 1px solid var(--sv-gray-200);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        }

        .sc-logo {
            width: 70%;
            margin: 0 auto 18px auto;
            display: block;
        }

        .sc-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--sv-gray-900);
        }

        .sc-desc {
            font-size: 14px;
            color: var(--sv-gray-700);
            margin-bottom: 18px;
        }

        .sc-error {
            padding: 12px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
        }

        .sc-admin-card {
            background: #fff;
            padding: 28px;
            border-radius: 12px;
            border: 1px solid var(--sv-gray-200);
            max-width: 600px;
        }

        .sc-status-badge {
            margin: 10px 0;
            padding: 10px 16px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            border: 1px solid var(--sv-gray-200);
        }

        .sc-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .sc-status-active {
            background: #dcfce7;
        }

        .sc-status-inactive {
            background: var(--sv-gray-50);
        }

        .sc-status-active .sc-status-dot {
            background: #22c55e;
        }

        .sc-status-inactive .sc-status-dot {
            background: #4b5563;
        }

        .sc-result-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 18px;
            border-radius: 12px;
            text-align: left;
        }

        .sc-result-code {
            display: block;
            padding: 8px;
            background: #fff;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin-top: 4px;
            overflow-wrap: anywhere;
        }

        .sc-svelta-response {
            background: #f3faf8;
            border: 1px solid #cce7df;
            padding: 16px;
            border-radius: 12px;
            margin-top: 12px;
            text-align: left;
        }

        .sc-svelta-response-title {
            font-size: 16px;
            color: #0f5132;
            font-weight: 600;
        }

        .sc-svelta-response-list {
            margin-top: 10px;
            padding-left: 18px;
            color: #084c33;
            font-size: 14px;
        }

        .sc-input {
            width: 100%;
            max-width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--sv-gray-200);
            font-size: 14px;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            box-sizing: border-box;
        }

        .sc-input:focus {
            outline: none;
            border-color: var(--sv-purple);
            box-shadow: 0 0 0 2px rgba(122, 75, 255, 0.15);
            background: #fff;
        }

        .sc-input-label {
            text-align: left;
            font-size: 13px;
            font-weight: 500;
            color: var(--sv-gray-700);
            margin-bottom: 4px;
        }

        .sc-input-help {
            text-align: left;
            font-size: 12px;
            color: var(--sv-gray-700);
            margin-top: 4px;
        }

        .sc-actions-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 16px;
        }

        @media (max-width: 480px) {
            .sc-actions-row {
                flex-direction: column;
                align-items: stretch;
            }

            .sc-actions-row .sc-btn {
                width: 100%;
            }
        }
    </style>
<?php }

/* ---------------------------------------------------
 * ADMIN UI
 * ---------------------------------------------------*/
function simple_connect_admin_ui()
{
    $is_active = get_option('simple_connect_active', false);
    simple_connect_styles(); ?>

    <div class="sc-admin-card">
        <h2>Connection System Control</h2>

        <div class="sc-status-badge <?php echo $is_active ? 'sc-status-active' : 'sc-status-inactive'; ?>">
            <span class="sc-status-dot"></span>
            <span><?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
        </div>

        <div style="margin-top:20px;">
            <?php if (!$is_active): ?>
                <button class="sc-btn sc-btn-teal" onclick="toggleSC('activate')">Activate</button>
            <?php else: ?>
                <button class="sc-btn sc-btn-red" onclick="toggleSC('deactivate')">Deactivate</button>
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
 * FRONTEND CONNECT UI (2-step GUID flow)
 *
 * Step 1: "Open Svelta Connection" → user logs in on Svelta side + sees GUID.
 * Step 2: User pastes GUID into field → click "Finish Connection".
 *         Plugin creates WC API key + sends GUID & secret to Svelta.
 * ---------------------------------------------------*/
function simple_connect_frontend_ui($is_admin_test = false)
{
    $is_active = get_option('simple_connect_active', false);
    $nonce     = wp_create_nonce('simple_connect_nonce');

    // Store host for Start URL
    $store_url  = home_url();
    $store_host = parse_url($store_url, PHP_URL_HOST) ?: $store_url;
    $svelta_start_url = 'https://staging.clientapi.sveltacourier.com/api/WooCommerceAuth/Start?store_url='
        . rawurlencode($store_host);

    simple_connect_styles(); ?>

    <div class="sc-connect-area">

        <?php if (!$is_active && !$is_admin_test): ?>
            <div class="sc-error">Connection system is inactive. Please contact admin.</div>
        <?php else: ?>

            <div class="sc-card">

                <img class="sc-logo" src="https://www.svelta.io/wp-content/uploads/2024/08/Svelta-ltd-01-1024x301.png" alt="Svelta logo">

                <div class="sc-title">Connect to Svelta</div>

                <div class="sc-desc">
                    Step 1: Open Svelta in a new tab and log in.<br>
                    Step 2: Copy the GUID shown by Svelta and paste it below.<br>
                    Step 3: Click "Finish Connection" to create a WooCommerce API key and send it to Svelta.
                </div>

                <div class="sc-actions-row">
                    <a class="sc-btn sc-btn-ghost"
                        href="<?php echo esc_url($svelta_start_url); ?>"
                        target="_blank" rel="noopener noreferrer">
                        Open Svelta Connection
                    </a>

                    <button class="sc-btn sc-btn-purple sc-connect-btn" type="button">
                        Finish Connection
                    </button>
                </div>

                <div style="margin-top:18px; text-align:left;">
                    <div class="sc-input-label">Svelta GUID</div>
                    <input type="text" class="sc-input sc-guid-input" placeholder="Paste GUID from Svelta here">
                    <div class="sc-input-help">
                        After logging in on Svelta, they will show you a GUID for this store. Paste it exactly as shown.
                    </div>
                </div>

                <div class="sc-connect-status" style="margin-top:16px; font-size:14px; color:#4b5563;"></div>

            </div>

            <script>
                (function($) {
                    $(document).ready(function() {

                        const btn = $('.sc-connect-btn');
                        const guidEl = $('.sc-guid-input');
                        const status = $('.sc-connect-status');

                        // Minimal pretty formatter for Svelta JSON response (Option A)
                        function formatSveltaResponse(obj) {
                            if (!obj || typeof obj !== 'object') {
                                return '';
                            }

                            let html = `
                                <div class="sc-svelta-response">
                                    <div class="sc-svelta-response-title">Svelta Response</div>
                                    <ul class="sc-svelta-response-list">
                            `;

                            for (const key in obj) {
                                if (!Object.prototype.hasOwnProperty.call(obj, key)) continue;
                                let label = key.replace(/_/g, ' ');
                                label = label.charAt(0).toUpperCase() + label.slice(1);
                                html += `<li><strong>${label}:</strong> ${obj[key]}</li>`;
                            }

                            html += `
                                    </ul>
                                </div>
                            `;

                            return html;
                        }

                        btn.on('click', function() {

                            const guid = (guidEl.val() || '').trim();

                            if (!guid) {
                                status.html('<div class="sc-error">Please paste the Svelta GUID before finishing the connection.</div>');
                                guidEl.focus();
                                return;
                            }

                            btn.prop('disabled', true).text('Connecting...');
                            status.text('Creating WooCommerce API key and sending credentials to Svelta...');

                            $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
                                action: 'svelta_finish_connect',
                                nonce: '<?php echo $nonce; ?>',
                                svelta_guid: guid
                            }, function(response) {

                                if (response && response.success) {

                                    const wc = response.data.wc;
                                    const svelta = response.data.svelta;
                                    const prettySvelta = formatSveltaResponse(svelta.response || {});

                                    status.html(`
                                        <div class="sc-result-box">
                                            <strong style="font-size:16px;">Connection Successful</strong><br><br>

                                            <strong>Store URL:</strong>
                                            <span style="display:block; margin-top:4px;">${svelta.store_url}</span><br>

                                            <strong>Svelta GUID:</strong>
                                            <code class="sc-result-code">
                                                ${svelta.client_guid}
                                            </code><br>

                                            <strong>WooCommerce API Key (created):</strong><br>
                                            <span style="font-size:13px; color:#6b7280;">Permissions: read_write</span><br><br>

                                            <strong>Consumer Key:</strong>
                                            <code class="sc-result-code">
                                                ${wc.consumer_key}
                                            </code><br>

                                            <strong>Consumer Secret:</strong>
                                            <code class="sc-result-code">
                                                ${wc.consumer_secret}
                                            </code><br><br>

                                            <strong>Sent to Svelta as:</strong><br>
                                            <span style="font-size:13px; color:#6b7280;">
                                                client_id = Svelta GUID<br>
                                                client_secret = WooCommerce Consumer Secret
                                            </span>
                                            ${prettySvelta}
                                        </div>
                                    `);

                                    btn.text('Connected to Svelta').css('opacity', '0.7');

                                } else {
                                    let msg = 'Failed to connect.';
                                    if (response && response.data && response.data.message) {
                                        msg += ' ' + response.data.message;
                                    }
                                    status.html('<div class="sc-error">' + msg + '</div>');
                                    btn.prop('disabled', false).text('Finish Connection');
                                }

                            }).fail(function() {
                                status.html('<div class="sc-error">Network error. Please try again.</div>');
                                btn.prop('disabled', false).text('Finish Connection');
                            });

                        });

                    });
                })(jQuery);
            </script>

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
 * AJAX: Finish Connect
 * 1) Take GUID from user (pasted after login)
 * 2) Create WooCommerce REST API key (read_write)
 * 3) Send GUID + secret to Svelta ReceivePluginCredentials
 * 4) Return everything to frontend
 * ---------------------------------------------------*/
add_action('wp_ajax_svelta_finish_connect', 'svelta_finish_connect');

function svelta_finish_connect()
{
    check_ajax_referer('simple_connect_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not logged in.']);
    }

    $guid_raw = isset($_POST['svelta_guid']) ? wp_unslash($_POST['svelta_guid']) : '';
    $guid     = trim(sanitize_text_field($guid_raw));

    if (empty($guid)) {
        wp_send_json_error(['message' => 'No Svelta GUID provided.']);
    }

    /* ---- Ensure WooCommerce is loaded ---- */
    if (!function_exists('WC')) {
        $wc_main = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
        if (file_exists($wc_main)) {
            include_once $wc_main;
        }
    }

    if (!function_exists('WC')) {
        wp_send_json_error(['message' => 'WooCommerce could not be loaded.']);
    }

    if (!function_exists('wc_rand_hash')) {
        include_once WP_PLUGIN_DIR . '/woocommerce/includes/wc-core-functions.php';
    }

    if (!function_exists('wc_api_hash')) {
        include_once WP_PLUGIN_DIR . '/woocommerce/includes/wc-api-functions.php';
    }

    global $wpdb;

    // Detect WooCommerce API key table (legacy vs new)
    $table_legacy = $wpdb->prefix . 'woocommerce_api_keys';
    $table_new    = $wpdb->prefix . 'wc_api_keys';
    $table        = null;

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_legacy)) === $table_legacy) {
        $table = $table_legacy;
    } elseif ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_new)) === $table_new) {
        $table = $table_new;
    } else {
        wp_send_json_error(['message' => 'WooCommerce API key table not found.']);
    }

    /* ---------------------------------------------------
     * STEP 1: Generate WooCommerce REST API key (read_write)
     * ---------------------------------------------------*/
    $consumer_key    = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();

    $insert = $wpdb->insert(
        $table,
        [
            'user_id'         => get_current_user_id(),
            'description'     => 'Svelta Integration API Key',
            'permissions'     => 'read_write',
            'consumer_key'    => wc_api_hash($consumer_key),
            'consumer_secret' => $consumer_secret,
            'truncated_key'   => substr($consumer_key, -7),
            'last_access'     => null,
        ]
    );

    if (!$insert) {
        wp_send_json_error([
            'message' => 'Database insert failed.',
            'error'   => $wpdb->last_error,
            'query'   => $wpdb->last_query,
        ]);
    }

    /* ---------------------------------------------------
     * STEP 2: Send GUID + secret to Svelta
     * ---------------------------------------------------*/
    $store_url  = home_url();
    $store_host = parse_url($store_url, PHP_URL_HOST) ?: $store_url;

    $receive_url = "https://staging.clientapi.sveltacourier.com/api/WooCommerceAuth/ReceivePluginCredentials";

    $payload = [
        'client_id'     => $guid,
        'client_secret' => $consumer_secret,
    ];

    $receive_response = wp_remote_post($receive_url, [
        'method'  => 'POST',
        'headers' => [
            'X-api-key'    => 'a2a7853f-d5d4-44e3-a7c2-0eea20172e30',
            'Content-Type' => 'application/json',
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 20,
    ]);

    if (is_wp_error($receive_response)) {
        wp_send_json_error([
            'message' => 'Svelta ReceivePluginCredentials request failed: ' . $receive_response->get_error_message(),
        ]);
    }

    $receive_body = wp_remote_retrieve_body($receive_response);
    $receive_json = json_decode($receive_body, true);

    wp_send_json_success([
        'wc' => [
            'consumer_key'    => $consumer_key,
            'consumer_secret' => $consumer_secret,
        ],
        'svelta' => [
            'store_url'   => $store_host,
            'client_guid' => $guid,
            'response'    => $receive_json,
        ],
    ]);
}
