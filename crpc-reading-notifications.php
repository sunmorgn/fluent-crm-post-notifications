<?php

/**
 * Plugin Name: CRPC Reading Program Notifications
 * Description: Automatically sends FluentCRM emails to subscribers when a post is published in a specific category.
 * Version: 1.0.0
 * Author: City Reformed
 * Text Domain: crpc-notifications
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CRPC_NOTIFICATIONS_PATH', plugin_dir_path(__FILE__));
define('CRPC_NOTIFICATIONS_URL', plugin_dir_url(__FILE__));

// Include Admin Settings
if (is_admin()) {
    require_once CRPC_NOTIFICATIONS_PATH . 'includes/admin-settings.php';
}

// Include Logic
require_once CRPC_NOTIFICATIONS_PATH . 'includes/notification-logic.php';
