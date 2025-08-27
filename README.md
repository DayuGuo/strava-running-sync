# Strava Running Sync - WordPress 插件

一个功能强大的WordPress插件，可以自动同步Strava跑步数据并在网站上进行可视化展示。

## 功能特性

- 🏃‍♀️ **多运动支持** - 自动同步跑步、步行、骑行等运动数据
- 📊 **统计展示** - 显示总距离、总时长、平均配速等统计信息
- 🗺️ **地图可视化** - 在交互式地图上展示运动轨迹
- 📋 **数据表格** - 以表格形式展示详细的活动列表
- 🎯 **类型筛选** - 支持按运动类型筛选数据
- 🔄 **定时同步** - 支持每小时自动同步最新数据

## 安装与配置

### 1. 创建Strava应用

1. 访问 [Strava API设置页面](https://www.strava.com/settings/api)
2. 点击"Create App"创建新应用
3. 填写应用信息：
   - Application Name: 你的应用名称
   - Category: 选择适当的分类
   - Club: 可留空
   - Website: 你的网站地址
   - Authorization Callback Domain: 你的域名（如：example.com）
   - **重要**：Authorization Callback Domain 只填写域名，不要包含协议和路径
4. 保存Client ID和Client Secret

**回调URL配置说明：**
- Strava的Authorization Callback Domain只需要填写域名（如：`example.com`）
- 完整的回调URL会是：`https://example.com/wp-admin/admin.php?page=strava-running-sync&action=callback`
- 如果你的WordPress安装在子目录或使用自定义域名，可以在插件设置中修改回调URL

### 2. 安装插件

1. 将插件文件夹上传到 `/wp-content/plugins/` 目录
2. 在WordPress后台激活插件

### 3. 配置插件

1. 在WordPress后台进入"Strava Running"设置页面
2. 填入从Strava获取的Client ID和Client Secret
3. 点击"连接到Strava"按钮完成OAuth授权
4. 配置显示选项（地图样式、自动同步等）
5. 点击"立即同步"进行首次数据同步

## 使用方法

### 短代码参数

使用 `[strava_running_display]` 短代码在页面或文章中显示跑步数据。

支持的参数：

- `type` - 显示类型
  - `both` - 同时显示统计、地图和表格（默认）
  - `stats` - 仅显示统计信息
  - `map` - 仅显示地图
  - `table` - 仅显示数据表格
- `limit` - 显示的活动数量（默认50）
- `map_height` - 地图高度（默认500px）

### 示例

```
<!-- 显示所有内容 -->
[strava_running_display]

<!-- 仅显示地图，高度600px -->
[strava_running_display type="map" map_height="600px"]

<!-- 显示统计和表格，限制20个活动 -->
[strava_running_display type="both" limit="20"]

<!-- 仅显示统计信息 -->
[strava_running_display type="stats"]
```

## 数据库结构

插件会创建两个数据表：

1. `wp_srs_activities` - 存储活动基本信息
2. `wp_srs_streams` - 存储活动轨迹数据

## 技术特性

- **响应式设计** - 适配移动设备
- **交互式地图** - 使用Leaflet.js显示路线
- **数据安全** - 支持OAuth 2.0认证
- **性能优化** - 数据缓存和分页加载
- **多语言支持** - 支持中英文界面
- **极简美学** - 受iA Writer启发的无框线设计

## 自定义样式

可以通过CSS自定义插件的外观，主要的CSS类名：

- `.srs-container` - 主容器
- `.srs-statistics` - 统计区域  
- `.srs-map-section` - 地图区域
- `.srs-activities-section` - 活动列表区域
- `.srs-table` - 表格样式
- `.srs-activity-filter` - 类型筛选按钮

## 常见问题

### OAuth认证错误

如果遇到 `Bad Request` 或 `invalid redirect_uri` 错误：

1. **检查回调域名配置**：
   - 确保Strava应用设置中的"Authorization Callback Domain"只包含域名
   - 例如：填写`example.com`而不是`https://example.com/wp-admin/...`

2. **自定义回调URL**：
   - 在插件设置页面的"回调URL"字段中输入完整的回调地址
   - 确保域名与Strava应用设置一致

3. **常见回调URL示例**：
   ```
   主域名安装：https://example.com/wp-admin/admin.php?page=strava-running-sync&action=callback
   子目录安装：https://example.com/blog/wp-admin/admin.php?page=strava-running-sync&action=callback
   子域名安装：https://blog.example.com/wp-admin/admin.php?page=strava-running-sync&action=callback
   ```

## 技术支持

如遇到问题或需要技术支持，请：

1. 检查WordPress和PHP版本兼容性
2. 确认Strava API配置正确
3. 验证回调URL配置
4. 查看WordPress错误日志

## 开源协议

本插件采用GPL v2协议开源。

## 版本历史

### v1.0.0
- 初始版本发布
- 支持Strava数据同步
- 地图和表格可视化
- 基础统计功能

---

享受跑步，享受数据！🏃‍♀️💨