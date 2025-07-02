<?php
namespace ELCIS;
use WP_REST_Server;

class Checkins_API {
    private $base = 'eastlake-sms/v1';

    public function register_routes() {
        error_log('✅ Checkins_API route registration triggered');
        register_rest_route($this->base, '/checkins', [
            'methods'  => WP_REST_Server::READABLE,
            'permission_callback' => [$this, 'can_use'],
            'callback' => [$this, 'get_open_checkins'],
        ]);
        register_rest_route($this->base, '/checkout', [
            'methods'  => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'handle_checkout'],
            'args'     => [
                'checkin_id' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);
    }
    public function can_use() {
        return is_user_logged_in() &&
               isset($_SERVER['HTTP_X_WP_NONCE']) &&
               wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'], 'wp_rest');
    }

    public function get_open_checkins() {
        $cached = get_transient('elcis_open_checkins');
        if ($cached) return $cached;

        elcis_refresh_pco_token_if_needed();
        $access_token = get_option('elcis_pco_access_token');
        $args  = [
          'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
          ],
          'timeout' => 10,
        ];
        $url = 'https://api.planningcenteronline.com/check-ins/v2/check_ins'
             . '?where[checked_out_at]=null'
             . '&include=person,locations,event_period,checked_in_by,event,event_times'
             . '&per_page=100';
        $resp = wp_remote_get($url, $args);
        if (is_wp_error($resp)) return $resp;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $checkins = [];

        // Helper function to find included items by type and id
        $find_included = function($included, $type, $id) {
            foreach ($included as $item) {
                if ($item['type'] === $type && $item['id'] === $id) {
                    return $item;
                }
            }
            return null;
        };

        $included = $body['included'] ?? [];

        foreach ($body['data'] as $item) {
            $location_id = $item['relationships']['locations']['data'][0]['id'] ?? null;
            $location = $location_id ? $find_included($included, 'Location', $location_id) : null;

            $event_id = $item['relationships']['event']['data']['id'] ?? null;
            $event = $event_id ? $find_included($included, 'Event', $event_id) : null;
            $event_name = $event['attributes']['name'] ?? '—';

            $event_period_id = $item['relationships']['event_period']['data']['id'] ?? null;
            $event_period = $event_period_id ? $find_included($included, 'EventPeriod', $event_period_id) : null;
            $event_time = '—';
            if ($event_period && !empty($event_period['attributes']['starts_at'])) {
                $event_time_obj = new \DateTime($event_period['attributes']['starts_at'], new \DateTimeZone('UTC'));
                $event_time_obj->setTimezone(new \DateTimeZone('Australia/Perth'));
                $event_time = $event_time_obj->format('H:i');
            }

            $person_id = $item['relationships']['person']['data']['id'] ?? null;
            $person = $person_id ? $find_included($included, 'Person', $person_id) : null;

            $checked_in_by_id = $item['relationships']['checked_in_by']['data']['id'] ?? null;
            $checked_in_by = $checked_in_by_id ? $find_included($included, 'Person', $checked_in_by_id) : null;
            $checked_in_by_name = $checked_in_by 
                ? ($checked_in_by['attributes']['first_name'] . ' ' . $checked_in_by['attributes']['last_name']) 
                : '—';

            $phone_numbers = $person['attributes']['phone_numbers'] ?? [];
            $primary_phone = '—';
            foreach ($phone_numbers as $num) {
                if (!empty($num['primary']) && !empty($num['number'])) {
                    $primary_phone = $num['number'];
                    break;
                }
            }
            $phone = $primary_phone;

            $tz = new \DateTimeZone('Australia/Perth');
            $created_at_dt = new \DateTime($item['attributes']['created_at'], new \DateTimeZone('UTC'));
            $created_at_dt->setTimezone($tz);

            $event_date = $created_at_dt->format('Y-m-d');
            $today = (new \DateTime('now', $tz))->format('Y-m-d');

            if ($event_date !== $today) {
                continue;
            }

            $checkins[] = [
                'id'         => $item['id'],
                'name'       => $item['attributes']['first_name'] . ' ' . $item['attributes']['last_name'],
                'since'      => date('H:i:s', $created_at),
                'created_at' => $item['attributes']['created_at'],
                'room'       => $location ? $location['attributes']['name'] : '—',
                'room_id'    => $location['id'] ?? null,
                'phone'      => $phone,
                'event_name' => $event_name,
                'event_time' => $event_time,
                'event_period_start' => $item['attributes']['event_period_start'] ?? null,
                'checked_in_by' => $checked_in_by_name,
            ];
        }

        set_transient('elcis_open_checkins', $checkins, 20); // 20-second cache
        return $checkins;
    }

    public function handle_checkout($request) {
        $checkin_id = sanitize_text_field($request->get_param('checkin_id'));
        error_log("▶️ Received checkout request for ID: $checkin_id");

        $access_token = get_option('elcis_pco_access_token');
        if (!$access_token) {
            error_log("❌ Missing access token");
            return new \WP_Error('missing_token', 'Access token not available', ['status' => 401]);
        }

        $url = "https://api.planningcenteronline.com/check-ins/v2/check_ins/{$checkin_id}";
        $now = gmdate("Y-m-d\TH:i:s\Z");

        $body = json_encode([
            'data' => [
                'type'       => 'CheckIn',
                'id'         => $checkin_id,
                'attributes' => [
                    'checked_out_at' => $now,
                ],
            ],
        ]);

        error_log("📤 Sending PATCH to $url with body: $body");

        $response = wp_remote_request($url, [
            'method'  => 'PATCH',
            'headers' => [
                'Authorization' => "Bearer {$access_token}",
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            error_log("❌ WP Error: " . $response->get_error_message());
            return new \WP_Error('api_error', $response->get_error_message(), ['status' => 500]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log("✅ PCO response code: $code");
        error_log("📥 Response body: $response_body");

        if ($code >= 200 && $code < 300) {
            delete_transient('elcis_open_checkins');
            return ['success' => true, 'checked_out_at' => $now];
        }

        return new \WP_Error('api_error', 'Failed to check out', ['status' => $code]);
    }
}