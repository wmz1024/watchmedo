# 连续停留时间Bug修复说明 🐛

## 修复日期
2024-01-XX

## 发现的Bug

### Bug 1: 计算逻辑不完整
**问题**：原来的逻辑只查询同一应用的历史记录，没有正确检测其他应用获得焦点的情况。

**场景**：
```
10:00 Chrome 聚焦
10:05 Chrome 聚焦
10:10 VSCode 聚焦 ← 其他应用获得焦点
10:15 Chrome 聚焦 ← 切回来
```

**Bug表现**：显示从10:00开始的时间（15分钟），而不是从10:15开始（正确应该是0秒）

### Bug 2: 格式化函数错误
**问题**：使用了 `formatUptime()` 函数，该函数不显示秒数。

**Bug表现**：显示"5分钟"而不是"5分钟 30秒"

### Bug 3: 时间计算在前端
**问题**：部分格式化逻辑在前端JavaScript中，不统一。

**Bug表现**：PHP返回的格式化字符串被前端覆盖

---

## 修复方案

### 1. 完全在PHP端计算和格式化

**优点**：
- ✅ 统一的计算逻辑
- ✅ 减少前端计算负担
- ✅ 格式化一致性
- ✅ 更容易调试和测试

### 2. 改进的计算算法

#### 步骤1：查询当前应用的历史记录
```php
$appRecords = $db->fetchAll(
    'SELECT timestamp, is_focused 
     FROM process_records 
     WHERE device_id = ? 
       AND executable_name = ?
       AND timestamp <= ?
     ORDER BY timestamp DESC 
     LIMIT 100',
    [$deviceId, $currentAppName, $p['timestamp']]
);
```

#### 步骤2：找到非聚焦记录
```php
foreach ($appRecords as $record) {
    if ($record['is_focused'] == 0) {
        // 找到了！连续聚焦在这之后开始
        $consecutiveFocused = false;
        break;
    }
    $startTime = strtotime($record['timestamp']);
}
```

#### 步骤3：检查其他应用是否获得焦点
```php
if ($consecutiveFocused) {
    // 查询是否有其他应用获得过焦点
    $otherFocusedApp = $db->fetchOne(
        'SELECT timestamp 
         FROM process_records 
         WHERE device_id = ? 
           AND executable_name != ?
           AND is_focused = 1
           AND timestamp > ?
           AND timestamp < ?
         ORDER BY timestamp DESC 
         LIMIT 1',
        [$deviceId, $currentAppName, ...]
    );
}
```

#### 步骤4：找到重新获得焦点的时间
```php
if ($otherFocusedApp) {
    $regainFocus = $db->fetchOne(
        'SELECT timestamp 
         FROM process_records 
         WHERE device_id = ? 
           AND executable_name = ?
           AND is_focused = 1
           AND timestamp > ?
           AND timestamp <= ?
         ORDER BY timestamp ASC 
         LIMIT 1',
        [...]
    );
}
```

#### 步骤5：PHP端格式化
```php
$hours = floor($focusedDuration / 3600);
$minutes = floor(($focusedDuration % 3600) / 60);
$seconds = $focusedDuration % 60;

$parts = [];
if ($hours > 0) $parts[] = $hours . '小时';
if ($minutes > 0) $parts[] = $minutes . '分钟';
$parts[] = $seconds . '秒';

$focusedDurationFormatted = implode(' ', $parts);
```

---

## 修复后的行为

### 场景1：连续使用（无切换）
```
数据：
10:00 Chrome is_focused=1
10:05 Chrome is_focused=1
10:10 Chrome is_focused=1 (当前)

计算：
1. 查询Chrome的历史 → 都是is_focused=1
2. 查询其他应用 → 没有其他应用获得焦点
3. 起始时间：10:00
4. 连续停留：10分钟 0秒 ✓
```

### 场景2：中途切换其他应用
```
数据：
10:00 Chrome is_focused=1
10:05 Chrome is_focused=1
10:10 VSCode is_focused=1 ← 其他应用获得焦点
10:15 Chrome is_focused=1 ← 切回来
10:20 Chrome is_focused=1 (当前)

计算：
1. 查询Chrome的历史 → 都是is_focused=1
2. 查询其他应用 → 10:10有VSCode获得焦点
3. 查询Chrome重新获得焦点 → 10:15
4. 起始时间：10:15
5. 连续停留：5分钟 0秒 ✓
```

### 场景3：应用失去焦点后恢复
```
数据：
10:00 Chrome is_focused=1
10:05 Chrome is_focused=1
10:10 Chrome is_focused=0 ← 失去焦点
10:15 Chrome is_focused=1 ← 恢复焦点
10:20 Chrome is_focused=1 (当前)

计算：
1. 查询Chrome的历史 → 10:10是is_focused=0
2. 在10:10处停止回溯
3. 起始时间：10:15（下一条is_focused=1的记录）
4. 连续停留：5分钟 0秒 ✓
```

