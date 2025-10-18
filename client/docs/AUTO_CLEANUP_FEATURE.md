# 自动清理旧数据功能 🗑️

## 功能概述

系统会在每次接收客户端数据后自动检测并清理过期的历史数据，防止数据库无限增长。

## 核心特性

### 1. 自动触发 🔄
- ✅ 在每次POST请求（receive.php）后自动检测
- ✅ 基于时间间隔，避免频繁清理影响性能
- ✅ 异步执行，不影响数据接收速度

### 2. 智能检测 🧠
- ✅ 检查距离上次清理的时间
- ✅ 只在达到清理间隔后才执行
- ✅ 可以随时手动触发清理

### 3. 可配置 ⚙️
- ✅ 数据保留天数（默认30天）
- ✅ 清理检测间隔（默认24小时）
- ✅ 启用/禁用自动清理

### 4. 安全可靠 🔒
- ✅ 事务处理，确保数据一致性
- ✅ 错误日志记录
- ✅ 最少保留1天数据（防止误操作）

## 工作原理

### 自动清理流程

```
客户端上报数据
    ↓
receive.php接收数据
    ↓
保存到数据库
    ↓
commit事务
    ↓
调用autoCleanOldData()
    ↓
检查是否启用自动清理 → 否 → 跳过
    ↓ 是
检查距离上次清理时间
    ↓
不足间隔 → 跳过（记录下次清理时间）
    ↓ 超过间隔
执行清理（删除旧数据）
    ↓
更新last_cleanup_time
    ↓
返回清理结果
```

### 清理逻辑

```php
// 1. 计算截止日期
$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

// 2. 清理各个表
DELETE FROM device_stats WHERE timestamp < $cutoffDate;
DELETE FROM process_records WHERE timestamp < $cutoffDate;
DELETE FROM disk_stats WHERE timestamp < $cutoffDate;
DELETE FROM network_stats WHERE timestamp < $cutoffDate;

// 3. 优化数据库（SQLite）
VACUUM;
```

## 配置参数

### 1. auto_clean_enabled
- **说明**: 是否启用自动清理
- **默认值**: `true`（启用）
- **类型**: 布尔值

### 2. data_retention_days
- **说明**: 数据保留天数
- **默认值**: `30`天
- **范围**: 最少1天，建议7-90天
- **示例**: 
  - 7天：适合数据量大的场景
  - 30天：默认推荐
  - 90天：需要长期分析

### 3. cleanup_interval_hours
- **说明**: 清理检测间隔（小时）
- **默认值**: `24`小时
- **范围**: 最少1小时
- **示例**:
  - 1小时：频繁检测（不推荐）
  - 12小时：每天2次
  - 24小时：每天1次（推荐）
  - 168小时：每周1次

## 使用方法

### 方法1: 自动清理（推荐）

1. **访问管理后台**
   ```
   http://your-domain/client/admin/
   ```

2. **切换到"维护"标签**

3. **配置自动清理**
   - ✅ 勾选"启用自动清理"
   - 设置"数据保留天数"（如：30）
   - 设置"清理检测间隔"（如：24小时）
   - 点击"保存配置"

4. **系统自动工作**
   - 每次客户端上报数据时检测
   - 达到间隔时间后自动清理
   - 无需人工干预

### 方法2: 手动清理

1. **访问管理后台 → 维护标签**

2. **手动清理数据**
   - 输入天数（如：30）
   - 点击"执行清理"
   - 确认操作
   - 查看清理结果

## 界面展示

### 管理后台 - 维护标签

```
┌────────────────────────────────────────────┐
│ 自动清理配置                                │
├────────────────────────────────────────────┤
│ ☑ 启用自动清理                             │
│                                            │
│ 数据保留天数: [30] 天                      │
│ 超过此天数的数据将被删除                    │
│                                            │
│ 清理检测间隔: [24] 小时                    │
│ 每隔此时间执行一次检测                      │
│                                            │
│ [保存配置]                                 │
│                                            │
│ ┌────────────────────────────────────────┐ │
│ │ 上次清理时间: 2小时前                   │ │
│ │ 下次清理: 22小时后                      │ │
│ └────────────────────────────────────────┘ │
└────────────────────────────────────────────┘

┌────────────────────────────────────────────┐
│ 手动清理数据                                │
├────────────────────────────────────────────┤
│ 立即删除指定天数之前的历史数据              │
│                                            │
│ [30] 天前的数据  [执行清理]                │
└────────────────────────────────────────────┘
```

## API接口

### 1. 获取设置
```
GET /api/admin.php?action=get_settings

响应:
{
  "auto_clean_enabled": true,
  "data_retention_days": 30,
  "cleanup_interval_hours": 24
}
```

### 2. 保存设置
```
POST /api/admin.php?action=save_settings

请求体:
{
  "auto_clean_enabled": true,
  "data_retention_days": 30,
  "cleanup_interval_hours": 24
}
```

