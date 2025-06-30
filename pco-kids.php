<?php
/**
 * Plugin Name: Eastlake Check-In SMS Dashboard
 * Description: Live roll-call of currently checked-in kids with one-click SMS to parents.
 * Version:     0.1.0
 * Author:      Eastlake Church Tech
 */

defined('ABSPATH') || exit;
require_once plugin_dir_path(__FILE__) . 'admin/pco-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/pco-kids-api.php';

/* Admin page */
add_action('admin_menu', ['ELCIS\\Dashboard_Page', 'register']);

/* Autoload simple classes */
spl_autoload_register(function ($c) {
    if (strpos($c, 'ELCIS_') === 0) {
        require_once plugin_dir_path(__FILE__) . str_replace(
            ['ELCIS_', '\\'], ['', '/'],
            strtolower($c)
        ) . '.php';
    }
});

/* Settings + capability */
register_activation_hook(__FILE__, function () {
    $kids_leader = get_role('kids_leader');
    if (!$kids_leader) {
        add_role('kids_leader', 'Kids Leader', ['read' => true]);
        $kids_leader = get_role('kids_leader');
    }
    if ($kids_leader) {
        $kids_leader->add_cap('manage_checkins_sms');
    }

    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_checkins_sms');
    }
});
add_action('admin_init', function () {
    register_setting('elcis_settings', 'elcis_pco_app_id');
    register_setting('elcis_settings', 'elcis_pco_secret');
    register_setting('elcis_settings', 'elcis_clearstream_key');
});


/* REST routes */
add_action('rest_api_init', function () {
    (new ELCIS\Checkins_API)->register_routes();
    (new ELCIS\Clearstream)->register_routes();
});