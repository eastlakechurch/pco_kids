<?php
namespace ELCIS;
use WP_REST_Server;

class Checkins_API {
    private $base = 'eastlake-sms/v1';

    public function register_routes() {
        register_rest_route($this->base, '/checkins', [
            'methods'  => WP_REST_Server::READABLE,
            'permission_callback' => [$this, 'can_use'],
            'callback' => [$this, 'get_open_checkins'],
        ]);
    }
    public function can_use() { return current_user_can('manage_checkins_sms'); }

    public function get_open_checkins() {
        $cached = get_transient('elcis_open_checkins');
        if ($cached) return $cached;

        $creds = $this->pco_creds();
        $args  = [
          'headers' => ['Authorization' => 'Basic ' . base64_encode("{$creds['id']}:{$creds['secret']}")],
          'timeout' => 10,
        ];
        $url = 'https://api.planningcenteronline.com/check-ins/v2/check_ins'
             . '?where[checked_out_at]=null&include=person,location,event&period&per_page=100';
        $resp = wp_remote_get($url, $args);
        if (is_wp_error($resp)) return $resp;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        set_transient('elcis_open_checkins', $body, 20); // 20-second cache
        return $body;
    }
    private function pco_creds() {
        return [
            'id'     => get_option('elcis_pco_app_id'),
            'secret' => get_option('elcis_pco_secret'),
        ];
    }
}