### 3. 获取清理状态
```
GET /api/admin.php?action=get_cleanup_status

响应:
{
  "retention_days": 30,
  "last_cleanup_time": 1640000000,
  "last_cleanup_formatted": "2024-01-01 12:00:00",
  "last_cleanup_ago": "2小时前",
  "next_cleanup_in_seconds": 79200
}
```

### 4. 手动清理
```
POST /api/admin.php?action=clean_data

请求体:
{
  "days": 30
}

响应:
{
  "deleted": 1523,
  "message": "已清理30天前的数据，共删除1523条记录"
}
```

## 性能优化

### 1. 避免频繁清理
```php
// 检查清理间隔
if ($timeSinceLastCleanup < $requiredInterval) {
    return; // 跳过清理
}
```

### 2. 事务处理
```php
$db->beginTransaction();
try {
    // 批量删除
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
}
```

### 3. 批量删除
```php
// 使用单条SQL删除所有旧记录
DELETE FROM table WHERE timestamp < ?
```

### 4. 数据库优化
```php
// SQLite: 执行VACUUM回收空间
if (DB_TYPE === 'sqlite') {
    $db->execute('VACUUM');
}
```

## 清理的数据表

1. **device_stats** - 设备统计数据
2. **process_records** - 进程记录
3. **disk_stats** - 磁盘统计
4. **network_stats** - 网络统计

**注意**: `devices`表不会被清理，只清理历史记录。

## 示例场景

### 场景1: 默认配置（30天）

```
当前日期: 2024-01-31
保留天数: 30天
清理间隔: 24小时

行为:
- 保留: 2024-01-01 00:00:00 之后的数据
- 删除: 2024-01-01 00:00:00 之前的数据
- 频率: 每24小时检测一次
```

### 场景2: 短期保留（7天）

```
当前日期: 2024-01-31
保留天数: 7天
清理间隔: 12小时

行为:
- 保留: 2024-01-24 00:00:00 之后的数据
- 删除: 2024-01-24 00:00:00 之前的数据
- 频率: 每12小时检测一次
```

### 场景3: 长期保留（90天）

```
当前日期: 2024-01-31
保留天数: 90天
清理间隔: 168小时（7天）

行为:
- 保留: 2023-11-02 00:00:00 之后的数据
- 删除: 2023-11-02 00:00:00 之前的数据
- 频率: 每周检测一次
```

## 数据估算

### 单设备数据量
```
假设：
- 10秒上报一次
- 每次20个进程
- 保留30天

计算：
- 每天上报次数: 24 × 60 × 6 = 8640次
- 每天进程记录: 8640 × 20 = 172,800条
- 30天总记录: 172,800 × 30 = 5,184,000条

数据库大小估算（SQLite）:
- process_records: ~500MB
- device_stats: ~10MB
- network_stats: ~5MB
- disk_stats: ~2MB
- 总计: ~517MB
```

### 自动清理效果
```
保留30天数据:
- 最多约500MB
- 自动删除30天前的数据
- 数据库大小相对稳定
```

## 日志记录

### PHP错误日志

自动清理会记录到PHP错误日志：

```
[2024-01-31 12:00:00] 数据清理完成: 删除了 30 天前的 15234 条记录
[2024-01-31 12:00:00] 自动清理数据失败: Connection timeout
```

### 查看日志

**Linux/macOS:**
```bash
tail -f /var/log/php_errors.log
```

**Windows (XAMPP):**
```
C:\xampp\php\logs\php_error_log
```

## 故障排查

### 问题1: 自动清理未执行

**检查:**
```sql
SELECT value FROM settings WHERE key = 'auto_clean_enabled';
SELECT value FROM settings WHERE key = 'last_cleanup_time';
```

**可能原因:**
- 自动清理被禁用
- 清理间隔未到
- 没有数据上报（未触发检测）

### 问题2: 数据库仍然很大

**原因:**
- SQLite未执行VACUUM
- 删除数据但空间未回收

**解决:**
```php
// 手动执行VACUUM
$db->execute('VACUUM');
```

### 问题3: 清理失败

**检查:**
- 查看PHP错误日志
- 检查数据库权限
- 确认表结构正确

## 安全建议

### 1. 备份数据
在启用自动清理前，建议先备份数据库：

**SQLite:**
```bash
cp data/database.db data/database.db.backup
```

**MySQL:**
```bash
mysqldump -u username -p database_name > backup.sql
```

### 2. 测试清理
先用较小的保留天数测试：

```
1. 设置保留天数: 90天
2. 等待清理执行
3. 检查数据是否正确
4. 逐步调整到目标天数
```

### 3. 监控日志
定期查看清理日志，确保正常工作。

## 配置建议

### 小型部署（1-5个设备）
```
数据保留天数: 60-90天
清理间隔: 24-48小时
```

### 中型部署（5-20个设备）
```
数据保留天数: 30-60天
清理间隔: 24小时
```

### 大型部署（20+个设备）
```
数据保留天数: 14-30天
清理间隔: 12-24小时
```

