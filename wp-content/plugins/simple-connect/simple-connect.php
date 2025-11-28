<?php
/*
Plugin Name: Simple Connect Plugin
Description: Connect WooCommerce to Svelta Courier and auto-create/update a single webhook that triggers on order status changes, using Svelta’s callback URL + API key. Adds order-level controls to create/cancel delivery requests and creates a WooCommerce REST API key for Svelta.
Version: 33.1
Author: Railijs Didzis Grieznis
*/

if (! defined('ABSPATH')) {
    exit;
}

// Base plugin paths
if (! defined('SC_SVELTA_PLUGIN_FILE')) {
    define('SC_SVELTA_PLUGIN_FILE', __FILE__);
}
if (! defined('SC_SVELTA_PLUGIN_DIR')) {
    define('SC_SVELTA_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (! defined('SC_SVELTA_PLUGIN_URL')) {
    define('SC_SVELTA_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Load all plugin parts
require_once SC_SVELTA_PLUGIN_DIR . 'includes/constants.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/helpers.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/rest-keys.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/webhooks.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/statuses.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/ui-connect-card.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/admin-page.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/orders.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/delivery-api.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/ajax-delivery.php';
require_once SC_SVELTA_PLUGIN_DIR . 'includes/ajax-disconnect.php';
