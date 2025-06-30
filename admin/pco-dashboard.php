<?php
namespace ELCIS;
class Dashboard_Page {
    public static function register() {
        add_menu_page(
            'Kids Check-In SMS',
            'Check-In SMS',
            'manage_checkins_sms',
            'elcis-dashboard',
            [self::class,'render'],
            'dashicons-megaphone'
        );

        add_submenu_page(
            'elcis-dashboard',
            'PCO Settings',
            'Settings',
            'manage_checkins_sms',
            'elcis-settings',
            [self::class, 'settings_page']
        );

        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }
    public static function render() {
        echo '<h1>Live Kids Roll-Call</h1><div id="elcis-table">Loading…</div><div id="elcis-replies"></div>';
    }
    public static function enqueue_assets() {
        wp_enqueue_script('elcis-dashboard', plugins_url('admin/js/pco-dashboard.js', plugin_dir_path(__FILE__)), ['wp-api', 'jquery'], '0.1.0', true);

        wp_localize_script('elcis-dashboard', 'elcisCfg', [
            'root' => esc_url_raw(rest_url('eastlake-sms/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function settings_page() {
        if (isset($_POST['elcis_save_settings'])) {
            update_option('elcis_pco_app_id', sanitize_text_field($_POST['elcis_pco_app_id']));
            update_option('elcis_pco_secret', sanitize_text_field($_POST['elcis_pco_secret']));
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }

        $app_id = get_option('elcis_pco_app_id', '');
        $secret = get_option('elcis_pco_secret', '');

        echo '<div class="wrap"><h1>Planning Center Settings</h1><form method="post">
            <table class="form-table">
                <tr><th scope="row"><label for="elcis_pco_app_id">App ID</label></th>
                    <td><input name="elcis_pco_app_id" type="text" value="' . esc_attr($app_id) . '" class="regular-text" /></td></tr>
                <tr><th scope="row"><label for="elcis_pco_secret">Secret</label></th>
                    <td><input name="elcis_pco_secret" type="password" value="' . esc_attr($secret) . '" class="regular-text" /></td></tr>
            </table>
            <p class="submit"><button type="submit" name="elcis_save_settings" class="button-primary">Save Changes</button></p>
        </form></div>';
    }
}