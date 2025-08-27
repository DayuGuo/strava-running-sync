<?php

if (!defined('ABSPATH')) {
    exit;
}

class SRS_StravaAPI {
    
    const STRAVA_OAUTH_URL = 'https://www.strava.com/oauth/authorize';
    const STRAVA_TOKEN_URL = 'https://www.strava.com/oauth/token';
    const STRAVA_API_BASE = 'https://www.strava.com/api/v3';
    
    private $client_id;
    private $client_secret;
    private $access_token;
    private $refresh_token;
    
    public function __construct() {
        $this->client_id = get_option('srs_strava_client_id');
        $this->client_secret = get_option('srs_strava_client_secret');
        $this->access_token = get_option('srs_strava_access_token');
        $this->refresh_token = get_option('srs_strava_refresh_token');
    }
    
    public function getAuthorizationUrl() {
        $redirect_uri = $this->getRedirectUri();
        $state = wp_create_nonce('srs_oauth_' . get_current_user_id());
        set_transient('srs_oauth_state_' . get_current_user_id(), $state, 600);
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'approval_prompt' => 'auto',
            'scope' => 'read,activity:read_all',
            'state' => $state
        ];
        
        return self::STRAVA_OAUTH_URL . '?' . http_build_query($params);
    }
    
    private function getRedirectUri() {
        $custom_uri = get_option('srs_strava_redirect_uri');
        if ($custom_uri) {
            return $custom_uri;
        }
        
        return admin_url('admin.php?page=strava-running-sync&action=callback');
    }
    
    public function handleCallback($code) {
        $redirect_uri = $this->getRedirectUri();
        
        $response = wp_remote_post(self::STRAVA_TOKEN_URL, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            update_option('srs_strava_access_token', $data['access_token']);
            update_option('srs_strava_refresh_token', $data['refresh_token']);
            update_option('srs_strava_athlete_id', $data['athlete']['id']);
            update_option('srs_strava_token_expires', time() + $data['expires_in']);
            
            $this->access_token = $data['access_token'];
            $this->refresh_token = $data['refresh_token'];
            
            return ['success' => true, 'athlete' => $data['athlete']];
        }
        
        return ['success' => false, 'message' => '获取访问令牌失败'];
    }
    
    public function refreshToken() {
        if (!$this->refresh_token) {
            return false;
        }
        
        $response = wp_remote_post(self::STRAVA_TOKEN_URL, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            update_option('srs_strava_access_token', $data['access_token']);
            update_option('srs_strava_refresh_token', $data['refresh_token']);
            update_option('srs_strava_token_expires', time() + $data['expires_in']);
            
            $this->access_token = $data['access_token'];
            $this->refresh_token = $data['refresh_token'];
            
            return true;
        }
        
        return false;
    }
    
    private function ensureValidToken() {
        $expires = get_option('srs_strava_token_expires', 0);
        
        if ($expires < time() + 600) {
            return $this->refreshToken();
        }
        
        return true;
    }
    
    public function getActivities($page = 1, $per_page = 30) {
        if (!$this->ensureValidToken()) {
            return ['success' => false, 'message' => '令牌刷新失败'];
        }
        
        $response = wp_remote_get(self::STRAVA_API_BASE . '/athlete/activities', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ],
            'query' => [
                'page' => $page,
                'per_page' => $per_page
            ]
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        $activities = json_decode($body, true);
        
        return ['success' => true, 'activities' => $activities];
    }
    
    public function getActivityDetails($activity_id) {
        if (!$this->ensureValidToken()) {
            return null;
        }
        
        $response = wp_remote_get(self::STRAVA_API_BASE . '/activities/' . $activity_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ]
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    public function getActivityStream($activity_id, $types = 'latlng,altitude,time') {
        if (!$this->ensureValidToken()) {
            return null;
        }
        
        $response = wp_remote_get(self::STRAVA_API_BASE . '/activities/' . $activity_id . '/streams', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ],
            'query' => [
                'keys' => $types,
                'key_by_type' => true
            ]
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    public function syncActivities() {
        $db = new SRS_Database();
        $synced_count = 0;
        $page = 1;
        
        do {
            $result = $this->getActivities($page, 50);
            
            if (!$result['success'] || empty($result['activities'])) {
                break;
            }
            
            foreach ($result['activities'] as $activity) {
                if (!in_array($activity['type'], ['Run', 'Walk', 'Ride', 'VirtualRide'])) {
                    continue;
                }
                
                $existing = $db->getActivityByStravaId($activity['id']);
                
                if (!$existing) {
                    $details = $this->getActivityDetails($activity['id']);
                    $stream = $this->getActivityStream($activity['id']);
                    
                    if ($details) {
                        $db->insertActivity($details, $stream);
                        $synced_count++;
                    }
                }
            }
            
            $page++;
            
            if (count($result['activities']) < 50) {
                break;
            }
            
        } while ($page <= 10);
        
        update_option('srs_last_sync', current_time('mysql'));
        
        return ['success' => true, 'synced' => $synced_count];
    }
}