# Tauri应用媒体监控设置指南

## 📱 设置页面说明

我已经为Tauri应用添加了完整的媒体监控设置界面，位于**设置页面**的底部。

## 🎨 新增的设置卡片

### 媒体播放监控设置

这个新增的设置卡片包含以下选项：

#### 1. **启用媒体监控** 🎵
- **功能**: 启用或禁用媒体播放状态监控
- **默认**: 启用
- **说明**: 关闭后将不会监控或发送任何媒体信息

#### 2. **发送封面缩略图** 🖼️
- **功能**: 是否上传媒体封面到服务器
- **默认**: 关闭（节省流量）
- **依赖**: 需要先启用媒体监控
- **影响**: 
  - ✅ 启用：每次推送增加约16KB流量
  - ❌ 禁用：仅发送文本信息（约500字节）

#### 3. **压缩缩略图** 🗜️
- **功能**: 压缩图片以减少大小
- **默认**: 启用
- **依赖**: 需要启用媒体监控和发送封面
- **效果**: 自动调整JPEG质量以满足大小限制

#### 4. **缩略图最大大小** 📏
- **功能**: 设置压缩后图片的最大大小
- **默认**: 16 KB
- **范围**: 4-64 KB
- **推荐值**:
  - 8 KB - 低质量（节省流量）
  - 16 KB - 平衡推荐 ✨
  - 32 KB - 高质量（占用空间较大）

## 🎯 使用流程

### 基础使用（不发送封面）

1. 打开应用，点击左侧导航栏的 **"设置"**
2. 滚动到 **"媒体播放监控设置"** 卡片
3. 确保 **"启用媒体监控"** 已开启
4. 保持 **"发送封面缩略图"** 关闭
5. 开始播放音乐或视频

### 完整使用（包含封面）

1. 在设置页面找到 **"媒体播放监控设置"**
2. 启用 **"启用媒体监控"** ✅
3. 启用 **"发送封面缩略图"** ✅
4. 启用 **"压缩缩略图"** ✅（推荐）
5. 设置 **"缩略图最大大小"** (推荐16KB)
6. 点击 **"保存"** 按钮
7. 开始播放媒体

## 💡 配置说明

### 配置文件位置

设置会自动保存到：
- **Windows**: `C:\Users\{用户名}\AppData\Roaming\watchmedo\media_settings.json`
- **Linux**: `~/.config/watchmedo/media_settings.json`
- **macOS**: `~/Library/Application Support/watchmedo/media_settings.json`

### 配置文件结构

```json
{
  "enabled": true,
  "send_thumbnail": false,
  "compress_thumbnail": true,
  "thumbnail_max_size_kb": 16
}
```

## ⚙️ 技术实现

### 前端 (React/TypeScript)

**接口定义**:
```typescript
interface MediaSettings {
  enabled: boolean;
  send_thumbnail: boolean;
  compress_thumbnail: boolean;
  thumbnail_max_size_kb: number;
}
```

**Tauri Commands**:
```typescript
// 读取设置
const settings = await invoke<MediaSettings>("get_media_settings");

// 保存设置
await invoke("set_media_settings", { settings: newSettings });

// 获取当前媒体信息
const media = await invoke("get_current_media_info");
```

### 后端 (Rust)

**对应的Rust函数**:
```rust
#[tauri::command]
pub fn get_media_settings() -> MediaSettings

#[tauri::command]
pub fn set_media_settings(settings: MediaSettings) -> Result<(), String>

#[tauri::command]
pub async fn get_current_media_info() -> Option<MediaInfo>
```

## 📊 UI特性

### 1. 条件禁用
- 当媒体监控关闭时，所有子选项自动禁用
- 当不发送封面时，压缩选项自动禁用
- 当不压缩时，大小设置自动禁用

### 2. 即时反馈
- 使用Toast通知显示操作结果
- 成功：绿色提示 ✅
- 失败：红色提示 ❌

### 3. 警告提示
- 黄色警告框显示重要注意事项
- 平台限制说明
- 支持的播放器列表

## 🌐 浏览器查看

设置保存后，可以通过以下方式查看效果：

1. **本地HTTP API**:
   ```
   http://localhost:21536/api/system
   ```
   查看 `media` 字段

2. **PHP后端**:
   访问设备详情页面，查看 **"媒体播放状态"** 卡片

## 🔍 调试建议

### 检查设置是否生效

