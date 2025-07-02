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

        $access_token = get_option('elcis_pco_access_token');
        $refresh_token = get_option('elcis_pco_refresh_token');
        $token_expires = get_option('elcis_pco_token_expires');

        echo '<div class="wrap"><h1>Planning Center Settings</h1><form method="post">
            <table class="form-table">
                <tr><th scope="row"><label for="elcis_pco_app_id">Client ID</label></th>
                    <td><input name="elcis_pco_app_id" type="text" value="' . esc_attr($app_id) . '" class="regular-text" /></td></tr>
                <tr><th scope="row"><label for="elcis_pco_secret">Client Secret</label></th>
                    <td><input name="elcis_pco_secret" type="password" value="' . esc_attr($secret) . '" class="regular-text" /></td></tr>
                <tr><th scope="row"><label>Connection Status</label></th>
                    <td>' . ($access_token ? '<span style="color:green;">Connected ✅</span>' : '<span style="color:red;">Not Connected ❌</span>') . '</td></tr>
                <tr><th scope="row"><label>Authorize</label></th>
                    <td><a href="' . esc_url(self::build_oauth_url()) . '" class="button button-primary">Connect to Planning Center</a></td></tr>
                <tr><th colspan="2"><hr /></th></tr>
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

    public static function build_oauth_url() {
        $client_id = get_option('elcis_pco_app_id');
        $redirect_uri = urlencode(admin_url('admin.php?page=pco_oauth_callback'));
        $scope = 'people check_ins groups';
        return "https://api.planningcenteronline.com/oauth/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&scope={$scope}";
    }
}