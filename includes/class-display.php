<?php

if (!defined('ABSPATH')) {
    exit;
}

class SRS_Display {
    
    public static function render($atts) {
        $db = new SRS_Database();
        
        $activities = $db->getActivities(1, $atts['limit']);
        $statistics = $db->getStatistics();
        
        ob_start();
        ?>
        <div class="srs-container">
            <?php if ($atts['type'] === 'both' || $atts['type'] === 'stats'): ?>
                <div class="srs-statistics">
                    <div class="srs-section-header">
                        <h3><?php _e('运动统计', 'strava-running-sync'); ?></h3>
                        <div class="srs-activity-filter">
                            <button class="srs-filter-btn active" data-type="all"><?php _e('全部', 'strava-running-sync'); ?></button>
                            <button class="srs-filter-btn" data-type="Run"><?php _e('跑步', 'strava-running-sync'); ?></button>
                            <button class="srs-filter-btn" data-type="Ride"><?php _e('骑行', 'strava-running-sync'); ?></button>
                            <button class="srs-filter-btn" data-type="Walk"><?php _e('步行', 'strava-running-sync'); ?></button>
                        </div>
                    </div>
                    
                    <div class="srs-stats-cards">
                        <div class="srs-stat-card">
                            <div class="srs-stat-number"><?php echo intval($statistics['total_activities']); ?></div>
                            <div class="srs-stat-label"><?php _e('总活动数', 'strava-running-sync'); ?></div>
                        </div>
                        <div class="srs-stat-card">
                            <div class="srs-stat-number"><?php echo number_format($statistics['total_distance'] / 1000, 1); ?></div>
                            <div class="srs-stat-unit">km</div>
                            <div class="srs-stat-label"><?php _e('总距离', 'strava-running-sync'); ?></div>
                        </div>
                        <div class="srs-stat-card">
                            <div class="srs-stat-number"><?php echo self::formatTimeShort($statistics['total_time']); ?></div>
                            <div class="srs-stat-label"><?php _e('总时长', 'strava-running-sync'); ?></div>
                        </div>
                        <div class="srs-stat-card">
                            <div class="srs-stat-number"><?php echo number_format($statistics['total_elevation'], 0); ?></div>
                            <div class="srs-stat-unit">m</div>
                            <div class="srs-stat-label"><?php _e('总爬升', 'strava-running-sync'); ?></div>
                        </div>
                        <div class="srs-stat-card">
                            <div class="srs-stat-number"><?php echo number_format($statistics['avg_distance'] / 1000, 1); ?></div>
                            <div class="srs-stat-unit">km</div>
                            <div class="srs-stat-label"><?php _e('平均距离', 'strava-running-sync'); ?></div>
                        </div>
                        <div class="srs-stat-card">
                            <div class="srs-stat-number"><?php echo number_format($statistics['avg_speed'] * 3.6, 1); ?></div>
                            <div class="srs-stat-unit">km/h</div>
                            <div class="srs-stat-label"><?php _e('平均速度', 'strava-running-sync'); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['type'] === 'both' || $atts['type'] === 'map'): ?>
                <div class="srs-map-section">
                    <h3><?php _e('活动地图', 'strava-running-sync'); ?></h3>
                    <div id="srs-map" style="height: <?php echo esc_attr($atts['map_height']); ?>"></div>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['type'] === 'both' || $atts['type'] === 'table'): ?>
                <div class="srs-activities-section">
                    <h3><?php _e('最近活动', 'strava-running-sync'); ?></h3>
                    <table class="srs-table">
                        <thead>
                            <tr>
                                <th><?php _e('日期', 'strava-running-sync'); ?></th>
                                <th><?php _e('类型', 'strava-running-sync'); ?></th>
                                <th><?php _e('距离', 'strava-running-sync'); ?></th>
                                <th><?php _e('时长', 'strava-running-sync'); ?></th>
                                <th><?php _e('速度/配速', 'strava-running-sync'); ?></th>
                                <th><?php _e('爬升', 'strava-running-sync'); ?></th>
                                <th><?php _e('心率', 'strava-running-sync'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities['activities'] as $activity): ?>
                                <tr data-activity-id="<?php echo $activity['id']; ?>" class="srs-activity-<?php echo strtolower($activity['type']); ?>">
                                    <td><?php echo date('Y-m-d', strtotime($activity['start_date_local'])); ?></td>
                                    <td><span class="srs-activity-type"><?php echo self::getActivityTypeLabel($activity['type']); ?></span></td>
                                    <td><?php echo number_format($activity['distance'] / 1000, 2); ?> km</td>
                                    <td><?php echo self::formatTime($activity['moving_time']); ?></td>
                                    <td><?php echo self::formatSpeedOrPace($activity['type'], $activity['moving_time'], $activity['distance']); ?></td>
                                    <td><?php echo number_format($activity['total_elevation_gain'], 0); ?> m</td>
                                    <td>
                                        <?php if ($activity['average_heartrate']): ?>
                                            <?php echo intval($activity['average_heartrate']); ?> bpm
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($activities['total_pages'] > 1): ?>
                        <div class="srs-pagination">
                            <button id="srs-load-more" data-page="2" data-total="<?php echo $activities['total_pages']; ?>">
                                <?php _e('加载更多', 'strava-running-sync'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
            var srsActivities = <?php echo wp_json_encode($activities['activities'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    private static function formatTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        } else {
            return sprintf('%d:%02d', $minutes, $secs);
        }
    }
    
    private static function formatTimeShort($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours >= 24) {
            $days = floor($hours / 24);
            $remaining_hours = $hours % 24;
            return $days . 'd ' . $remaining_hours . 'h';
        } else if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        } else {
            return $minutes . 'm';
        }
    }
    
    private static function formatPace($time, $distance) {
        if ($distance <= 0 || $time <= 0) {
            return '-';
        }
        
        $distance_km = $distance / 1000;
        if ($distance_km <= 0) {
            return '-';
        }
        
        $pace_seconds = $time / $distance_km;
        $pace_minutes = floor($pace_seconds / 60);
        $pace_secs = intval($pace_seconds % 60);
        
        return sprintf('%d:%02d /km', $pace_minutes, $pace_secs);
    }
    
    private static function formatSpeedOrPace($type, $time, $distance) {
        if ($distance <= 0 || $time <= 0) {
            return '-';
        }
        
        if (in_array($type, ['Ride', 'VirtualRide'])) {
            $hours = $time / 3600;
            if ($hours <= 0) {
                return '-';
            }
            $speed = ($distance / 1000) / $hours;
            return number_format($speed, 1) . ' km/h';
        } else {
            return self::formatPace($time, $distance);
        }
    }
    
    private static function getActivityTypeLabel($type) {
        $labels = [
            'Run' => '跑步',
            'Walk' => '步行',
            'Ride' => '骑行',
            'VirtualRide' => '虚拟骑行'
        ];
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }
}