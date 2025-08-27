<?php

if (!defined('ABSPATH')) {
    exit;
}

class SRS_Database {
    
    private $table_activities;
    private $table_streams;
    
    public function __construct() {
        global $wpdb;
        $this->table_activities = $wpdb->prefix . 'srs_activities';
        $this->table_streams = $wpdb->prefix . 'srs_streams';
    }
    
    public static function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql_activities = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srs_activities (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            strava_id bigint(20) UNSIGNED NOT NULL,
            athlete_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            distance float NOT NULL,
            moving_time int(11) NOT NULL,
            elapsed_time int(11) NOT NULL,
            total_elevation_gain float DEFAULT 0,
            type varchar(50) NOT NULL,
            start_date datetime NOT NULL,
            start_date_local datetime NOT NULL,
            timezone varchar(100) DEFAULT NULL,
            start_latlng text DEFAULT NULL,
            end_latlng text DEFAULT NULL,
            average_speed float DEFAULT 0,
            max_speed float DEFAULT 0,
            average_heartrate float DEFAULT NULL,
            max_heartrate int(11) DEFAULT NULL,
            calories float DEFAULT NULL,
            polyline text DEFAULT NULL,
            summary_polyline text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY strava_id (strava_id),
            KEY athlete_id (athlete_id),
            KEY start_date (start_date),
            KEY type (type)
        ) $charset_collate;";
        
        $sql_streams = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srs_streams (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            activity_id bigint(20) UNSIGNED NOT NULL,
            latlng longtext DEFAULT NULL,
            altitude longtext DEFAULT NULL,
            time_data longtext DEFAULT NULL,
            heartrate longtext DEFAULT NULL,
            cadence longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY activity_id (activity_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_activities);
        dbDelta($sql_streams);
    }
    
    public function insertActivity($activity_data, $stream_data = null) {
        global $wpdb;
        
        // 验证必需字段
        if (empty($activity_data['id']) || empty($activity_data['athlete']['id'])) {
            return false;
        }
        
        // 安全处理日期
        $start_date = !empty($activity_data['start_date']) ? 
            date('Y-m-d H:i:s', strtotime($activity_data['start_date'])) : 
            current_time('mysql');
        
        $start_date_local = !empty($activity_data['start_date_local']) ? 
            date('Y-m-d H:i:s', strtotime($activity_data['start_date_local'])) : 
            $start_date;
        
        $data = [
            'strava_id' => intval($activity_data['id']),
            'athlete_id' => intval($activity_data['athlete']['id']),
            'name' => sanitize_text_field($activity_data['name'] ?? 'Untitled'),
            'distance' => floatval($activity_data['distance'] ?? 0),
            'moving_time' => intval($activity_data['moving_time'] ?? 0),
            'elapsed_time' => intval($activity_data['elapsed_time'] ?? 0),
            'total_elevation_gain' => floatval($activity_data['total_elevation_gain'] ?? 0),
            'type' => sanitize_text_field($activity_data['type'] ?? 'Other'),
            'start_date' => $start_date,
            'start_date_local' => $start_date_local,
            'timezone' => sanitize_text_field($activity_data['timezone'] ?? null),
            'start_latlng' => isset($activity_data['start_latlng']) ? json_encode($activity_data['start_latlng']) : null,
            'end_latlng' => isset($activity_data['end_latlng']) ? json_encode($activity_data['end_latlng']) : null,
            'average_speed' => floatval($activity_data['average_speed'] ?? 0),
            'max_speed' => floatval($activity_data['max_speed'] ?? 0),
            'average_heartrate' => isset($activity_data['average_heartrate']) ? floatval($activity_data['average_heartrate']) : null,
            'max_heartrate' => isset($activity_data['max_heartrate']) ? intval($activity_data['max_heartrate']) : null,
            'calories' => isset($activity_data['calories']) ? floatval($activity_data['calories']) : null,
            'polyline' => isset($activity_data['map']['polyline']) ? sanitize_text_field($activity_data['map']['polyline']) : null,
            'summary_polyline' => isset($activity_data['map']['summary_polyline']) ? sanitize_text_field($activity_data['map']['summary_polyline']) : null
        ];
        
        $result = $wpdb->insert($this->table_activities, $data);
        
        if ($result && $stream_data) {
            $activity_id = $wpdb->insert_id;
            $this->insertStream($activity_id, $stream_data);
        }
        
        return $result;
    }
    
