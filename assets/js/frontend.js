jQuery(document).ready(function($) {
    let map = null;
    let markers = [];
    let routes = [];
    let currentFilter = 'all';
    
    // 主题适应功能
    function detectAndApplyTheme() {
        const container = $('.srs-container');
        if (!container.length) return;
        
        // 检测主题背景颜色
        const bodyBg = $('body').css('background-color');
        const contentBg = $('.site-content, .entry-content, main, article').first().css('background-color');
        const textColor = $('body, .site-content, main').first().css('color');
        
        // 如果检测到非白色背景，应用主题适应类
        if (bodyBg && bodyBg !== 'rgb(255, 255, 255)' && bodyBg !== 'rgba(255, 255, 255, 1)' && bodyBg !== 'transparent') {
            container.addClass('theme-adaptive');
        }
        
        // 动态设置CSS变量
        const computedStyles = {
            textColor: textColor || 'inherit',
            linkColor: $('a').first().css('color') || '#0073aa',
            borderColor: rgba2hex(adjustBrightness(textColor, -0.5)) || '#ddd'
        };
        
        container.css({
            '--srs-text-color': computedStyles.textColor,
            '--srs-link-color': computedStyles.linkColor,
            '--srs-border-color': computedStyles.borderColor,
            '--srs-border-light-color': adjustOpacity(computedStyles.borderColor, 0.5)
        });
    }
    
    // 颜色工具函数
    function rgba2hex(rgb) {
        if (!rgb || rgb === 'transparent') return null;
        const match = rgb.match(/\d+/g);
        if (!match || match.length < 3) return null;
        return '#' + ((1 << 24) + (parseInt(match[0]) << 16) + (parseInt(match[1]) << 8) + parseInt(match[2])).toString(16).slice(1);
    }
    
    function adjustBrightness(color, amount) {
        if (!color || color === 'transparent') return null;
        const match = color.match(/\d+/g);
        if (!match || match.length < 3) return null;
        
        const r = Math.max(0, Math.min(255, parseInt(match[0]) + (amount * 255)));
        const g = Math.max(0, Math.min(255, parseInt(match[1]) + (amount * 255)));
        const b = Math.max(0, Math.min(255, parseInt(match[2]) + (amount * 255)));
        
        return `rgb(${r}, ${g}, ${b})`;
    }
    
    function adjustOpacity(color, opacity) {
        if (!color) return null;
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        return `rgba(${r}, ${g}, ${b}, ${opacity})`;
    }
    
    // 应用主题检测
    detectAndApplyTheme();
    
    function initMap() {
        const mapElement = document.getElementById('srs-map');
        if (!mapElement) return;
        
        // 先计算初始边界
        let initialBounds = null;
        let initialCenter = [116.4074, 39.9042]; // 默认中心
        let initialZoom = 10; // 默认缩放
        
        if (typeof srsActivities !== 'undefined' && srsActivities.length > 0) {
            // 计算最近活动的边界
            const sortedActivities = srsActivities.sort((a, b) => {
                return new Date(b.start_date_local) - new Date(a.start_date_local);
            });
            
            const recentBounds = new mapboxgl.LngLatBounds();
            const recentCount = Math.min(5, sortedActivities.length);
            let hasValidBounds = false;
            
            for (let i = 0; i < recentCount; i++) {
                const activity = sortedActivities[i];
                if (activity.start_latlng) {
                    const latlng = JSON.parse(activity.start_latlng);
                    if (latlng && latlng.length === 2) {
                        recentBounds.extend([latlng[1], latlng[0]]);
                        hasValidBounds = true;
                    }
                }
                if (activity.polyline) {
                    const decodedPath = decodePolyline(activity.polyline);
                    if (decodedPath && decodedPath.length > 0) {
                        decodedPath.forEach(coord => recentBounds.extend(coord));
                        hasValidBounds = true;
                    }
                }
            }
            
            if (hasValidBounds && !recentBounds.isEmpty()) {
                initialCenter = recentBounds.getCenter().toArray();
                // 根据边界计算合适的缩放级别
                const ne = recentBounds.getNorthEast();
                const sw = recentBounds.getSouthWest();
                const latDiff = ne.lat - sw.lat;
                const lngDiff = ne.lng - sw.lng;
                const maxDiff = Math.max(latDiff, lngDiff);
                
                if (maxDiff < 0.01) initialZoom = 14;
                else if (maxDiff < 0.05) initialZoom = 13;
                else if (maxDiff < 0.1) initialZoom = 12;
                else if (maxDiff < 0.5) initialZoom = 11;
                else initialZoom = 10;
            }
        }
        
        try {
            // 使用计算出的初始位置创建地图
            const mapConfig = {
                container: 'srs-map',
                center: initialCenter,
                zoom: initialZoom
            };
            
            if (!srs_ajax.mapbox_token || srs_ajax.mapbox_token === '') {
                mapConfig.style = 'https://api.maptiler.com/maps/streets/style.json?key=get_your_own_OpIi9ZULNHzrESv6T2vL';
            } else {
                mapboxgl.accessToken = srs_ajax.mapbox_token;
                mapConfig.style = srs_ajax.map_style || 'mapbox://styles/mapbox/streets-v12';
            }
            
            map = new mapboxgl.Map(mapConfig);
            map.addControl(new mapboxgl.NavigationControl());
            
            // 等待地图加载完成
            map.on('load', function() {
                loadActivitiesOnMap(true); // 传入参数表示初始加载
            });
            
            // 处理加载错误
            map.on('error', function(e) {
                console.error('Mapbox error:', e);
                mapElement.innerHTML = '<p style="text-align: center; padding: 20px;">地图加载失败，请检查 Mapbox Token 是否有效</p>';
            });
            
        } catch (error) {
            console.error('Map initialization error:', error);
            mapElement.innerHTML = '<p style="text-align: center; padding: 20px;">地图初始化失败：' + error.message + '</p>';
        }
    }
    
    function loadActivitiesOnMap(skipFitBounds = false) {
        if (!map) {
            console.log('Map not initialized');
            return;
        }
        
        if (typeof srsActivities === 'undefined' || srsActivities.length === 0) {
            console.log('No activities data available');
            return;
        }
        
        // 清除现有标记和路线
        markers.forEach(marker => marker.remove());
        markers = [];
        
        routes.forEach(routeId => {
            if (map.getLayer(routeId)) {
                map.removeLayer(routeId);
            }
            if (map.getSource(routeId)) {
                map.removeSource(routeId);
            }
        });
        routes = [];
        
        const bounds = new mapboxgl.LngLatBounds();
        
        const filteredActivities = currentFilter === 'all' ? 
            srsActivities : 
            srsActivities.filter(activity => activity.type === currentFilter);
        
        // 按日期排序，最新的在前
        const sortedActivities = filteredActivities.sort((a, b) => {
            return new Date(b.start_date_local) - new Date(a.start_date_local);
        });
        
        // 用于存储最近活动的位置
        let recentActivityBounds = null;
        const recentCount = 5; // 最近5次活动
        
        sortedActivities.forEach(function(activity, index) {
            // 不再创建标记，只处理轨迹
            if (activity.start_latlng) {
                const latlng = JSON.parse(activity.start_latlng);
                if (latlng && latlng.length === 2) {
                    bounds.extend([latlng[1], latlng[0]]);
                    
                    // 收集最近活动的位置
                    if (index < recentCount) {
                        if (!recentActivityBounds) {
                            recentActivityBounds = new mapboxgl.LngLatBounds();
                        }
                        recentActivityBounds.extend([latlng[1], latlng[0]]);
                    }
                }
            }
            
            if (activity.polyline) {
                const decodedPath = decodePolyline(activity.polyline);
                if (decodedPath && decodedPath.length > 0) {
                    const routeId = 'route-' + activity.id;
                    routes.push(routeId);
                    
                    // 添加路线
                    map.addSource(routeId, {
                        'type': 'geojson',
                        'data': {
                            'type': 'Feature',
                            'properties': {},
                            'geometry': {
                                'type': 'LineString',
                                'coordinates': decodedPath
                            }
                        }
                    });
                    
                    map.addLayer({
                        'id': routeId,
                        'type': 'line',
                        'source': routeId,
                        'layout': {
                            'line-join': 'round',
                            'line-cap': 'round'
                        },
                        'paint': {
                            'line-color': getActivityColor(activity.type),
                            'line-width': 2.5,
                            'line-opacity': 0.7
                        }
                    });
                    
                    decodedPath.forEach(coord => {
                        bounds.extend(coord);
                        // 收集最近活动的路线点
                        if (index < recentCount && recentActivityBounds) {
                            recentActivityBounds.extend(coord);
                        }
                    });
                }
            }
        });
        
        // 只有在非初始加载时才调整地图视图
        if (!skipFitBounds) {
            if (recentActivityBounds && !recentActivityBounds.isEmpty()) {
                map.fitBounds(recentActivityBounds, { 
                    padding: 80,
                    maxZoom: 14
                });
            } else if (!bounds.isEmpty()) {
                map.fitBounds(bounds, { 
                    padding: 50,
                    maxZoom: 13 
                });
            }
        }
    }
    
    function getActivityColor(type) {
        const colors = {
            'Run': '#007AFF',
            'Walk': '#AF52DE', 
            'Ride': '#34C759',
            'VirtualRide': '#FF9500'
        };
        return colors[type] || '#007AFF';
    }
    
    function escapeHtml(unsafe) {
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }
    
    function formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }
    
    function formatPace(time, distance) {
        if (distance === 0) return '-';
        
        const paceSeconds = time / (distance / 1000);
        const paceMinutes = Math.floor(paceSeconds / 60);
        const paceSecs = Math.round(paceSeconds % 60);
        
        return `${paceMinutes}:${paceSecs.toString().padStart(2, '0')} /km`;
    }
    
    function formatSpeedOrPace(type, time, distance) {
        if (distance === 0) return '-';
        
        if (['Ride', 'VirtualRide'].includes(type)) {
            const speed = (distance / 1000) / (time / 3600);
            return `${speed.toFixed(1)} km/h`;
        } else {
            return formatPace(time, distance);
        }
    }
    
    function getActivityTypeLabel(type) {
        const labels = {
            'Run': '跑步',
            'Walk': '步行',
            'Ride': '骑行',
            'VirtualRide': '虚拟骑行'
        };
        return labels[type] || type;
    }
    
    function updateStatistics(type) {
        $.ajax({
            url: srs_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'srs_get_filtered_stats',
                type: type,
                nonce: srs_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const stats = response.data;
                    
                    $('.srs-stat-item').each(function() {
                        const $item = $(this);
                        const $value = $item.find('.srs-stat-value');
                        const label = $item.find('.srs-stat-label').text();
                        
                        if (label.includes('总活动数')) {
                            $value.text(parseInt(stats.total_activities));
                        } else if (label.includes('总距离')) {
                            $value.text((stats.total_distance / 1000).toFixed(2) + ' km');
                        } else if (label.includes('总时长')) {
                            $value.text(formatTime(stats.total_time));
                        } else if (label.includes('总爬升')) {
                            $value.text(Math.round(stats.total_elevation) + ' m');
                        } else if (label.includes('平均距离')) {
                            $value.text((stats.avg_distance / 1000).toFixed(2) + ' km');
                        } else if (label.includes('平均速度')) {
                            $value.text((stats.avg_speed * 3.6).toFixed(2) + ' km/h');
                        }
                    });
                }
            }
        });
    }
    
    function filterTableActivities(type) {
        $('.srs-table tbody tr').each(function() {
            const $row = $(this);
            
            if (type === 'all') {
                $row.show();
            } else {
                if ($row.hasClass('srs-activity-' + type.toLowerCase())) {
                    $row.show();
                } else {
                    $row.hide();
                }
            }
        });
    }
    
    // 活动类型筛选
    $(document).on('click', '.srs-filter-btn', function() {
        const $btn = $(this);
        const type = $btn.data('type');
        
        $('.srs-filter-btn').removeClass('active');
        $btn.addClass('active');
        
        currentFilter = type;
        
        // 更新统计数据
        updateStatistics(type === 'all' ? null : type);
        
        // 更新地图 - 确保地图已初始化
        if (map && map.loaded()) {
            loadActivitiesOnMap(false); // 筛选时允许调整视图
        } else if (map) {
            map.on('load', function() {
                loadActivitiesOnMap(false); // 筛选时允许调整视图
            });
        }
        
        // 筛选表格
        filterTableActivities(type);
    });
    
    // 表格行点击事件 - 高亮对应的路线
    $(document).on('click', '.srs-table tbody tr', function() {
        if ($(this).is(':hidden')) return;
        
        const activityId = $(this).data('activity-id');
        
        $('.srs-table tbody tr').removeClass('active');
        $(this).addClass('active');
        
        if (map) {
            // 重置所有路线的透明度
            routes.forEach(routeId => {
                if (map.getLayer(routeId)) {
                    map.setPaintProperty(routeId, 'line-opacity', 0.3);
                    map.setPaintProperty(routeId, 'line-width', 2);
                }
            });
            
            // 高亮选中的路线
            const selectedRouteId = 'route-' + activityId;
            if (map.getLayer(selectedRouteId)) {
                map.setPaintProperty(selectedRouteId, 'line-opacity', 1);
                map.setPaintProperty(selectedRouteId, 'line-width', 3);
                
                // 将选中的路线移到最上层
                map.moveLayer(selectedRouteId);
            }
            
            // 如果有路线数据，聚焦到该路线
            const activity = srsActivities.find(a => a.id == activityId);
            if (activity && activity.polyline) {
                const decodedPath = decodePolyline(activity.polyline);
                if (decodedPath && decodedPath.length > 0) {
                    const routeBounds = new mapboxgl.LngLatBounds();
                    decodedPath.forEach(coord => routeBounds.extend(coord));
                    map.fitBounds(routeBounds, { 
                        padding: 100,
                        maxZoom: 14 
                    });
                }
            }
        }
    });
    
    // 加载更多活动
    $('#srs-load-more').on('click', function() {
        const button = $(this);
        const page = parseInt(button.data('page'));
        const totalPages = parseInt(button.data('total'));
        
        button.prop('disabled', true).text('加载中...');
        
        $.ajax({
            url: srs_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'srs_get_activities',
                page: page,
                per_page: 20,
                nonce: srs_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.activities) {
                    const activities = response.data.activities;
                    
                    activities.forEach(function(activity) {
                        const row = createTableRow(activity);
                        $('.srs-table tbody').append(row);
                        srsActivities.push(activity);
                    });
                    
                    if (page < totalPages) {
                        button.data('page', page + 1).prop('disabled', false).text('加载更多');
                    } else {
                        button.hide();
                    }
                    
                    // 更新地图时不改变视图
                    if (map && map.loaded()) {
                        loadActivitiesOnMap(true); // 加载更多时跳过视图调整
                    }
                }
            },
            error: function() {
                button.prop('disabled', false).text('加载更多');
            }
        });
    });
    
    function createTableRow(activity) {
        const distance = (activity.distance / 1000).toFixed(2);
        const duration = formatTime(activity.moving_time);
        const speedOrPace = formatSpeedOrPace(activity.type, activity.moving_time, activity.distance);
        const elevation = Math.round(activity.total_elevation_gain);
        const heartrate = activity.average_heartrate ? Math.round(activity.average_heartrate) + ' bpm' : '-';
        const date = new Date(activity.start_date_local).toISOString().split('T')[0];
        const typeLabel = getActivityTypeLabel(activity.type);
        const typeLower = activity.type.toLowerCase();
        
        return `
            <tr data-activity-id="${parseInt(activity.id)}" class="srs-activity-${escapeHtml(typeLower)}">
                <td>${date}</td>
                <td><span class="srs-activity-type">${escapeHtml(typeLabel)}</span></td>
                <td>${distance} km</td>
                <td>${duration}</td>
                <td>${speedOrPace}</td>
                <td>${elevation} m</td>
                <td>${heartrate}</td>
            </tr>
        `;
    }
    
    // Polyline 解码函数 (适配 Mapbox)
    function decodePolyline(encoded) {
        const points = [];
        let index = 0, lat = 0, lng = 0;
        
        while (index < encoded.length) {
            let b, shift = 0, result = 0;
            do {
                b = encoded.charCodeAt(index++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while (b >= 0x20);
            
            const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1));
            lat += dlat;
            
            shift = 0;
            result = 0;
            do {
                b = encoded.charCodeAt(index++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while (b >= 0x20);
            
            const dlng = ((result & 1) ? ~(result >> 1) : (result >> 1));
            lng += dlng;
            
            points.push([lng / 1E5, lat / 1E5]); // Mapbox 使用 [lng, lat] 顺序
        }
        
        return points;
    }
    
    // 初始化 - 确保 Mapbox GL JS 已加载
    if (typeof mapboxgl !== 'undefined') {
        initMap();
    } else {
        console.error('Mapbox GL JS not loaded');
        const mapElement = document.getElementById('srs-map');
        if (mapElement) {
            mapElement.innerHTML = '<p style="text-align: center; padding: 20px;">Mapbox GL JS 加载失败</p>';
        }
    }
});