<?php

if (!defined('ABSPATH')) {
    exit;
}

class SRS_Admin {
    
    public static function renderSettingsPage() {
        if (isset($_GET['action']) && $_GET['action'] === 'callback' && isset($_GET['code'])) {
            // 验证 OAuth state 防止 CSRF
            $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
            $stored_state = get_transient('srs_oauth_state_' . get_current_user_id());
            
            if (!$state || !$stored_state || $state !== $stored_state) {
                echo '<div class="notice notice-error"><p>' . __('安全验证失败，请重新授权', 'strava-running-sync') . '</p></div>';
            } else {
                delete_transient('srs_oauth_state_' . get_current_user_id());
                
                $api = new SRS_StravaAPI();
                $result = $api->handleCallback($_GET['code']);
                
                if ($result['success']) {
                    echo '<div class="notice notice-success"><p>' . __('成功连接到Strava账户！', 'strava-running-sync') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('连接失败：', 'strava-running-sync') . $result['message'] . '</p></div>';
                }
            }
        }
        
        if (isset($_POST['save_settings'])) {
            check_admin_referer('srs_save_settings');
            
            update_option('srs_strava_client_id', sanitize_text_field($_POST['client_id']));
            update_option('srs_strava_client_secret', sanitize_text_field($_POST['client_secret']));
            update_option('srs_strava_redirect_uri', esc_url_raw($_POST['redirect_uri']));
            update_option('srs_mapbox_token', sanitize_text_field($_POST['mapbox_token']));
            update_option('srs_map_style', sanitize_text_field($_POST['map_style']));
            update_option('srs_auto_sync', isset($_POST['auto_sync']) ? 1 : 0);
            
            echo '<div class="notice notice-success"><p>' . __('设置已保存', 'strava-running-sync') . '</p></div>';
        }
        
        if (isset($_POST['sync_now'])) {
            check_admin_referer('srs_sync_now');
            
            $api = new SRS_StravaAPI();
            $result = $api->syncActivities();
            
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>' . sprintf(__('成功同步 %d 个活动', 'strava-running-sync'), $result['synced']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('同步失败', 'strava-running-sync') . '</p></div>';
            }
        }
        
        $client_id = get_option('srs_strava_client_id', '');
        $client_secret = get_option('srs_strava_client_secret', '');
        $redirect_uri = get_option('srs_strava_redirect_uri', admin_url('admin.php?page=strava-running-sync&action=callback'));
        $access_token = get_option('srs_strava_access_token', '');
        $athlete_id = get_option('srs_strava_athlete_id', '');
        $mapbox_token = get_option('srs_mapbox_token', '');
        $map_style = get_option('srs_map_style', 'mapbox://styles/mapbox/streets-v12');
        $auto_sync = get_option('srs_auto_sync', 0);
        $last_sync = get_option('srs_last_sync', '');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Strava Running Sync 设置', 'strava-running-sync'); ?></h1>
            
            <div class="srs-admin-container">
                <div class="srs-admin-main">
                    <form method="post" action="">
                        <?php wp_nonce_field('srs_save_settings'); ?>
                        