---

## API响应格式

### 修复前
```json
{
  "focused_duration": 300,
  "focused_duration_formatted": "5分钟"  // 缺少秒数
}
```

### 修复后
```json
{
  "focused_duration": 325,
  "focused_duration_formatted": "5分钟 25秒"  // 包含秒数
}
```

---

## 性能优化

### 查询限制
- 每个应用最多查询100条历史记录
- 使用索引优化查询速度
- 只在需要时查询其他应用

### 查询次数
```
最少：1次（只查当前应用历史）
最多：3次（当前应用 + 其他应用 + 重新获得焦点）
平均：1-2次
```

### 时间复杂度
- 查询：O(log n) - 数据库索引查询
- 遍历：O(100) - 最多100条记录
- 总体：O(log n + 100) ≈ O(log n)

---

## 测试用例

### 测试1：基本功能
```sql
-- 插入测试数据
INSERT INTO process_records (device_id, timestamp, executable_name, is_focused) VALUES
(1, '2024-01-01 10:00:00', 'chrome.exe', 1),
(1, '2024-01-01 10:05:00', 'chrome.exe', 1),
(1, '2024-01-01 10:10:00', 'chrome.exe', 1);

-- 预期结果：10分钟 0秒
```

### 测试2：切换应用
```sql
INSERT INTO process_records (device_id, timestamp, executable_name, is_focused) VALUES
(1, '2024-01-01 10:00:00', 'chrome.exe', 1),
(1, '2024-01-01 10:05:00', 'chrome.exe', 1),
(1, '2024-01-01 10:10:00', 'vscode.exe', 1),
(1, '2024-01-01 10:15:00', 'chrome.exe', 1),
(1, '2024-01-01 10:20:00', 'chrome.exe', 1);

-- 预期结果：5分钟 0秒（从10:15开始）
```

### 测试3：失去焦点
```sql
INSERT INTO process_records (device_id, timestamp, executable_name, is_focused) VALUES
(1, '2024-01-01 10:00:00', 'chrome.exe', 1),
(1, '2024-01-01 10:05:00', 'chrome.exe', 1),
(1, '2024-01-01 10:10:00', 'chrome.exe', 0),
(1, '2024-01-01 10:15:00', 'chrome.exe', 1),
(1, '2024-01-01 10:20:00', 'chrome.exe', 1);

-- 预期结果：5分钟 0秒（从10:15开始）
```

---

## 前后端职责

### PHP后端（stats.php）
✅ 查询数据库
✅ 计算连续停留时间
✅ 格式化时间字符串
✅ 返回JSON数据

### JavaScript前端（device.js）
✅ 调用API
✅ 接收数据
✅ 显示格式化后的字符串
❌ 不再进行时间计算
❌ 不再进行格式化

---

## 调试方法

### 1. 查看API响应
```bash
curl "http://your-domain/client/api/stats.php?action=realtime&device_id=1"
```

### 2. 使用测试页面
```
访问: test_realtime.php
查看: 连续停留秒数 和 格式化显示
```

### 3. 查看数据库
```sql
SELECT 
    timestamp,
    executable_name,
    is_focused,
    CASE WHEN is_focused = 1 THEN '聚焦' ELSE '未聚焦' END as status
FROM process_records
WHERE device_id = 1
ORDER BY timestamp DESC
LIMIT 50;
```

### 4. 浏览器控制台
```javascript
// 打开设备详情页面
// 按F12 → Console
// 查看日志：
"实时数据响应:"
"聚焦的应用:"
```

---

## 已知限制

1. **查询限制100条**：如果连续聚焦超过100次记录，可能不准确
2. **时间精度**：依赖于客户端上报频率（通常10秒）
3. **时区问题**：使用服务器时区计算

---

## 未来改进

- [ ] 添加缓存机制，减少重复计算
- [ ] 支持更长的历史查询（>100条）
- [ ] 添加时间精度配置选项
- [ ] 优化数据库索引

---

## 相关文件

- `client/api/stats.php` - 修复后的计算逻辑
- `client/public/assets/js/device.js` - 前端显示
- `client/public/test_realtime.php` - 测试工具

---

## 总结

通过将所有计算和格式化逻辑移到PHP端，我们修复了以下问题：

1. ✅ 正确检测应用切换
2. ✅ 精确显示秒数
3. ✅ 统一的计算逻辑
4. ✅ 更好的可维护性

现在连续停留时间能够准确反映用户在当前应用上的连续专注时间！

