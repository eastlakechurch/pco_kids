<?php
/**
 * Plugin Name: Eastlake Check-In SMS Dashboard
 * Description: Live roll-call of currently checked-in kids with one-click SMS to parents.
 * Version:     0.1.0
 * Author:      Eastlake Church Tech
 */

defined('ABSPATH') || exit;

/* Autoload simple classes */
spl_autoload_register(function ($class) {
    if (strpos($class, 'ELCIS\\') === 0) {
        $relative_class = substr($class, strlen('ELCIS\\'));
        $file = plugin_dir_path(__FILE__) . 'includes/' . strtolower(str_replace(['\\', '_'], ['-', '-'], $relative_class)) . '.php';
        error_log("🧩 Autoload looking for: $file");

        if (file_exists($file)) {
            require_once $file;
            error_log("✅ Loaded class file: $file");
        } else {
            error_log("❌ File not found: $file");
        }
    }
});

require_once plugin_dir_path(__FILE__) . 'admin/pco-dashboard.php';
add_action('init', ['ELCIS\\Dashboard_Page', 'register_shortcode']);

/* Admin page */
add_action('admin_menu', ['ELCIS\\Dashboard_Page', 'register']);

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
    (new \ELCIS\Checkins_API)->register_routes();
    (new \ELCIS\Clearstream)->register_routes();

    register_rest_route('eastlake-sms/v1', '/sms-template', [
        'methods'  => 'GET',
        'callback' => function () {
            return get_option('elcis_clearstream_sms_template', '{kid} needs you at {room}. Please come now.');
        },
        'permission_callback' => '__return_true',
    ]);
});

// Redirect kids_leader users to the dashboard after login
add_filter('login_redirect', function ($redirect_to, $request, $user) {
    if (isset($user->roles) && in_array('kids_leader', $user->roles, true)) {
        return site_url('/kids-check-in/');
    }
    return $redirect_to;
}, 10, 3);

// Hide admin bar for kids_leader users
add_filter('show_admin_bar', function ($show) {
    return current_user_can('kids_leader') ? false : $show;
});

// Prevent kids_leader users from accessing wp-admin
add_action('admin_init', function () {
    if (current_user_can('kids_leader') && !current_user_can('administrator')) {
        if (!wp_doing_ajax()) {
            wp_redirect(site_url('/kids-check-in/'));
            exit;
        }
    }
});