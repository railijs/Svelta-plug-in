<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Delivery request status / create / cancel.
 * These AJAX handlers are called by the order card’s JS.
 */

add_action('wp_ajax_svelta_order_status', 'simple_connect_ajax_order_status');
function simple_connect_ajax_order_status()
{
    check_ajax_referer('simple_connect_order_nonce', 'nonce');

    if (! current_user_can('manage_woocommerce') && ! current_user_can('manage_options')) {
        wp_send_json_error(
            array(
                'message' => 'You do not have permission to view Svelta delivery status.',
            )
        );
    }

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    if ($order_id <= 0) {
        wp_send_json_error(
            array(
                'message' => 'Invalid order ID.',
            )
        );
    }

    $error = '';
    $data  = simple_connect_call_svelta_delivery_endpoint(SVELTA_BACKEND_DR_STATUS_URL, $order_id, array(), $error);
    $dr_id = '';
    $has_dr = false;

    if (null === $data) {
        wp_send_json_error(
            array(
                'message' => 'Failed to talk to Svelta: ' . $error,
            )
        );
    }

    // Expecting something like { "drId": "12345", ... } if exists.
    if (! empty($data['drId'])) {
        $dr_id  = (string) $data['drId'];
        $has_dr = true;
        update_post_meta($order_id, '_svelta_dr_id', $dr_id);
    } else {
        delete_post_meta($order_id, '_svelta_dr_id');
    }

    wp_send_json_success(
        array(
            'has_dr' => $has_dr,
            'dr_id'  => $dr_id,
        )
    );
}

add_action('wp_ajax_svelta_order_create', 'simple_connect_ajax_order_create');
function simple_connect_ajax_order_create()
{
    check_ajax_referer('simple_connect_order_nonce', 'nonce');

    if (! current_user_can('manage_woocommerce') && ! current_user_can('manage_options')) {
        wp_send_json_error(
            array(
                'message' => 'You do not have permission to create Svelta deliveries.',
            )
        );
    }

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    if ($order_id <= 0) {
        wp_send_json_error(
            array(
                'message' => 'Invalid order ID.',
            )
        );
    }

    $error = '';
    $data  = simple_connect_call_svelta_delivery_endpoint(SVELTA_BACKEND_DR_CREATE_URL, $order_id, array(), $error);

    if (null === $data) {
        wp_send_json_error(
            array(
                'message' => 'Failed to create delivery on Svelta: ' . $error,
            )
        );
    }

    if (empty($data['drId'])) {
        wp_send_json_error(
            array(
                'message' => 'Svelta did not return a delivery ID.',
            )
        );
    }

    $dr_id = (string) $data['drId'];
    update_post_meta($order_id, '_svelta_dr_id', $dr_id);

    wp_send_json_success(
        array(
            'dr_id' => $dr_id,
        )
    );
}

add_action('wp_ajax_svelta_order_cancel', 'simple_connect_ajax_order_cancel');
function simple_connect_ajax_order_cancel()
{
    check_ajax_referer('simple_connect_order_nonce', 'nonce');

    if (! current_user_can('manage_woocommerce') && ! current_user_can('manage_options')) {
        wp_send_json_error(
            array(
                'message' => 'You do not have permission to cancel Svelta deliveries.',
            )
        );
    }

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    if ($order_id <= 0) {
        wp_send_json_error(
            array(
                'message' => 'Invalid order ID.',
            )
        );
    }

    $error = '';
    $data  = simple_connect_call_svelta_delivery_endpoint(SVELTA_BACKEND_DR_CANCEL_URL, $order_id, array(), $error);

    if (null === $data) {
        wp_send_json_error(
            array(
                'message' => 'Failed to cancel delivery on Svelta: ' . $error,
            )
        );
    }

    delete_post_meta($order_id, '_svelta_dr_id');

    wp_send_json_success(
        array(
            'message' => 'Delivery request cancelled on Svelta.',
        )
    );
}
