# 电池电量显示功能 🔋

## 功能概述

在Tauri桌面应用和网页client端都添加了笔记本电池电量显示功能，自动检测并显示电池状态。

## 特性

### 1. 多平台支持 💻
- ✅ **Windows**: 通过WMIC命令获取电池信息
- ✅ **Linux**: 从/sys/class/power_supply读取电池数据
- ✅ **macOS**: 使用pmset命令获取电池状态

### 2. 向后兼容 🔄
- ✅ **自动检测**: 如果没有电池数据，不显示电池信息
- ✅ **台式机兼容**: 台式机自动隐藏电池显示
- ✅ **旧版本兼容**: 旧版本客户端不发送电池数据时不会报错

### 3. 实时显示 ⚡
- 电量百分比
- 充电状态（充电中/使用电池/已充满/电量低）
- 可视化进度条
- 动态颜色指示

## 技术实现

### Tauri端 (Rust)

#### 1. 数据结构
```rust
#[derive(Serialize, Clone)]
struct BatteryInfo {
    percentage: f32,      // 电量百分比 0-100
    is_charging: bool,    // 是否正在充电
    status: String,       // 状态文本
}
```

#### 2. Windows实现
```rust
// 使用WMIC命令获取电池信息
WMIC Path Win32_Battery Get EstimatedChargeRemaining,BatteryStatus
```

#### 3. Linux实现
```bash
# 从sysfs读取
/sys/class/power_supply/BAT0/capacity
/sys/class/power_supply/BAT0/status
```

#### 4. macOS实现
```bash
# 使用pmset命令
pmset -g batt
```

### Client端 (PHP + JavaScript)

#### 1. 数据库字段
```sql
ALTER TABLE device_stats ADD COLUMN battery_percentage FLOAT;
ALTER TABLE device_stats ADD COLUMN battery_is_charging TINYINT;
ALTER TABLE device_stats ADD COLUMN battery_status VARCHAR(50);
```

#### 2. API接收
```php
// client/api/receive.php
if (isset($data['battery']) && is_array($data['battery'])) {
    $batteryPercentage = $data['battery']['percentage'] ?? null;
    $batteryIsCharging = isset($data['battery']['is_charging']) ? (int)$data['battery']['is_charging'] : null;
    $batteryStatus = $data['battery']['status'] ?? null;
}
```

#### 3. 前端显示
```javascript
// 兼容性检查
if (!stats || stats.battery_percentage === null) {
    batteryContainer.classList.add('hidden');
    return;
}
```

## 界面展示

### 笔记本（有电池）
```
┌─────────────────────────────────────────┐
│ 🔋 电池状态: 充电中           85%       │
│ ████████████████████████░░░░░░░         │
└─────────────────────────────────────────┘
```

### 台式机（无电池）
```
（不显示电池信息）
```

## 颜色指示

| 状态 | 颜色 | 条件 |
|------|------|------|
| 充电中 | 🟢 绿色 | is_charging = true |
| 电量充足 | 🔵 蓝色 | percentage > 50% |
| 电量中等 | 🟡 黄色 | 20% < percentage ≤ 50% |
| 电量低 | 🔴 红色 | percentage ≤ 20% |

## 安装步骤

### 新安装

1. **数据库会自动创建字段**
   - 运行安装向导会自动创建包含电池字段的表

### 现有安装（升级）

1. **运行迁移脚本**
   ```
   访问: http://your-domain/client/install/migrate_battery.php
   ```

2. **或手动执行SQL**

   **SQLite:**
   ```sql
   ALTER TABLE device_stats ADD COLUMN battery_percentage REAL;
   ALTER TABLE device_stats ADD COLUMN battery_is_charging INTEGER;
   ALTER TABLE device_stats ADD COLUMN battery_status TEXT;
   ```

   **MySQL:**
   ```sql
   ALTER TABLE device_stats ADD COLUMN battery_percentage FLOAT AFTER memory_percent;
   ALTER TABLE device_stats ADD COLUMN battery_is_charging TINYINT AFTER battery_percentage;
   ALTER TABLE device_stats ADD COLUMN battery_status VARCHAR(50) AFTER battery_is_charging;
   ```