1. **查看配置文件**:
   ```bash
   # Windows PowerShell
   Get-Content "$env:APPDATA\watchmedo\media_settings.json"
   
   # Linux/macOS
   cat ~/.config/watchmedo/media_settings.json
   ```

2. **检查媒体信息获取**:
   - 播放音乐（Spotify、Chrome等）
   - 打开浏览器访问 `http://localhost:21536/api/system`
   - 查看 `media` 字段是否有数据

3. **检查服务器端**:
   - 访问PHP设备详情页面
   - 查看是否显示媒体播放状态卡片

## ⚠️ 常见问题

### Q: 为什么看不到媒体信息？
A: 检查以下几点：
- 确认Windows版本（需要Win10/11）
- 确认播放器支持（Spotify、Chrome、VLC等）
- 确认已启用媒体监控
- 确认正在播放媒体

### Q: 封面没有显示？
A: 检查：
- "发送封面缩略图"是否启用
- 播放器是否提供封面（有些播放器不提供）
- 网络连接是否正常

### Q: 图片质量太低？
A: 调整：
- 增加"缩略图最大大小"（如32KB）
- 确保"压缩缩略图"已启用

### Q: 流量/存储占用太大？
A: 优化：
- 关闭"发送封面缩略图"
- 降低"缩略图最大大小"（如8KB）
- PHP端定期清理旧记录

## 📈 性能影响

### 网络流量对比

| 配置 | 单次推送大小 | 每小时（60秒推送间隔） |
|------|-------------|---------------------|
| 仅文本 | ~500 字节 | ~30 KB |
| 8KB封面 | ~8.5 KB | ~510 KB |
| 16KB封面 | ~16.5 KB | ~990 KB |
| 32KB封面 | ~32.5 KB | ~1.95 MB |

### 存储空间影响

假设每天推送1440次（每分钟一次）：

| 配置 | 每天 | 每周 | 每月 |
|------|------|------|------|
| 仅文本 | ~700 KB | ~4.8 MB | ~20 MB |
| 16KB封面 | ~23 MB | ~161 MB | ~690 MB |

**建议**: 定期清理7天以上的媒体记录

## 🎉 界面预览

设置页面包含：

```
┌─────────────────────────────────────────┐
│ 媒体播放监控设置                          │
│ 配置媒体播放状态监控功能（仅Windows）      │
├─────────────────────────────────────────┤
│ 启用媒体监控              [开关] ✓        │
│ 监控当前播放的音乐/视频信息                │
├─────────────────────────────────────────┤
│ 发送封面缩略图            [开关] ✗        │
│ 上传媒体封面到服务器（会增加网络流量）      │
├─────────────────────────────────────────┤
│ 压缩缩略图                [开关] ✓        │
│ 压缩图片以减少存储空间和网络流量           │
├─────────────────────────────────────────┤
│ 缩略图最大大小 (KB)                       │
│ 压缩后的图片最大大小                      │
│ [16] [保存]                              │
│ 推荐值：8KB、16KB、32KB                   │
├─────────────────────────────────────────┤
│ ⚠️ 注意事项                               │
│ • 此功能仅在Windows 10/11上可用          │
│ • 需要媒体播放器支持Windows Media Control│
│ • 支持的播放器：Spotify、Chrome、VLC等    │
│ • 发送封面会增加网络流量（每次约16KB）     │
└─────────────────────────────────────────┘
```

## 📝 代码示例

### 在其他组件中使用媒体信息

```typescript
import { invoke } from "@tauri-apps/api/tauri";

interface MediaInfo {
  title: string;
  artist?: string;
  album?: string;
  duration?: number;
  position?: number;
  playback_status: string;
  media_type: string;
  thumbnail?: string;
}

// 获取当前播放的媒体
const getMedia = async () => {
  try {
    const media = await invoke<MediaInfo | null>("get_current_media_info");
    if (media) {
      console.log(`正在播放: ${media.title}`);
      if (media.artist) {
        console.log(`艺术家: ${media.artist}`);
      }
    } else {
      console.log("当前没有播放媒体");
    }
  } catch (error) {
    console.error("获取媒体信息失败:", error);
  }
};
```

## 🚀 部署清单

1. ✅ Rust后端代码已完成
2. ✅ PHP后端API已完成
3. ✅ 数据库迁移脚本已完成
4. ✅ PHP前端显示已完成
5. ✅ Tauri设置界面已完成
6. ✅ 配置文件管理已完成

所有功能已就绪，可以直接使用！🎊

