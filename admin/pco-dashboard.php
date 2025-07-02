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
        self::register_shortcode();
    }
    public static function render() {
        echo '<h1>Live Kids Roll-Call</h1><div id="elcis-table">Loading…</div><div id="elcis-replies"></div>';
    }
    public static function enqueue_assets() {
        wp_enqueue_script('elcis-dashboard', plugins_url('admin/js/pco-dashboard.js', dirname(__FILE__)), ['wp-api', 'jquery'], '0.1.0', true);

        wp_localize_script('elcis-dashboard', 'elcisCfg', [
            'root' => esc_url_raw(rest_url('eastlake-sms/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function settings_page() {
        if (isset($_POST['elcis_save_settings'])) {
            update_option('elcis_pco_app_id', sanitize_text_field($_POST['elcis_pco_app_id']));
            update_option('elcis_pco_secret', sanitize_text_field($_POST['elcis_pco_secret']));
            update_option('elcis_clearstream_key', sanitize_text_field($_POST['elcis_clearstream_key']));
            update_option('elcis_clearstream_account_id', sanitize_text_field($_POST['elcis_clearstream_account_id']));
            update_option('elcis_clearstream_sms_template', sanitize_text_field($_POST['elcis_clearstream_sms_template']));
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
                <tr><th scope="row"><label for="elcis_clearstream_key">Clearstream API Key</label></th>
                    <td><input name="elcis_clearstream_key" type="text" value="' . esc_attr(get_option('elcis_clearstream_key', '')) . '" class="regular-text" /></td></tr>
                <tr><th scope="row"><label for="elcis_clearstream_account_id">Clearstream Subaccount ID</label></th>
                    <td><input name="elcis_clearstream_account_id" type="text" value="' . esc_attr(get_option('elcis_clearstream_account_id', '')) . '" class="regular-text" /></td></tr>
                <tr><th scope="row"><label for="elcis_clearstream_sms_template">Default SMS Template</label></th>
                    <td><input name="elcis_clearstream_sms_template" type="text" value="' . esc_attr(get_option('elcis_clearstream_sms_template', '{kid} needs you at {room}. Please come now.')) . '" class="regular-text" />
                    <p class="description">Use <code>{kid}</code> and <code>{room}</code> as placeholders.</p></td></tr>
            </table>
            <p class="submit"><button type="submit" name="elcis_save_settings" class="button-primary">Save Changes</button></p>
        </form></div>';
    }

    public static function register_shortcode() {
        add_shortcode('elcis_checkin_dashboard', function () {
            if (!current_user_can('manage_checkins_sms')) {
                return '<p>You must be logged in to view this page.</p>';
            }

            self::enqueue_assets();

            return '<div id="elcis-table">Loading…</div><div id="elcis-replies"></div>';
        });
    }
}