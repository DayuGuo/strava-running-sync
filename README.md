# Strava Running Sync - WordPress 插件

一个功能强大的开源 WordPress 插件，可以自动同步Strava跑步数据并在网站上进行可视化展示。

## 功能特性

- 🏃‍♀️ **多运动支持** - 自动同步跑步、步行、骑行等运动数据
- 📊 **统计展示** - 显示总距离、总时长、平均配速等统计信息
- 🗺️ **地图可视化** - 在交互式地图上展示运动轨迹
- 📋 **数据表格** - 以表格形式展示详细的活动列表，适配电脑和手机端
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
   - Website: 你的网站地址（如 https://anotherdayu.com/）
   - Authorization Callback Domain: 你的域名（如：example.com）
   - **重要**：Authorization Callback Domain 只填写域名，不要包含协议和路径。（如 anotherdayu.com）
4. 保存Client ID和Client Secret

**回调URL配置说明：**
- Strava的Authorization Callback Domain只需要填写域名（如：`example.com`）
- 完整的回调URL会是：`https://example.com/wp-admin/admin.php?page=strava-running-sync&action=callback`
- 如果你的WordPress安装在子目录或使用自定义域名，可以在插件设置中修改回调URL。


### 2. 安装插件

1. 将插件文件夹上传到 `/wp-content/plugins/` 目录；或直接在插件页面上传插件压缩包。
2. 在WordPress后台激活插件。

### 3. 配置插件

1. 在WordPress后台进入"Strava Running"设置页面
2. 填入从 Strava 获取的 Client ID 和 Client Secret
3. 点击「连接到Strava」按钮完成OAuth授权
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

## 技术支持

如遇到问题或需要技术支持，请：

1. 检查WordPress和PHP版本兼容性
2. 确认Strava API配置正确
3. 验证回调URL配置
4. 查看WordPress错误日志

## 开源协议

本插件采用GPL v2协议开源。
---

享受跑步，享受数据！🏃‍♀️💨