                        <h2><?php _e('Strava API 设置', 'strava-running-sync'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="client_id"><?php _e('Client ID', 'strava-running-sync'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="client_id" name="client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text" />
                                    <p class="description">
                                        <?php _e('从 Strava API 应用程序设置页面获取', 'strava-running-sync'); ?>
                                        <a href="https://www.strava.com/settings/api" target="_blank"><?php _e('创建应用', 'strava-running-sync'); ?></a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="client_secret"><?php _e('Client Secret', 'strava-running-sync'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="client_secret" name="client_secret" value="<?php echo esc_attr($client_secret); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="redirect_uri"><?php _e('回调 URL', 'strava-running-sync'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="redirect_uri" name="redirect_uri" value="<?php echo esc_attr($redirect_uri); ?>" class="large-text" />
                                    <p class="description">
                                        <?php _e('在Strava应用设置中配置的授权回调域名必须与此URL的域名一致', 'strava-running-sync'); ?><br>
                                        <?php _e('默认值：', 'strava-running-sync'); ?> <code><?php echo admin_url('admin.php?page=strava-running-sync&action=callback'); ?></code>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('连接状态', 'strava-running-sync'); ?></th>
                                <td>
                                    <?php if ($access_token): ?>
                                        <span class="dashicons dashicons-yes" style="color: green;"></span>
                                        <?php _e('已连接', 'strava-running-sync'); ?> (Athlete ID: <?php echo $athlete_id; ?>)
                                    <?php else: ?>
                                        <span class="dashicons dashicons-no" style="color: red;"></span>
                                        <?php _e('未连接', 'strava-running-sync'); ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($client_id && $client_secret): ?>
                                        <?php $api = new SRS_StravaAPI(); ?>
                                        <a href="<?php echo $api->getAuthorizationUrl(); ?>" class="button button-secondary">
                                            <?php _e('连接到 Strava', 'strava-running-sync'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <h2><?php _e('显示设置', 'strava-running-sync'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="mapbox_token"><?php _e('Mapbox Access Token', 'strava-running-sync'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="mapbox_token" name="mapbox_token" value="<?php echo esc_attr($mapbox_token); ?>" class="large-text" />
                                    <p class="description">
                                        <?php _e('从 Mapbox 获取访问令牌', 'strava-running-sync'); ?>
                                        <a href="https://account.mapbox.com/access-tokens/" target="_blank"><?php _e('获取 Token', 'strava-running-sync'); ?></a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="map_style"><?php _e('地图样式', 'strava-running-sync'); ?></label>
                                </th>
                                <td>
                                    <select id="map_style" name="map_style">
                                        <option value="mapbox://styles/mapbox/streets-v12" <?php selected($map_style, 'mapbox://styles/mapbox/streets-v12'); ?>>Streets</option>
                                        <option value="mapbox://styles/mapbox/outdoors-v12" <?php selected($map_style, 'mapbox://styles/mapbox/outdoors-v12'); ?>>Outdoors</option>
                                        <option value="mapbox://styles/mapbox/light-v11" <?php selected($map_style, 'mapbox://styles/mapbox/light-v11'); ?>>Light</option>
                                        <option value="mapbox://styles/mapbox/dark-v11" <?php selected($map_style, 'mapbox://styles/mapbox/dark-v11'); ?>>Dark</option>
                                        <option value="mapbox://styles/mapbox/satellite-v9" <?php selected($map_style, 'mapbox://styles/mapbox/satellite-v9'); ?>>Satellite</option>
                                        <option value="mapbox://styles/mapbox/satellite-streets-v12" <?php selected($map_style, 'mapbox://styles/mapbox/satellite-streets-v12'); ?>>Satellite Streets</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('自动同步', 'strava-running-sync'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="auto_sync" value="1" <?php checked($auto_sync, 1); ?> />
                                        <?php _e('每小时自动同步活动', 'strava-running-sync'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('保存设置', 'strava-running-sync'); ?>" />
                        </p>
                    </form>
                    
                    <?php if ($access_token): ?>
                        <hr />
                        <h2><?php _e('数据同步', 'strava-running-sync'); ?></h2>
                        <form method="post" action="" style="display: inline;">
                            <?php wp_nonce_field('srs_sync_now'); ?>
                            <p>
                                <?php if ($last_sync): ?>
                                    <?php _e('上次同步时间：', 'strava-running-sync'); ?> <?php echo $last_sync; ?>
                                <?php else: ?>
                                    <?php _e('尚未同步', 'strava-running-sync'); ?>
                                <?php endif; ?>
                            </p>
                            <input type="submit" name="sync_now" class="button button-secondary" value="<?php _e('立即同步', 'strava-running-sync'); ?>" />
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="srs-admin-sidebar">
                    <div class="srs-admin-box">
                        <h3><?php _e('使用说明', 'strava-running-sync'); ?></h3>
                        <ol>
                            <li><?php _e('在 Strava 上创建 API 应用', 'strava-running-sync'); ?></li>
                            <li><?php _e('填入 Client ID 和 Secret', 'strava-running-sync'); ?></li>
                            <li><?php _e('点击"连接到 Strava"授权', 'strava-running-sync'); ?></li>
                            <li><?php _e('使用短代码 [strava_running_display] 显示数据', 'strava-running-sync'); ?></li>
                        </ol>
                    </div>
                    
                    <div class="srs-admin-box">
                        <h3><?php _e('短代码参数', 'strava-running-sync'); ?></h3>
                        <ul>
                            <li><code>type</code> - both, table, map, stats</li>
                            <li><code>limit</code> - 显示活动数量</li>
                            <li><code>map_height</code> - 地图高度</li>
                        </ul>
                        <p><?php _e('示例：', 'strava-running-sync'); ?></p>
                        <code>[strava_running_display type="both" limit="20" map_height="600px"]</code>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .srs-admin-container {
                display: flex;
                gap: 20px;
                margin-top: 20px;
            }
            .srs-admin-main {
                flex: 1;
                background: white;
                padding: 20px;
                border: 1px solid #ddd;
            }
            .srs-admin-sidebar {
                width: 300px;
            }
            .srs-admin-box {
                background: white;
                padding: 15px;
                border: 1px solid #ddd;
                margin-bottom: 20px;
            }
            .srs-admin-box h3 {
                margin-top: 0;
            }
        </style>
        <?php
    }
    
    public static function renderActivitiesPage() {
        $db = new SRS_Database();
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $activities = $db->getActivities($page, 20);
        
        ?>
        <div class="wrap">
            <h1><?php _e('活动列表', 'strava-running-sync'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('日期', 'strava-running-sync'); ?></th>
                        <th><?php _e('活动名称', 'strava-running-sync'); ?></th>
                        <th><?php _e('类型', 'strava-running-sync'); ?></th>
                        <th><?php _e('距离', 'strava-running-sync'); ?></th>
                        <th><?php _e('时长', 'strava-running-sync'); ?></th>
                        <th><?php _e('平均速度', 'strava-running-sync'); ?></th>
                        <th><?php _e('爬升', 'strava-running-sync'); ?></th>
                        <th><?php _e('操作', 'strava-running-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities['activities'] as $activity): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($activity['start_date_local'])); ?></td>
                            <td><?php echo esc_html($activity['name']); ?></td>
                            <td><?php echo esc_html($activity['type']); ?></td>
                            <td><?php echo number_format($activity['distance'] / 1000, 2); ?> km</td>
                            <td><?php echo gmdate('H:i:s', $activity['moving_time']); ?></td>
                            <td><?php echo number_format($activity['average_speed'] * 3.6, 2); ?> km/h</td>
                            <td><?php echo number_format($activity['total_elevation_gain'], 0); ?> m</td>
                            <td>
                                <a href="https://www.strava.com/activities/<?php echo $activity['strava_id']; ?>" target="_blank">
                                    <?php _e('查看', 'strava-running-sync'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php
            $total_pages = $activities['total_pages'];
            if ($total_pages > 1):
                $pagination_args = [
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $page,
                    'total' => $total_pages,
                ];
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links($pagination_args);
                echo '</div></div>';
            endif;
            ?>
        </div>
        <?php
    }
}