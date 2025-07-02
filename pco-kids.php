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

// Add hidden submenu page for PCO OAuth callback
add_action('admin_menu', function () {
    add_submenu_page(null, 'PCO OAuth Callback', 'PCO OAuth Callback', 'manage_options', 'pco_oauth_callback', 'elcis_handle_oauth_callback');
});

function elcis_handle_oauth_callback() {
    if (!isset($_GET['code'])) {
        echo 'Missing authorization code.';
        return;
    }

    $code = sanitize_text_field($_GET['code']);
    $client_id = get_option('elcis_pco_app_id');
    $client_secret = get_option('elcis_pco_secret');
    $redirect_uri = admin_url('admin.php?page=pco_oauth_callback');

    $response = wp_remote_post('https://api.planningcenteronline.com/oauth/token', [
        'body' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect_uri,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ],
    ]);

    if (is_wp_error($response)) {
        echo 'OAuth error: ' . esc_html($response->get_error_message());
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['access_token'])) {
        echo '<p style="color:red;">Failed to get access token.</p>';
        return;
    }

    update_option('elcis_pco_access_token', $body['access_token']);
    update_option('elcis_pco_refresh_token', $body['refresh_token']);
    update_option('elcis_pco_token_expires', time() + $body['expires_in']);

    echo '<div class="updated"><p>✅ Connected successfully. You may close this window.</p></div>';
}


/**
 * Refresh the Planning Center OAuth token if it's about to expire.
 */
function elcis_refresh_pco_token_if_needed() {
    $expires = get_option('elcis_pco_token_expires');
    // Only refresh if $expires is set and we are within 60 seconds of expiry (or expired)
    if (!$expires || time() < ($expires - 60)) {
        return;
    }

    $refresh_token = get_option('elcis_pco_refresh_token');
    $client_id = get_option('elcis_pco_app_id');
    $client_secret = get_option('elcis_pco_secret');

    $response = wp_remote_post('https://api.planningcenteronline.com/oauth/token', [
        'body' => [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ],
    ]);

    if (!is_wp_error($response)) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['access_token'])) {
            update_option('elcis_pco_access_token', $body['access_token']);
            update_option('elcis_pco_refresh_token', $body['refresh_token']);
            update_option('elcis_pco_token_expires', time() + $body['expires_in']);
        }
    }
}

/* REST routes */
add_action('rest_api_init', function () {
    error_log('🧠 Registering ELCIS Checkins_API...');
    $checkins_api = new \ELCIS\Checkins_API();
    $checkins_api->register_routes();

    $clearstream = new \ELCIS\Clearstream();
    $clearstream->register_routes();

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