3. **更新Tauri应用**
   - 重新编译并安装最新版本的Tauri应用

## API数据格式

### Tauri → Server
```json
{
  "computer_name": "MY-LAPTOP",
  "uptime": 3600,
  "cpu_usage": [10.5, 12.3],
  "memory_usage": {
    "total": 16000000000,
    "used": 8000000000,
    "percent": 50.0
  },
  "battery": {
    "percentage": 85.0,
    "is_charging": true,
    "status": "充电中"
  }
}
```

### Server → Client (API Response)
```json
{
  "latest_stats": {
    "battery_percentage": 85.0,
    "battery_is_charging": 1,
    "battery_status": "充电中"
  }
}
```

## 测试方法

### 1. 测试Tauri应用
```bash
# 开发模式
cd src-tauri
cargo tauri dev

# 访问API端点
curl http://localhost:3000/api/system
```

### 2. 测试Client端
```bash
# 运行迁移
访问: http://localhost/client/install/migrate_battery.php

# 查看设备详情
访问: http://localhost/client/public/device.php?id=1
```

### 3. 验证数据库
```sql
SELECT battery_percentage, battery_is_charging, battery_status
FROM device_stats
ORDER BY timestamp DESC
LIMIT 10;
```

## 故障排查

### 问题1: 笔记本不显示电池信息

**可能原因:**
- Tauri应用版本过旧
- 数据库未更新
- 电池驱动问题

**解决方法:**
1. 更新Tauri应用到最新版本
2. 运行数据库迁移脚本
3. 检查系统电池驱动是否正常

### 问题2: 台式机显示了电池信息

**原因**: 这不应该发生，但如果发生了：

**检查:**
```sql
-- 查看是否有错误的电池数据
SELECT * FROM device_stats WHERE battery_percentage IS NOT NULL;
```

### 问题3: 迁移脚本失败

**解决方法:**
1. 手动执行SQL语句
2. 检查数据库权限
3. 查看错误日志

## 兼容性说明

### Tauri应用
- ✅ Windows 7/8/10/11
- ✅ Linux (Kernel 2.6+)
- ✅ macOS 10.13+

### Client网页端
- ✅ 支持旧版本客户端（不发送电池数据）
- ✅ 新旧版本混合环境
- ✅ 台式机和笔记本混合环境

## 性能影响

- **额外数据库字段**: 3个字段（约12-20字节/记录）
- **额外网络传输**: 约50-100字节/次
- **计算开销**: 极小（单次命令调用）
- **更新频率**: 随系统信息一起更新（通常10秒/次）

## 未来改进

- [ ] 显示预计剩余时间
- [ ] 电池健康度显示
- [ ] 低电量警告通知
- [ ] 历史电量曲线图
- [ ] 充电/放电速率

## 相关文件

### Tauri端
- `src-tauri/src/main.rs` - 主要逻辑和电池获取
- `src-tauri/Cargo.toml` - 依赖配置

### Client端
- `client/includes/database.php` - 数据库schema
- `client/api/receive.php` - 接收电池数据
- `client/public/device.php` - 显示电池信息
- `client/public/assets/js/device.js` - 前端渲染逻辑
- `client/install/migrate_battery.php` - 数据库迁移脚本

## 总结

电池电量显示功能已完整实现，具有以下优势：

1. ✅ **跨平台**: 支持Windows/Linux/macOS
2. ✅ **智能检测**: 自动识别笔记本/台式机
3. ✅ **向后兼容**: 不影响旧版本使用
4. ✅ **实时更新**: 电量变化实时显示
5. ✅ **视觉友好**: 颜色和图标清晰直观

现在用户可以在监控界面实时查看笔记本的电池状态！🔋⚡

