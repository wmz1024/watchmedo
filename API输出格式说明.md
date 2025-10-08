# HTTP API 和远程推送输出格式说明

## 进程信息格式 (ProcessInfo)

每个进程的信息现在按照以下顺序输出：

### JSON 字段顺序
```json
{
  "memory": 1234567,           // 占用内存（字节）
  "is_focused": true,          // 是否为当前聚焦窗口
  "window_title": "示例窗口",   // 窗口标题（如果有）
  "executable_name": "app.exe", // 可执行文件名
  "pid": 12345,                // 进程ID
  "cpu_usage": 15.5            // CPU占用率（百分比）
}
```

### 字段说明

1. **memory** (u64)
   - 进程占用的内存大小，单位：字节
   - 类型：无符号64位整数
   - 示例：`524288000` (500 MB)

2. **is_focused** (bool)
   - 该进程是否为当前聚焦的窗口
   - 类型：布尔值
   - `true` = 当前聚焦，`false` = 非聚焦
   - Windows: 使用 `GetForegroundWindow` API 检测
   - 其他系统: 默认返回 `false`

3. **window_title** (String)
   - 进程主窗口的标题
   - 如果进程有可见窗口，显示窗口标题
   - 如果没有窗口，显示可执行文件名
   - Windows: 使用 `EnumWindows` 和 `GetWindowTextW` 获取
   - 其他系统: 返回可执行文件名

4. **executable_name** (String)
   - 可执行文件的名称
   - 示例：`chrome.exe`, `Code.exe`, `python.exe`

5. **pid** (u32)
   - 进程ID（Process ID）
   - 类型：无符号32位整数
   - 系统唯一标识符

6. **cpu_usage** (f32)
   - CPU占用率百分比
   - 类型：32位浮点数
   - 范围：0.0 - 100.0+
   - 多核系统可能超过100%

## HTTP API 端点

### GET /api/system

返回完整的系统信息，包括进程列表。

#### 响应示例
```json
{
  "computer_name": "MY-PC",
  "uptime": 3600,
  "cpu_usage": [25.5, 30.2, 15.8, 40.1],
  "memory_usage": {
    "total": 17179869184,
    "used": 8589934592,
    "percent": 50.0
  },
  "processes": [
    {
      "memory": 524288000,
      "is_focused": true,
      "window_title": "Visual Studio Code - main.rs",
      "executable_name": "Code.exe",
      "pid": 12345,
      "cpu_usage": 15.5
    },
    {
      "memory": 1073741824,
      "is_focused": false,
      "window_title": "Google Chrome",
      "executable_name": "chrome.exe",
      "pid": 6789,
      "cpu_usage": 8.2
    }
  ],
  "disks": [...],
  "network": [...]
}
```

## 远程推送格式

远程推送使用相同的数据格式，通过 POST 请求发送到配置的 URL。

### 推送内容
```json
{
  "computer_name": "MY-PC",
  "timestamp": "2024-01-01T12:00:00Z",
  "processes": [
    {
      "memory": 524288000,
      "is_focused": true,
      "window_title": "Visual Studio Code - main.rs",
      "executable_name": "Code.exe",
      "pid": 12345,
      "cpu_usage": 15.5
    }
    // ... 更多进程
  ],
  // ... 其他系统信息
}
```

## 设置控制

### 共享设置
可以通过设置页面控制是否共享进程信息：

- `share_processes`: 启用/禁用进程信息共享
- 如果禁用，`processes` 字段将为 `null`

### 进程列表限制设置
可以通过设置页面控制进程列表的数量：

- `process_limit`: 设置进程列表的数量限制（默认：20）
- 进程列表将包含：
  1. **CPU 占用最高的前 N 个进程**（按设置的 process_limit 数量）
  2. **当前聚焦的进程**（如果不在前 N 个中，会额外添加到列表最前面）
  
**示例：**
- 如果设置 `process_limit = 20`
- 当前有一个聚焦的进程（CPU 占用很低，不在前20）
- 最终返回的进程列表会有 **21 个进程**：
  - 1 个聚焦进程（排在最前面）
  - 20 个 CPU 占用最高的进程

**配置位置：** 设置页面 → 应用设置 → API进程列表数量限制

### 使用示例

#### JavaScript/TypeScript
```typescript
fetch('http://localhost:21536/api/system')
  .then(res => res.json())
  .then(data => {
    if (data.processes) {
      data.processes.forEach(proc => {
        console.log(`${proc.is_focused ? '🎯 ' : ''}${proc.window_title}`);
        console.log(`  PID: ${proc.pid}, CPU: ${proc.cpu_usage}%`);
        console.log(`  Memory: ${(proc.memory / 1024 / 1024).toFixed(2)} MB`);
      });
    }
  });
```

#### Python
```python
import requests

response = requests.get('http://localhost:21536/api/system')
data = response.json()

if data.get('processes'):
    for proc in data['processes']:
        focus_icon = '🎯 ' if proc['is_focused'] else ''
        print(f"{focus_icon}{proc['window_title']}")
        print(f"  PID: {proc['pid']}, CPU: {proc['cpu_usage']}%")
        print(f"  Memory: {proc['memory'] / 1024 / 1024:.2f} MB")
```

## 平台兼容性

### Windows
- ✅ 完整支持所有字段
- ✅ 窗口标题检测
- ✅ 聚焦窗口检测

### macOS / Linux
- ✅ 支持基础字段 (memory, pid, cpu_usage, executable_name)
- ⚠️ `is_focused` 始终为 `false`
- ⚠️ `window_title` 返回可执行文件名

## 注意事项

1. **性能影响**：获取窗口标题需要枚举所有窗口，可能有轻微性能开销
2. **权限要求**：某些进程可能需要管理员权限才能获取完整信息
3. **更新频率**：建议每2-5秒更新一次，避免频繁请求
4. **内存单位**：所有内存值以字节为单位，需要自行转换为 MB/GB

