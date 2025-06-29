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
        wp_enqueue_script('elcis-dashboard', plugins_url('admin/js/checkin-dashboard.js', __DIR__), ['wp-api', 'jquery'], '0.1.0', true);
        wp_localize_script('elcis-dashboard', 'elcisCfg', [
            'root' => esc_url_raw(rest_url('eastlake-sms/v1')),
            'nonce'=> wp_create_nonce('wp_rest'),
        ]);
    }
    public static function render() {
        echo '<h1>Live Kids Roll-Call</h1><div id="elcis-table">Loading…</div><div id="elcis-replies"></div>';
    }
}