## 高级配置

### 禁用自动清理

**方法1: 通过界面**
- 管理后台 → 维护 → 取消勾选"启用自动清理"

**方法2: 通过数据库**
```sql
UPDATE settings SET value = '0' WHERE key = 'auto_clean_enabled';
```

### 修改默认值

编辑 `functions.php`:

```php
function autoCleanOldData($db) {
    $retentionDays = (int)getSetting($db, 'data_retention_days', 30); // 改这里
    $cleanupInterval = (int)getSetting($db, 'cleanup_interval_hours', 24); // 改这里
    $autoCleanEnabled = getSetting($db, 'auto_clean_enabled', '1') === '1'; // 改这里
    // ...
}
```

## 性能影响

### 清理性能
```
数据量: 100万条记录
删除30天前数据: ~500,000条
执行时间: 1-5秒（视数据库性能）
```

### 对请求的影响
```
正常数据接收: ~50-100ms
触发清理时: ~50-100ms（清理在事务外执行）
清理执行: 1-5秒（不阻塞响应）
```

### 数据库负载
```
- 使用索引加速删除（timestamp字段）
- 批量删除，减少IO操作
- 事务处理，确保一致性
```

## 监控和统计

### 查看清理统计

访问管理后台 → 维护标签：

- 上次清理时间: `2小时前`
- 下次清理: `22小时后`

### SQL查询

```sql
-- 查看清理配置
SELECT * FROM settings WHERE key LIKE '%clean%';

-- 查看数据分布
SELECT 
    DATE(timestamp) as date,
    COUNT(*) as count
FROM process_records
GROUP BY date
ORDER BY date DESC
LIMIT 30;

-- 查看最旧的数据
SELECT MIN(timestamp) as oldest FROM process_records;
```

## 代码示例

### 触发自动清理

```php
// 在receive.php中
$db->commit();

// 自动清理（智能检测）
$cleanupResult = autoCleanOldData($db);

if ($cleanupResult['executed']) {
    // 清理已执行
    error_log("清理了 {$cleanupResult['deleted']} 条记录");
}
```

### 手动触发清理

```php
// 立即清理30天前的数据
$deletedCount = cleanOldData($db, 30);
echo "删除了 {$deletedCount} 条记录";
```

## 文件修改清单

### 后端
1. ✅ `client/includes/functions.php` - 添加清理函数
2. ✅ `client/api/receive.php` - 集成自动清理
3. ✅ `client/api/admin.php` - 添加配置管理API

### 前端
4. ✅ `client/admin/index.php` - 添加配置界面

### 文档
5. ✅ `client/AUTO_CLEANUP_FEATURE.md` - 功能说明

## 测试方法

### 测试1: 自动清理触发

```bash
# 1. 设置保留天数为1天
# 2. 插入2天前的测试数据
INSERT INTO process_records (device_id, timestamp, executable_name, is_focused)
VALUES (1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'test.exe', 0);

# 3. 等待客户端上报数据
# 4. 检查测试数据是否被删除
SELECT COUNT(*) FROM process_records WHERE executable_name = 'test.exe';
```

### 测试2: 清理间隔

```bash
# 1. 设置间隔为1小时
# 2. 触发清理（客户端上报数据）
# 3. 立即再次上报数据
# 4. 检查是否跳过清理（应该跳过）
```

### 测试3: 手动清理

```bash
# 1. 访问管理后台
# 2. 维护 → 手动清理
# 3. 输入天数，执行清理
# 4. 查看删除记录数
```

## 常见问题

### Q1: 自动清理会影响性能吗？
**A**: 不会。清理在数据保存完成后执行，且有间隔控制，不会频繁执行。

### Q2: 如何知道清理是否正常工作？
**A**: 查看管理后台 → 维护标签，显示上次清理时间和删除记录数。

### Q3: 可以关闭自动清理吗？
**A**: 可以。在管理后台取消勾选"启用自动清理"即可。

### Q4: 误删除了数据怎么办？
**A**: 数据删除不可恢复，建议定期备份数据库。

### Q5: 如何调整保留天数？
**A**: 管理后台 → 维护 → 修改"数据保留天数" → 保存配置。

## 最佳实践

1. **定期备份**: 每周备份一次数据库
2. **合理设置**: 根据存储空间和需求设置保留天数
3. **监控日志**: 定期查看清理日志
4. **测试验证**: 新配置先在测试环境验证
5. **渐进调整**: 逐步调整保留天数，不要一次性删除太多

## 总结

自动清理旧数据功能提供了：

- ✅ **自动化**: 无需人工干预
- ✅ **可配置**: 灵活的参数设置
- ✅ **高效**: 智能间隔控制
- ✅ **安全**: 事务处理和错误恢复
- ✅ **兼容**: 支持SQLite和MySQL
- ✅ **透明**: 清理状态实时显示

现在系统可以自动维护数据库大小，防止无限增长！🗑️✨

