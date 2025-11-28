<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared styles (connect card + order card) + connect card UI.
 */

/**
 * Output CSS once and reuse it for:
 * - The API Connect admin card.
 * - The order-side Svelta delivery card.
 */
function simple_connect_styles()
{
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
?>
    <style>
        :root {
            --sv-purple: #7A4BFF;
            --sv-purple-dark: #6539d6;
            --sv-gray-200: #e5e7eb;
            --sv-gray-100: #f3f4f6;
            --sv-gray-50: #fafafa;
            --sv-gray-900: #111827;
            --sv-gray-700: #4b5563;
            --sv-gray-500: #6b7280;
        }

        .sc-connect-area {
            margin: 40px auto;
            max-width: 580px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .sc-card {
            position: relative;
            background: radial-gradient(circle at top, #f5f3ff 0, #ffffff 55%, #f9fafb 100%);
            padding: 26px 26px 22px;
            border-radius: 22px;
            border: 1px solid rgba(148, 163, 184, 0.4);
            box-shadow:
                0 16px 40px rgba(15, 23, 42, 0.14),
                0 0 0 1px rgba(255, 255, 255, 0.6) inset;
            text-align: center;
            overflow: hidden;
        }

        .sc-card::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 0 0, rgba(124, 58, 237, 0.14), transparent 55%),
                radial-gradient(circle at 100% 100%, rgba(79, 70, 229, 0.1), transparent 55%);
            opacity: 0.85;
        }

        .sc-card-inner {
            position: relative;
            z-index: 1;
        }

        .sc-header-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(209, 213, 219, 0.8);
            box-shadow: 0 6px 18px rgba(148, 163, 184, 0.45);
            margin-bottom: 14px;
        }

        .sc-logo {
            max-width: 160px;
            height: auto;
            display: block;
        }

        .sc-title {
            font-size: 19px;
            font-weight: 700;
            margin: 0 0 4px;
            color: var(--sv-gray-900);
            letter-spacing: -0.02em;
        }

        .sc-subtitle {
            font-size: 12px;
            color: var(--sv-gray-500);
            margin: 0;
        }

        .sc-badge-row {
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .sc-badge {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 3px 9px;
            border-radius: 999px;
            border: 1px solid rgba(129, 140, 248, 0.65);
            background: rgba(238, 242, 255, 0.92);
            color: #4f46e5;
            font-weight: 600;
        }

        .sc-status-pill {
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid rgba(209, 213, 219, 0.9);
            background: rgba(255, 255, 255, 0.96);
            color: var(--sv-gray-500);
            white-space: nowrap;
        }

        .sc-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .sc-status-pill.sc-status-connected-pill {
            border-color: rgba(34, 197, 94, 0.6);
            color: #166534;
            background: rgba(240, 253, 250, 0.98);
        }

        .sc-status-pill.sc-status-connected-pill .sc-status-dot {
            background: #22c55e;
        }

        .sc-status-pill.sc-status-disconnected-pill .sc-status-dot {
            background: #a855f7;
        }

        .sc-body {
            margin-top: 18px;
        }

        .sc-desc {
            font-size: 13px;
            color: var(--sv-gray-700);
            margin: 0 0 10px;
        }

        .sc-status-text {
            font-size: 11px;
            color: var(--sv-gray-500);
            margin: 0;
        }

        .sc-status-text span {
            font-weight: 600;
        }

        .sc-status-connected span {
            color: #166534;
        }

        .sc-status-disconnected span {
            color: #4338ca;
        }

        .sc-actions {
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sc-btn-primary {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 24px;
            font-size: 13px;
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
            font-family: inherit;
            background: linear-gradient(135deg, var(--sv-purple), #9b5cff);
            box-shadow: 0 4px 14px rgba(88, 28, 135, 0.3);
            white-space: nowrap;
        }

        .sc-btn-primary:hover {
            background: linear-gradient(135deg, var(--sv-purple-dark), #7c3aed);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(88, 28, 135, 0.35);
        }

        .sc-btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 3px 8px rgba(88, 28, 135, 0.25);
        }

        .sc-btn-primary:disabled {
            opacity: 0.75;
            cursor: default;
            transform: none;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.1);
        }

        .sc-btn-secondary-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 999px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            font-size: 11px;
            font-weight: 500;
            color: #b91c1c;
            cursor: pointer;
            text-decoration: none;
            transition:
                background .15s ease,
                border-color .15s ease,
                color .15s ease,
                box-shadow .15s ease,
                transform .1s ease;
            white-space: nowrap;
        }

        .sc-btn-secondary-danger:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            box-shadow: 0 2px 8px rgba(248, 113, 113, 0.35);
            transform: translateY(-0.5px);
        }

        .sc-btn-secondary-danger:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(248, 113, 113, 0.35);
        }

        .sc-btn-secondary-danger:disabled {
            opacity: 0.65;
            cursor: default;
            box-shadow: none;
            transform: none;
        }

        .sc-result-box {
            margin-top: 16px;
            background: rgba(249, 250, 251, 0.96);
            border: 1px dashed rgba(148, 163, 184, 0.95);
            padding: 10px 12px;
            border-radius: 14px;
            font-size: 12px;
            color: var(--sv-gray-700);
            text-align: left;
        }

        .sc-result-code {
            display: block;
            padding: 5px 8px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-top: 4px;
            overflow-wrap: anywhere;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 11px;
        }

        .sc-webhook-note {
            margin-top: 8px;
            font-size: 11px;
            color: #6b7280;
        }

        .sc-webhook-note-error {
            color: #b91c1c;
        }

        /* ORDER CARD */
        .svelta-order-card-wrapper {
            margin: 10px 0 14px;
        }

        .svelta-order-card {
            padding: 10px 10px 8px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.7);
            background: linear-gradient(135deg, #f9fafb, #ffffff);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .svelta-order-card p {
            margin: 0 0 6px;
            font-size: 12px;
            color: #4b5563;
        }

        .svelta-order-card .svelta-order-id {
            font-weight: 600;
        }

        .svelta-order-card .svelta-status-label {
            font-size: 11px;
            color: #6b7280;
        }

        .svelta-order-card .svelta-status-label strong {
            color: #111827;
        }

        .svelta-order-card .svelta-actions {
            margin-top: 8px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .svelta-order-card .svelta-message {
            margin-top: 6px;
            font-size: 11px;
            color: #6b7280;
        }

        .svelta-order-card .svelta-message-error {
            color: #b91c1c;
        }

        .svelta-order-card .svelta-status-line {
            margin-top: 4px;
        }

        .svelta-order-card .svelta-pill {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 11px;
        }
    </style>
<?php
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

    $status_text_class = $has_saved_connection ? 'sc-status-text sc-status-connected' : 'sc-status-text sc-status-disconnected';
    $status_pill_class = $has_saved_connection ? 'sc-status-pill sc-status-connected-pill' : 'sc-status-pill sc-status-disconnected-pill';

    simple_connect_styles();
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
    </div>
<?php
}
