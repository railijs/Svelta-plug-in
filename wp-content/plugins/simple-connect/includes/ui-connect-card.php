<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared styles (connect card + order card) + connect card UI.
 */

/**
 * Ensure our admin CSS is enqueued.
 * (Used both on the API Connect page and the order edit screen.)
 */
function simple_connect_styles()
{
    wp_enqueue_style(
        'simple-connect-admin',
        SC_SVELTA_PLUGIN_URL . 'assets/css/simple-connect-admin.css',
        array(),
        '33.1'
    );
}

/**
 * Frontend / admin connect card UI.
 *
 * Used on the API Connect admin page.
 *
 * @param bool $is_admin_test Whether we are in admin context (enables Disconnect button + JS).
 */
function simple_connect_frontend_ui($is_admin_test = false)
{
    $auth_url          = simple_connect_get_svelta_start_url();
    $existing_callback = get_option(SVELTA_OPTION_CALLBACK_URL);
    $webhook_error     = get_option(SVELTA_OPTION_WEBHOOK_LAST_ERROR);
    $stored_webhook_id = (int) get_option(SVELTA_OPTION_WEBHOOK_ID);

    $has_saved_connection = simple_connect_has_svelta_connection();
    $just_connected       = isset($_GET['api_key'], $_GET['callback_url']);

    if ($just_connected) {
        $display_callback = esc_url_raw(wp_unslash($_GET['callback_url']));
    } else {
        $display_callback = $existing_callback;
    }

    $show_connected_box = ($just_connected || $has_saved_connection) && ! empty($display_callback);

    $status_text_class = $has_saved_connection
        ? 'sc-status-text sc-status-connected'
        : 'sc-status-text sc-status-disconnected';

    $status_pill_class = $has_saved_connection
        ? 'sc-status-pill sc-status-connected-pill'
        : 'sc-status-pill sc-status-disconnected-pill';

    // Enqueue shared CSS.
    simple_connect_styles();

    // If we're on the admin page (API Connect), enqueue the JS for the disconnect button.
    if ($is_admin_test) {
        wp_enqueue_script(
            'simple-connect-admin',
            SC_SVELTA_PLUGIN_URL . 'assets/js/simple-connect-admin.js',
            array('jquery'),
            '33.1',
            true
        );

        // Pass nonce + ajax URL to JS.
        wp_localize_script(
            'simple-connect-admin',
            'SimpleConnectAdmin',
            array(
                'ajaxUrl'         => admin_url('admin-ajax.php'),
                'disconnectNonce' => wp_create_nonce('simple_connect_nonce'),
            )
        );
    }
?>
    <div class="sc-connect-area">
        <div class="sc-card">
            <div class="sc-card-inner">

                <div class="sc-header-logo">
                    <img class="sc-logo"
                        src="https://www.svelta.io/wp-content/uploads/2024/08/Svelta-ltd-01-1024x301.png"
                        alt="Svelta logo">
                </div>

                <h2 class="sc-title">Svelta order forwarding</h2>
                <p class="sc-subtitle">Connect your WooCommerce orders to Svelta Courier.</p>

                <div class="sc-badge-row">
                    <span class="sc-badge">WooCommerce</span>
                    <span class="<?php echo esc_attr($status_pill_class); ?>">
                        <span class="sc-status-dot"></span>
                        <span><?php echo $has_saved_connection ? 'Connected' : 'Not connected'; ?></span>
                    </span>
                </div>

                <div class="sc-body">
                    <p class="sc-desc">
                        Link this store to your Svelta account so that new WooCommerce orders are forwarded
                        automatically for delivery. You can reconnect at any time if your Svelta settings change.
                    </p>

                    <p class="<?php echo esc_attr($status_text_class); ?>">
                        Status:
                        <span>
                            <?php echo $has_saved_connection ? 'Connected to Svelta' : 'Not connected yet'; ?>
                        </span>
                    </p>

                    <div class="sc-actions">
                        <button class="sc-btn-primary"
                            type="button"
                            onclick="window.location.href='<?php echo esc_url($auth_url); ?>'">
                            <?php echo $show_connected_box ? 'Reconnect to Svelta' : 'Connect to Svelta'; ?>
                        </button>

                        <?php if ($is_admin_test && $has_saved_connection) : ?>
                            <button type="button"
                                class="sc-btn-secondary-danger sc-disconnect-btn-js">
                                Disconnect from Svelta
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($show_connected_box && $display_callback) : ?>
                        <div class="sc-result-box">
                            <strong>Your store is connected to Svelta.</strong><br>
                            Orders from this store will be sent to:
                            <code class="sc-result-code">
                                <?php echo esc_html($display_callback); ?>
                            </code>

                            <?php if (! empty($webhook_error)) : ?>
                                <div class="sc-webhook-note sc-webhook-note-error">
                                    There was a problem setting up the connection inside WooCommerce:
                                    <?php echo esc_html($webhook_error); ?>
                                    <br>
                                    Please check your WooCommerce installation and try reconnecting.
                                </div>
                            <?php elseif ($stored_webhook_id) : ?>
                                <div class="sc-webhook-note">
                                    New orders will be forwarded automatically to Svelta.
                                    You can review the Svelta webhook in
                                    WooCommerce → Settings → Advanced → Webhooks.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
}
