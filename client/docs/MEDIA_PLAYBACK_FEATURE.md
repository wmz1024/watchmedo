# 媒体播放状态监控功能说明

## 功能概述

该功能允许WatchMeDo监控并显示设备当前正在播放的音乐/视频信息，包括：
- 媒体标题
- 艺术家/专辑信息
- 播放进度
- 播放状态（播放中/暂停）
- 媒体封面缩略图（可选）

## 平台支持

- ✅ Windows（使用Windows Media Control API）
- ⚠️ macOS（待实现）
- ⚠️ Linux（待实现）

## 配置说明

### 客户端配置

配置文件位置：`~/.config/watchmedo/media_settings.json`（或Windows对应位置）

默认配置：
```json
{
  "enabled": true,
  "send_thumbnail": false,
  "compress_thumbnail": true,
  "thumbnail_max_size_kb": 16
}
```

配置项说明：
- `enabled`: 是否启用媒体监控（默认：true）
- `send_thumbnail`: 是否发送媒体封面缩略图（默认：false）
- `compress_thumbnail`: 是否压缩缩略图（默认：true）
- `thumbnail_max_size_kb`: 缩略图最大大小（KB）（默认：16KB）

### 修改配置

可以通过Tauri应用的设置页面修改配置，或手动编辑JSON文件。

**注意事项：**
1. 发送封面会增加网络流量和存储空间
2. 建议启用压缩以减少数据传输
3. 压缩质量会根据设置的大小自动调整

## 服务端部署

### 自动迁移

系统会在首次接收到媒体数据时自动创建数据库表，无需手动迁移。

### 手动迁移（可选）

如果需要提前创建表，可以运行：

```bash
cd client/install
php migrate_media.php
```

### 数据库表结构

表名：`media_playback`

字段说明：
- `id`: 主键
- `device_id`: 设备ID（外键）
- `title`: 媒体标题
- `artist`: 艺术家
- `album`: 专辑
- `duration`: 总时长（秒）
- `position`: 当前播放位置（秒）
- `playback_status`: 播放状态（Playing/Paused/Stopped）
- `media_type`: 媒体类型（Music/Video）
- `thumbnail`: Base64编码的缩略图
- `timestamp`: 记录时间

## API接口

### 获取当前播放媒体

```
GET /api/stats.php?action=current_media&device_id={device_id}
```

返回最近1分钟内的媒体播放记录。

**响应示例：**

```json
{
  "success": true,
  "data": {
    "title": "Beautiful Song",
    "artist": "Artist Name",
    "album": "Album Name",
    "duration": 240,
    "position": 60,
    "playback_status": "Playing",
    "media_type": "Music",
    "thumbnail": "base64_encoded_image_data",
    "timestamp": "2025-10-24 12:00:00",
    "time_ago": "5秒前"
  }
}
```

如果没有播放记录，`data` 将为 `null`。

## 前端显示

### 显示位置

在设备详情页面（device.php）的顶部，会显示媒体播放状态卡片。

### 显示逻辑

- 仅当有媒体播放数据时才显示卡片
- 兼容旧版本客户端（无媒体数据时自动隐藏）
- 每10秒自动刷新一次

### 显示内容

1. 媒体标题和艺术家/专辑信息
2. 播放状态标签（播放中/已暂停）
3. 媒体类型标签（音乐/视频）
4. 播放进度条（如果有时长信息）
5. 媒体封面缩略图（如果启用并发送）

## 兼容性说明

### 向后兼容

- ✅ 旧版本客户端不会发送媒体数据，不影响现有功能
- ✅ 如果数据库表不存在，接收API会自动创建
- ✅ 前端会检测是否有媒体数据，无数据时不显示卡片

### 升级指南

从旧版本升级到新版本：

1. **客户端升级**：
   - 下载并安装最新版本Tauri应用
   - 首次运行时会自动创建配置文件
   - 默认不发送封面，可以在设置中启用

2. **服务端升级**：
   - 无需手动操作
   - 系统会在接收到首个媒体数据时自动创建表

## 性能考虑

### 存储空间

- 不包含封面：每条记录约 500 字节
- 包含封面（16KB）：每条记录约 16.5 KB
- 建议定期清理旧记录（超过7天）

### 网络流量

- 不发送封面：约 500 字节/次
- 发送封面（压缩到16KB）：约 16.5 KB/次
- 推送间隔默认60秒

### 清理建议

可以在 `autoCleanOldData` 函数中添加媒体数据清理逻辑：

```php
// 清理7天前的媒体记录
$db->execute(
    'DELETE FROM media_playback WHERE timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)'
);
```

## 常见问题

### Q: 为什么看不到媒体播放信息？

A: 可能的原因：
1. 客户端版本过旧，不支持媒体监控
2. 当前没有播放媒体
3. 媒体播放器不支持Windows Media Control API
4. 客户端配置中禁用了媒体监控

### Q: 支持哪些媒体播放器？

A: 支持所有使用Windows Media Control API的播放器，包括：
- Windows Media Player
- Spotify
- Chrome浏览器（播放网页音视频）
- Edge浏览器
- VLC（较新版本）
- 其他实现了Windows Media Session的应用

### Q: 封面缩略图占用空间太大怎么办？

A: 可以：
1. 关闭封面发送（`send_thumbnail: false`）
2. 启用压缩（`compress_thumbnail: true`）
3. 降低压缩大小（如 `thumbnail_max_size_kb: 8`）
4. 定期清理旧记录

### Q: 如何禁用媒体监控功能？

A: 在客户端配置文件中设置：
```json
{
  "enabled": false
}
```

## 技术实现

### Rust端

- 使用 `windows` crate 调用 Windows Media Control API
- 使用 `image` crate 进行图片压缩
- 异步获取媒体信息

### PHP端

- 自动检测并创建数据库表
- 兼容MySQL和SQLite
- 通过try-catch确保向后兼容

### 前端

- 使用JavaScript异步加载媒体数据
- 自动隐藏/显示媒体卡片
- 实时更新播放进度

## 更新日志

**Version 1.0.0** (2025-10-24)
- ✨ 新增媒体播放状态监控功能
- ✨ 支持封面缩略图（可选）
- ✨ 支持图片压缩
- ✨ 自动数据库迁移
- ✨ 向后兼容旧版本

