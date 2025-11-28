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

    simple_connect_styles();

    // Render the card wrapper; JS will move it into the sidebar.
    echo '<div id="svelta-order-card-wrapper" class="svelta-order-card-wrapper">';
    simple_connect_render_order_card_inner($order_id);
    echo '</div>';
?>
    <script>
        (function($) {
            $(document).ready(function() {
                var $wrap = $('#svelta-order-card-wrapper');
                if (!$wrap.length) return;

                // Right sidebar container.
                var $sidebarSortables = $('#postbox-container-1 .meta-box-sortables');
                if (!$sidebarSortables.length) return;

                // Try to place after "Order actions" meta box.
                var $orderActions = $('#woocommerce-order-actions');
                if ($orderActions.length) {
                    $orderActions.after($wrap);
                } else {
                    // Fallback: put at the top of sidebar.
                    $sidebarSortables.prepend($wrap);
                }
            });
        })(jQuery);
    </script>
    <?php
}

/**
 * Inner card HTML + JS (called by the wrapper).
 * The card:
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
            <p class="svelta-message">Go to <strong>WooCommerce → API Connect</strong> to connect, then return here.</p>
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
                <strong><span class="svelta-dr-status-text"><?php echo $order_meta ? 'Checking…' : 'Checking…'; ?></span></strong>
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

    <script>
        (function($) {
            $(document).ready(function() {
                var $card = $('.svelta-order-card[data-order-id="<?php echo esc_js($order_id); ?>"]');
                if (!$card.length) return;

                var orderId = $card.data('order-id');
                var nonce = $card.data('nonce');
                var $status = $card.find('.svelta-dr-status-text');
                var $statusLine = $card.find('.svelta-status-line');
                var $pill = $card.find('.svelta-dr-id-pill');
                var $msgMain = $card.find('.svelta-message-main');
                var $msgError = $card.find('.svelta-message-error');
                var $btnCreate = $card.find('.svelta-create-btn');
                var $btnCancel = $card.find('.svelta-cancel-btn');

                function setLoading(message) {
                    $msgMain.text(message).show();
                    $msgError.hide().text('');
                    $btnCreate.prop('disabled', true);
                    $btnCancel.prop('disabled', true);
                }

                function clearLoading() {
                    $btnCreate.prop('disabled', false);
                    $btnCancel.prop('disabled', false);
                }

                function showError(message) {
                    $msgError.text(message).show();
                }

                function updateUIHasDR(drId) {
                    $status.text('Created');
                    $pill.text('DR ID: ' + drId);
                    $statusLine.show();
                    $btnCreate.hide();
                    $btnCancel.show();
                    $msgMain.text('This order already has a delivery request on Svelta.');
                }

                function updateUINoDR() {
                    $status.text('Not created yet');
                    $statusLine.hide();
                    $btnCancel.hide();
                    $btnCreate.show();
                    $msgMain.text('You can manually create a delivery request for this order.');
                }

                // Initial status check: ask Svelta if a delivery request exists.
                setLoading('Checking Svelta delivery status…');
                $.post(ajaxurl, {
                    action: 'svelta_order_status',
                    nonce: nonce,
                    order_id: orderId
                }, function(resp) {
                    clearLoading();
                    if (!resp || !resp.success) {
                        showError(resp && resp.data && resp.data.message ?
                            resp.data.message :
                            'Could not check delivery status on Svelta.');
                        $status.text('Unknown');
                        return;
                    }

                    if (resp.data && resp.data.has_dr && resp.data.dr_id) {
                        updateUIHasDR(resp.data.dr_id);
                    } else {
                        updateUINoDR();
                    }
                }).fail(function() {
                    clearLoading();
                    showError('Network error while talking to Svelta.');
                    $status.text('Unknown');
                });

                // Create button: manually create a delivery request.
                $btnCreate.on('click', function(e) {
                    e.preventDefault();
                    if (!confirm('Create a delivery request on Svelta for this order?')) return;

                    setLoading('Creating delivery request on Svelta…');
                    $.post(ajaxurl, {
                        action: 'svelta_order_create',
                        nonce: nonce,
                        order_id: orderId
                    }, function(resp) {
                        clearLoading();
                        if (!resp || !resp.success) {
                            showError(resp && resp.data && resp.data.message ?
                                resp.data.message :
                                'Could not create delivery request on Svelta.');
                            return;
                        }

                        if (resp.data && resp.data.dr_id) {
                            updateUIHasDR(resp.data.dr_id);
                        } else {
                            showError('Svelta did not return a delivery ID.');
                        }
                    }).fail(function() {
                        clearLoading();
                        showError('Network error while talking to Svelta.');
                    });
                });

                // Cancel button: cancel an existing delivery request.
                $btnCancel.on('click', function(e) {
                    e.preventDefault();
                    if (!confirm('Cancel the existing delivery request on Svelta for this order?')) return;

                    setLoading('Cancelling delivery request on Svelta…');
                    $.post(ajaxurl, {
                        action: 'svelta_order_cancel',
                        nonce: nonce,
                        order_id: orderId
                    }, function(resp) {
                        clearLoading();
                        if (!resp || !resp.success) {
                            showError(resp && resp.data && resp.data.message ?
                                resp.data.message :
                                'Could not cancel delivery request on Svelta.');
                            return;
                        }

                        updateUINoDR();
                    }).fail(function() {
                        clearLoading();
                        showError('Network error while talking to Svelta.');
                    });
                });
            });
        })(jQuery);
    </script>
<?php
}
