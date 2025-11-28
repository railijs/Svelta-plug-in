<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Custom Svelta statuses (Pending pickup / Pending delivery)
 * These are extra order statuses that only become available
 * when the store is connected to Svelta.
 */

add_action('init', 'simple_connect_register_custom_statuses');
function simple_connect_register_custom_statuses()
{
    if (! simple_connect_has_svelta_connection()) {
        return;
    }

    register_post_status(
        'wc-pending-pickup',
        array(
            'label'                     => _x('Pending pickup', 'Order status', 'simple-connect'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Pending pickup <span class="count">(%s)</span>',
                'Pending pickup <span class="count">(%s)</span>',
                'simple-connect'
            ),
        )
    );

    register_post_status(
        'wc-pending-delivery',
        array(
            'label'                     => _x('Pending delivery', 'Order status', 'simple-connect'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Pending delivery <span class="count">(%s)</span>',
                'Pending delivery <span class="count">(%s)</span>',
                'simple-connect'
            ),
        )
    );
}

/**
 * Insert our two custom Svelta statuses into the WooCommerce status dropdown.
 */
add_filter('wc_order_statuses', 'simple_connect_add_custom_statuses');
function simple_connect_add_custom_statuses($statuses)
{
    if (! simple_connect_has_svelta_connection()) {
        return $statuses;
    }

    $new_statuses = array();

    foreach ($statuses as $key => $label) {
        $new_statuses[$key] = $label;

        // Insert our statuses right after “Processing”.
        if ('wc-processing' === $key) {
            $new_statuses['wc-pending-pickup']   = _x('Pending pickup', 'Order status', 'simple-connect');
            $new_statuses['wc-pending-delivery'] = _x('Pending delivery', 'Order status', 'simple-connect');
        }
    }

    // Safety: if "Processing" wasn't found for some reason, make sure they still appear.
    if (! isset($new_statuses['wc-pending-pickup'])) {
        $new_statuses['wc-pending-pickup'] = _x('Pending pickup', 'Order status', 'simple-connect');
    }
    if (! isset($new_statuses['wc-pending-delivery'])) {
        $new_statuses['wc-pending-delivery'] = _x('Pending delivery', 'Order status', 'simple-connect');
    }

    return $new_statuses;
}
