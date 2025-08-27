<?php
/**
 * Plugin Name: Strava Running Sync
 * Plugin URI: https://github.com/DayuGuo/strava-running-sync
 * Description: 自动同步Strava跑步数据并展示在WordPress网站上
 * Version: 1.0.0
 * Author: Dayu
 * License: GPL v2 or later
 * Text Domain: strava-running-sync
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WordPress is fully loaded
if (!function_exists('plugin_dir_path') || !function_exists('plugin_dir_url')) {
    // WordPress functions not available, defer initialization
    add_action('plugins_loaded', function() {
        if (!defined('SRS_PLUGIN_DIR')) {
            define('SRS_PLUGIN_DIR', plugin_dir_path(__FILE__));
            define('SRS_PLUGIN_URL', plugin_dir_url(__FILE__));
            define('SRS_VERSION', '1.0.0');
            StravaRunningSync::getInstance();
        }
    });
    return;
}

define('SRS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SRS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SRS_VERSION', '1.0.0');

class StravaRunningSync {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initHooks();
        $this->loadDependencies();
    }
    
    private function initHooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_shortcode('strava_running_display', [$this, 'renderShortcode']);
        
        add_action('wp_ajax_srs_sync_strava', [$this, 'ajaxSyncStrava']);
        
        add_action('wp_ajax_srs_get_activities', [$this, 'ajaxGetActivities']);
        add_action('wp_ajax_nopriv_srs_get_activities', [$this, 'ajaxGetActivities']);
        
        
        add_action('wp_ajax_srs_get_filtered_stats', [$this, 'ajaxGetFilteredStats']);
        add_action('wp_ajax_nopriv_srs_get_filtered_stats', [$this, 'ajaxGetFilteredStats']);
        
        add_action('srs_cron_sync', [$this, 'cronSyncStrava']);
    }
    
    private function loadDependencies() {
        $plugin_dir = defined('SRS_PLUGIN_DIR') ? SRS_PLUGIN_DIR : plugin_dir_path(__FILE__);
        
        require_once $plugin_dir . 'includes/class-strava-api.php';
        require_once $plugin_dir . 'includes/class-database.php';
        require_once $plugin_dir . 'includes/class-admin.php';
        require_once $plugin_dir . 'includes/class-display.php';
    }
    
    public function init() {
        load_plugin_textdomain('strava-running-sync', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        SRS_Database::createTables();
        
        if (!wp_next_scheduled('srs_cron_sync')) {
            wp_schedule_event(time(), 'hourly', 'srs_cron_sync');
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('srs_cron_sync');
        flush_rewrite_rules();
    }
    
    public function enqueueScripts() {
        if (is_singular() && has_shortcode(get_post()->post_content, 'strava_running_display')) {
            $plugin_url = defined('SRS_PLUGIN_URL') ? SRS_PLUGIN_URL : plugin_dir_url(__FILE__);
            $version = defined('SRS_VERSION') ? SRS_VERSION : '1.0.0';
            
            wp_enqueue_style('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css');
            wp_enqueue_script('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js', [], null, true);
            
            wp_enqueue_style('srs-styles', $plugin_url . 'assets/css/frontend.css', [], $version);
            wp_enqueue_script('srs-script', $plugin_url . 'assets/js/frontend.js', ['jquery', 'mapbox-gl'], $version, true);
            
            // 检测主题颜色并生成自适应样式
            $theme_colors = $this->detectThemeColors();
            
            wp_localize_script('srs-script', 'srs_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('srs_ajax_nonce'),
                'mapbox_token' => get_option('srs_mapbox_token', ''),
                'map_style' => get_option('srs_map_style', 'mapbox://styles/mapbox/streets-v12'),
                'theme_colors' => $theme_colors
            ]);
            
            // 添加内联样式以支持主题适应
            $this->addAdaptiveInlineStyles($theme_colors);
        }
    }
    
    private function detectThemeColors() {
        $colors = [
            'detected' => false,
            'background' => '#ffffff',
            'text' => '#333333',
            'link' => '#0073aa',
            'border' => '#dddddd'
        ];
        
        // 尝试从当前主题获取颜色设置
        if (function_exists('get_theme_mod')) {
            $bg_color = get_theme_mod('background_color');
            $text_color = get_theme_mod('text_color');
            $link_color = get_theme_mod('link_color');
            
            if ($bg_color) {
                $colors['background'] = '#' . $bg_color;
                $colors['detected'] = true;
            }
            if ($text_color) {
                $colors['text'] = '#' . $text_color;
                $colors['detected'] = true;
            }
            if ($link_color) {
                $colors['link'] = '#' . $link_color;
                $colors['detected'] = true;
            }
        }
        
        return $colors;
    }
    
    private function addAdaptiveInlineStyles($theme_colors) {
        if ($theme_colors['detected']) {
            $custom_css = "
                .srs-container {
                    --srs-theme-bg: {$theme_colors['background']};
                    --srs-theme-text: {$theme_colors['text']};
                    --srs-theme-link: {$theme_colors['link']};
                }
            ";
            wp_add_inline_style('srs-styles', $custom_css);
        }
    }
    
    public function addAdminMenu() {
        add_menu_page(
            __('Strava Running Sync', 'strava-running-sync'),
            __('Strava Running', 'strava-running-sync'),
            'manage_options',
            'strava-running-sync',
            [SRS_Admin::class, 'renderSettingsPage'],
            'dashicons-location',
            30
        );
        
        add_submenu_page(
            'strava-running-sync',
            __('设置', 'strava-running-sync'),
            __('设置', 'strava-running-sync'),
            'manage_options',
            'strava-running-sync',
            [SRS_Admin::class, 'renderSettingsPage']
        );
        
        add_submenu_page(
            'strava-running-sync',
            __('活动列表', 'strava-running-sync'),
            __('活动列表', 'strava-running-sync'),
            'manage_options',
            'srs-activities',
            [SRS_Admin::class, 'renderActivitiesPage']
        );
    }
    
    public function renderShortcode($atts) {
        $atts = shortcode_atts([
            'type' => 'both',
            'limit' => 50,
            'map_height' => '500px'
        ], $atts, 'strava_running_display');
        
        return SRS_Display::render($atts);
    }
    
    public function ajaxSyncStrava() {
        check_ajax_referer('srs_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'strava-running-sync'));
        }
        
        $api = new SRS_StravaAPI();
        $result = $api->syncActivities();
        
        wp_send_json($result);
    }
    
    public function ajaxGetActivities() {
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'srs_ajax_nonce')) {
            wp_send_json_error(['message' => '安全验证失败']);
        }
        
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        
        $db = new SRS_Database();
        $activities = $db->getActivities($page, $per_page);
        
        wp_send_json_success($activities);
    }
    
    public function cronSyncStrava() {
        $api = new SRS_StravaAPI();
        $api->syncActivities();
    }
    
    
    public function ajaxGetFilteredStats() {
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'srs_ajax_nonce')) {
            wp_send_json_error(['message' => '安全验证失败']);
        }
        
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : null;
        if ($type === 'all') {
            $type = null;
        }
        
        if ($type && !in_array($type, ['Run', 'Walk', 'Ride', 'VirtualRide'])) {
            wp_send_json_error(['message' => '无效的活动类型']);
        }
        
        $db = new SRS_Database();
        $statistics = $db->getStatistics($type);
        
        wp_send_json_success($statistics);
    }
}

// Initialize plugin only if WordPress functions are available
if (function_exists('plugin_dir_path') && function_exists('plugin_dir_url')) {
    StravaRunningSync::getInstance();
}