    public function insertStream($activity_id, $stream_data) {
        global $wpdb;
        
        $data = [
            'activity_id' => $activity_id,
            'latlng' => isset($stream_data['latlng']) ? json_encode($stream_data['latlng']['data']) : null,
            'altitude' => isset($stream_data['altitude']) ? json_encode($stream_data['altitude']['data']) : null,
            'time_data' => isset($stream_data['time']) ? json_encode($stream_data['time']['data']) : null,
            'heartrate' => isset($stream_data['heartrate']) ? json_encode($stream_data['heartrate']['data']) : null,
            'cadence' => isset($stream_data['cadence']) ? json_encode($stream_data['cadence']['data']) : null
        ];
        
        return $wpdb->insert($this->table_streams, $data);
    }
    
    public function getActivityByStravaId($strava_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_activities} WHERE strava_id = %d",
            $strava_id
        ), ARRAY_A);
    }
    
    public function getActivities($page = 1, $per_page = 20, $filters = []) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['type'])) {
            $where .= " AND type = %s";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['start_date'])) {
            $where .= " AND start_date >= %s";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where .= " AND start_date <= %s";
            $params[] = $filters['end_date'];
        }
        
        $query = "SELECT * FROM {$this->table_activities} $where ORDER BY start_date DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $activities = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        $total_query = "SELECT COUNT(*) FROM {$this->table_activities} $where";
        if (count($params) > 2) {
            $total = $wpdb->get_var($wpdb->prepare($total_query, array_slice($params, 0, -2)));
        } else {
            $total = $wpdb->get_var($total_query);
        }
        
        return [
            'activities' => $activities,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }
    
    public function getActivityWithStream($activity_id) {
        global $wpdb;
        
        $activity = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_activities} WHERE id = %d",
            $activity_id
        ), ARRAY_A);
        
        if ($activity) {
            $stream = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_streams} WHERE activity_id = %d",
                $activity_id
            ), ARRAY_A);
            
            if ($stream) {
                $activity['stream'] = [
                    'latlng' => json_decode($stream['latlng'], true),
                    'altitude' => json_decode($stream['altitude'], true),
                    'time' => json_decode($stream['time_data'], true),
                    'heartrate' => json_decode($stream['heartrate'], true),
                    'cadence' => json_decode($stream['cadence'], true)
                ];
            }
        }
        
        return $activity;
    }
    
    public function getStatistics($type = null) {
        global $wpdb;
        
        if ($type) {
            return $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as total_activities,
                    SUM(distance) as total_distance,
                    SUM(moving_time) as total_time,
                    SUM(total_elevation_gain) as total_elevation,
                    AVG(distance) as avg_distance,
                    AVG(average_speed) as avg_speed,
                    MAX(distance) as max_distance,
                    MAX(average_speed) as max_speed
                FROM {$this->table_activities}
                WHERE type = %s
            ", $type), ARRAY_A);
        } else {
            return $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as total_activities,
                    SUM(distance) as total_distance,
                    SUM(moving_time) as total_time,
                    SUM(total_elevation_gain) as total_elevation,
                    AVG(distance) as avg_distance,
                    AVG(average_speed) as avg_speed,
                    MAX(distance) as max_distance,
                    MAX(average_speed) as max_speed
                FROM %i
                WHERE type IN (%s, %s, %s, %s)
            ", $this->table_activities, 'Run', 'Walk', 'Ride', 'VirtualRide'), ARRAY_A);
        }
    }
    
    public function getMonthlyStatistics($year = null, $type = null) {
        global $wpdb;
        
        if (!$year) {
            $year = date('Y');
        }
        
        if ($type) {
            return $wpdb->get_results($wpdb->prepare("
                SELECT 
                    MONTH(start_date_local) as month,
                    COUNT(*) as activities,
                    SUM(distance) as distance,
                    SUM(moving_time) as time,
                    SUM(total_elevation_gain) as elevation
                FROM {$this->table_activities}
                WHERE YEAR(start_date_local) = %d
                    AND type = %s
                GROUP BY MONTH(start_date_local)
                ORDER BY month
            ", $year, $type), ARRAY_A);
        } else {
            return $wpdb->get_results($wpdb->prepare("
                SELECT 
                    MONTH(start_date_local) as month,
                    COUNT(*) as activities,
                    SUM(distance) as distance,
                    SUM(moving_time) as time,
                    SUM(total_elevation_gain) as elevation
                FROM {$this->table_activities}
                WHERE YEAR(start_date_local) = %d
                    AND type IN ('Run', 'Walk', 'Ride', 'VirtualRide')
                GROUP BY MONTH(start_date_local)
                ORDER BY month
            ", $year), ARRAY_A);
        }
    }
    
    public function deleteActivity($activity_id) {
        global $wpdb;
        
        $wpdb->delete($this->table_streams, ['activity_id' => $activity_id]);
        
        return $wpdb->delete($this->table_activities, ['id' => $activity_id]);
    }
    
}