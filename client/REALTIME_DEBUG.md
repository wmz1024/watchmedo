# 实时数据显示调试指南

## 问题排查步骤

### 1. 检查是否有数据上报

首先确认设备是否有数据上报到服务器：

```sql
-- 检查最新的进程记录
SELECT * FROM process_records WHERE device_id = 1 ORDER BY timestamp DESC LIMIT 10;

-- 检查最新的网络记录
SELECT * FROM network_stats WHERE device_id = 1 ORDER BY timestamp DESC LIMIT 10;

-- 检查is_focused字段
SELECT executable_name, window_title, is_focused, timestamp 
FROM process_records 
WHERE device_id = 1 
ORDER BY timestamp DESC 
LIMIT 20;
```

### 2. 测试API接口

访问测试页面: `http://your-domain/client/public/test_realtime.php`

或者直接访问API:
```
http://your-domain/client/api/stats.php?action=realtime&device_id=1
```

### 3. 检查浏览器控制台

打开 `device.php` 页面，按 F12 打开开发者工具，查看 Console 标签：

- 查找 "实时数据响应:" 日志
- 查找 "渲染实时数据:" 日志
- 查找 "进程列表:" 日志
- 查找 "聚焦的应用:" 日志
- 查找 "网络数据:" 日志

### 4. 常见问题

#### 问题1: 显示"暂无实时数据"

**原因**: 
- 设备没有上报数据
- 数据库中没有该设备的记录

**解决**:
1. 确认客户端正在运行并上报数据
2. 检查设备token是否正确
3. 查看数据库中是否有数据

#### 问题2: 显示"当前无活跃应用"

**原因**:
- 所有进程的is_focused字段都是0或false
- 客户端没有正确检测焦点应用

**解决**:
1. 检查客户端是否正确发送is_focused字段
2. 查看数据库中is_focused字段的值

#### 问题3: 网络流量显示为0

**原因**:
- 网络统计数据为空或为0
- 客户端没有正确采集网络数据

**解决**:
1. 检查客户端是否正确采集网络数据
2. 查看数据库network_stats表

### 5. API响应示例

正确的API响应应该是这样的：

```json
{
  "success": true,
  "data": {
    "stats": {
      "device_id": 1,
      "timestamp": "2024-01-01 12:00:00",
      "cpu_usage_avg": 25.5,
      "memory_percent": 60.2
    },
    "processes": [
      {
        "name": "chrome.exe",
        "window_title": "Google Chrome",
        "cpu_usage": 5.2,
        "memory_usage": 524288000,
        "memory_formatted": "500 MB",
        "is_focused": true
      }
    ],
    "network": [
      {
        "name": "以太网",
        "received": 1024000,
        "transmitted": 512000,
        "received_formatted": "1.00 MB",
        "transmitted_formatted": "500 KB"
      }
    ]
  }
}
```

### 6. 修改刷新间隔

如果需要修改自动刷新的时间间隔，编辑 `device.js`:

```javascript
// 将10000改为你想要的毫秒数（例如5000=5秒）
refreshInterval = setInterval(function() {
    loadRealtimeData();
}, 10000);
```

### 7. 禁用自动刷新

如果需要禁用自动刷新，注释掉这行：

```javascript
// 启动实时数据自动刷新（每10秒）
// startRealtimeRefresh();
```

## 客户端数据格式

客户端应该按照以下格式上报数据：

```json
{
  "computer_name": "MY-PC",
  "uptime": 3600,
  "cpu_usage": [10.5, 12.3, 11.8],
  "memory_usage": {
    "total": 16000000000,
    "used": 8000000000,
    "percent": 50.0
  },
  "processes": [
    {
      "executable_name": "chrome.exe",
      "window_title": "Google Chrome - 新标签页",
      "cpu_usage": 5.2,
      "memory": 524288000,
      "is_focused": true
    }
  ],
  "network": [
    {
      "name": "以太网",
      "received": 1024000,
      "transmitted": 512000
    }
  ]
}
```

## 需要帮助?

如果以上步骤都无法解决问题，请：

1. 导出数据库中最新的几条记录
2. 提供浏览器控制台的完整日志
3. 提供API响应的完整内容

