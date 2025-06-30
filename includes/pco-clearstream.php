<?php
namespace ELCIS;
use WP_REST_Server;

class Clearstream {
    private $base = 'eastlake-sms/v1';

    public function register_routes() {
        register_rest_route($this->base, '/sms', [
            'methods'  => WP_REST_Server::CREATABLE,
            'permission_callback' => [$this, 'can_use'],
            'callback' => [$this, 'send_sms'],
            'args'     => [
                'to'      => ['required' => true],
                'body'    => ['required' => true],
                'header'  => ['default'  => 'Kids Team'],
            ],
        ]);
        /* inbound webhook */
        register_rest_route($this->base, '/inbound', [
            'methods'  => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true', // Clearstream will sign the request later
            'callback' => [$this, 'handle_inbound'],
        ]);
    }
    public function can_use() { return current_user_can('manage_checkins_sms'); }

    public function send_sms($req) {
        $key  = get_option('elcis_clearstream_key');
        $resp = wp_remote_post('https://api.getclearstream.com/v1/texts', [
            'headers' => ['X-Api-Key' => $key],
            'body'    => [
                'to'         => $req['to'],
                'from'       => '',              // default account number
                'text_header'=> $req['header'],
                'text_body'  => $req['body'],
            ],
            'timeout' => 10,
        ]);
        return wp_remote_retrieve_body($resp);
    }
    public function handle_inbound($req) {
        $payload = $req->get_json_params();
        // You might store in custom table or transient
        do_action('elcis_inbound_sms', $payload);
        return rest_ensure_response(['ok' => true]);
    }
}