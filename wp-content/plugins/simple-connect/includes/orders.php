<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Order card rendering (main hook + move to sidebar).
 * Adds a Svelta card to the order edit screen and moves it
 * into the right sidebar between "Order actions" and "Order attribution".
 */

add_action('woocommerce_admin_order_data_after_order_details', 'simple_connect_render_order_panel');

/**
 * Wrapper that inserts the card into the order edit screen.
 */
function simple_connect_render_order_panel($order)
{
    if (! $order) {
        return;
    }

    $post = get_post($order->get_id());
    if (! $post) {
        return;
    }

    $order_id = $post->ID;

    // Shared CSS.
    simple_connect_styles();

    // JS that moves the card into the sidebar + handles status/create/cancel.
    wp_enqueue_script(
        'simple-connect-order',
        SC_SVELTA_PLUGIN_URL . 'assets/js/simple-connect-order.js',
        array('jquery'),
        '33.1',
        true
    );

    // Render the card wrapper. JS will move it into the sidebar.
    echo '<div id="svelta-order-card-wrapper" class="svelta-order-card-wrapper">';
    simple_connect_render_order_card_inner($order_id);
    echo '</div>';
}

/**
 * Inner card HTML (called by the wrapper).
 * The JS file:
 * - On load: asks Svelta if a delivery request already exists (Status).
 * - If exists: shows "Created" + DR ID + Cancel button.
 * - If not: shows "Not created yet" + Create button.
 */
function simple_connect_render_order_card_inner($order_id)
{
    // If store is not connected, show a simple message.
    if (! simple_connect_has_svelta_connection()) {
?>
        <div class="svelta-order-card">
            <p>This store is not connected to Svelta yet.</p>
            <p class="svelta-message">
                Go to <strong>WooCommerce → API Connect</strong> to connect, then return here.
            </p>
        </div>
    <?php
        return;
    }

    $nonce      = wp_create_nonce('simple_connect_order_nonce');
    $order_meta = get_post_meta($order_id, '_svelta_dr_id', true);
    ?>
    <div class="svelta-order-card"
        data-order-id="<?php echo esc_attr($order_id); ?>"
        data-nonce="<?php echo esc_attr($nonce); ?>">

        <p>
            <span class="svelta-order-id">Order #<?php echo esc_html($order_id); ?></span><br>
            <span class="svelta-status-label">
                Svelta delivery request:
                <strong><span class="svelta-dr-status-text">
                        <?php echo $order_meta ? 'Checking…' : 'Checking…'; ?>
                    </span></strong>
            </span>
        </p>

        <div class="svelta-status-line" style="display:none;">
            <span class="svelta-pill svelta-dr-id-pill"></span>
        </div>

        <div class="svelta-actions">
            <button type="button"
                class="sc-btn-primary svelta-create-btn"
                style="display:none;">
                Create delivery request
            </button>

            <button type="button"
                class="sc-btn-secondary-danger svelta-cancel-btn"
                style="display:none;">
                Cancel delivery request
            </button>
        </div>

        <p class="svelta-message svelta-message-main">
            Talking to Svelta…
        </p>
        <p class="svelta-message svelta-message-error" style="display:none;"></p>
    </div>
<?php
}
