<?php
/**
 * 工具函数
 */

/**
 * 返回JSON响应
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 返回错误响应
 */
function errorResponse($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

/**
 * 返回成功响应
 */
function successResponse($data = [], $message = 'success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * 格式化字节大小
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * 格式化运行时间
 */
function formatUptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = $days . '天';
    if ($hours > 0) $parts[] = $hours . '小时';
    if ($minutes > 0) $parts[] = $minutes . '分钟';
    
    return implode(' ', $parts) ?: '0分钟';
}

/**
 * 格式化时间戳
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime)) return '';
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date($format, $timestamp);
}

/**
 * 计算时间差（人类可读）
 */
function timeAgo($datetime) {
    if (empty($datetime)) return '从未';
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return $diff . '秒前';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    if ($diff < 31536000) return floor($diff / 2592000) . '个月前';
    return floor($diff / 31536000) . '年前';
}

/**
 * 更新设备在线状态
 */
function updateDeviceOnlineStatus($db, $deviceId) {
    $device = $db->fetchOne('SELECT * FROM devices WHERE id = ?', [$deviceId]);
    
    if (!$device) return;
    
    $threshold = $device['online_threshold'] ?: DEVICE_ONLINE_THRESHOLD;
    $lastSeen = strtotime($device['last_seen_at']);
    $isOnline = (time() - $lastSeen) <= $threshold ? 1 : 0;
    
    $db->execute('UPDATE devices SET is_online = ? WHERE id = ?', [$isOnline, $deviceId]);
}

/**
 * 更新所有设备在线状态
 */
function updateAllDevicesOnlineStatus($db) {
    $devices = $db->fetchAll('SELECT id, last_seen_at, online_threshold FROM devices');
    
    foreach ($devices as $device) {
        $threshold = $device['online_threshold'] ?: DEVICE_ONLINE_THRESHOLD;
        $lastSeen = strtotime($device['last_seen_at']);
        $isOnline = (time() - $lastSeen) <= $threshold ? 1 : 0;
        
        $db->execute('UPDATE devices SET is_online = ? WHERE id = ?', [$isOnline, $device['id']]);
    }
}

/**
 * 计算CPU平均使用率
 */
function calculateCpuAverage($cpuUsageArray) {
    if (empty($cpuUsageArray) || !is_array($cpuUsageArray)) {
        return 0;
    }
    
    return array_sum($cpuUsageArray) / count($cpuUsageArray);
}

/**
 * 获取日期范围
 */
function getDateRange($date = null) {
    if (empty($date)) {
        $date = date('Y-m-d');
    }
    
    $start = $date . ' 00:00:00';
    $end = $date . ' 23:59:59';
    
    return [$start, $end];
}

/**
 * 根据视图模式获取日期范围
 * 
 * @param string $date 日期值（格式根据模式不同）
 * @param string $viewMode 视图模式：'day', 'month', 'year'
 * @return array [$startDate, $endDate]
 */
function getDateRangeByViewMode($date, $viewMode) {
    switch ($viewMode) {
        case 'month':
            // 月视图：date格式为 "2024-01"
            $firstDay = $date . '-01';
            $lastDay = date('Y-m-t', strtotime($firstDay)); // 月最后一天
            return [
                $firstDay . ' 00:00:00',
                $lastDay . ' 23:59:59'
            ];
            
        case 'year':
            // 年视图：date格式为 "2024"
            return [
                $date . '-01-01 00:00:00',
                $date . '-12-31 23:59:59'
            ];
            
        case 'day':
        default:
            // 日视图：date格式为 "2024-01-15"
            return getDateRange($date);
    }
}

/**
 * 安全获取POST数据
 */
function getPostData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true);
    }
    
    return $_POST;
}

/**
 * 验证必需字段
 */
function validateRequired($data, $fields) {
    $missing = [];
    
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    return $missing;
}

/**
 * 获取设置值
 */
function getSetting($db, $key, $default = null) {
    $result = $db->fetchOne('SELECT value FROM settings WHERE key = ?', [$key]);
    return $result ? $result['value'] : $default;
}

/**
 * 设置设置值
 */
function setSetting($db, $key, $value) {
    $exists = $db->fetchOne('SELECT key FROM settings WHERE key = ?', [$key]);
    
    if ($exists) {
        return $db->execute('UPDATE settings SET value = ? WHERE key = ?', [$value, $key]);
    } else {
        return $db->execute('INSERT INTO settings (key, value) VALUES (?, ?)', [$key, $value]);
    }
}

/**
 * 聚合进程使用时间
 * 计算每个应用在指定时间范围内的使用时长
 */
function aggregateProcessUsage($db, $deviceId, $startDate, $endDate) {
    $sql = "
        SELECT 
            executable_name,
            window_title,
            SUM(CASE WHEN is_focused = 1 THEN 1 ELSE 0 END) * 60 as total_seconds,
            AVG(cpu_usage) as avg_cpu,
            AVG(memory_usage) as avg_memory,
            COUNT(*) as record_count
        FROM process_records
        WHERE device_id = ? 
            AND timestamp >= ? 
            AND timestamp <= ?
        GROUP BY executable_name, window_title
        ORDER BY total_seconds DESC
    ";
    
    return $db->fetchAll($sql, [$deviceId, $startDate, $endDate]);
}

/**
 * 按小时聚合使用时间
 */
function aggregateHourlyUsage($db, $deviceId, $startDate, $endDate) {
    if (DB_TYPE === 'sqlite') {
        $hourFormat = "strftime('%H', timestamp)";
    } else {
        $hourFormat = "DATE_FORMAT(timestamp, '%H')";
    }
    
    $sql = "
        SELECT 
            $hourFormat as hour,
            SUM(CASE WHEN is_focused = 1 THEN 1 ELSE 0 END) * 60 as total_seconds
        FROM process_records
        WHERE device_id = ? 
            AND timestamp >= ? 
            AND timestamp <= ?
        GROUP BY hour
        ORDER BY hour
    ";
    
    return $db->fetchAll($sql, [$deviceId, $startDate, $endDate]);
}

/**
 * 按日聚合使用时间（月视图）
 */
function aggregateDailyUsage($db, $deviceId, $startDate, $endDate) {
    if (DB_TYPE === 'sqlite') {
        $dayFormat = "strftime('%d', timestamp)";
    } else {
        $dayFormat = "DATE_FORMAT(timestamp, '%d')";
    }
    
    $sql = "
        SELECT 
            $dayFormat as day,
            SUM(CASE WHEN is_focused = 1 THEN 1 ELSE 0 END) * 60 as total_seconds
        FROM process_records
        WHERE device_id = ? 
            AND timestamp >= ? 
            AND timestamp <= ?
        GROUP BY day
        ORDER BY day
    ";
    
    return $db->fetchAll($sql, [$deviceId, $startDate, $endDate]);
}

/**
 * 按月聚合使用时间（年视图）
 */
function aggregateMonthlyUsage($db, $deviceId, $startDate, $endDate) {
    if (DB_TYPE === 'sqlite') {
        $monthFormat = "strftime('%m', timestamp)";
    } else {
        $monthFormat = "DATE_FORMAT(timestamp, '%m')";
    }
    
    $sql = "
        SELECT 
            $monthFormat as month,
            SUM(CASE WHEN is_focused = 1 THEN 1 ELSE 0 END) * 60 as total_seconds
        FROM process_records
        WHERE device_id = ? 
            AND timestamp >= ? 
            AND timestamp <= ?
        GROUP BY month
        ORDER BY month
    ";
    
    return $db->fetchAll($sql, [$deviceId, $startDate, $endDate]);
}

/**
 * 自动清理旧数据
 * 在每次数据接收后调用，但会根据时间间隔判断是否真正执行清理
 * 
 * @param Database $db 数据库实例
 * @return array 清理结果 ['executed' => bool, 'deleted' => int, 'message' => string]
 */
function autoCleanOldData($db) {
    // 获取配置
    $retentionDays = (int)getSetting($db, 'data_retention_days', 30); // 默认保留30天
    $cleanupInterval = (int)getSetting($db, 'cleanup_interval_hours', 24); // 默认24小时清理一次
    $autoCleanEnabled = getSetting($db, 'auto_clean_enabled', '1') === '1'; // 默认启用
    
    // 如果禁用自动清理
    if (!$autoCleanEnabled) {
        return [
            'executed' => false,
            'deleted' => 0,
            'message' => '自动清理已禁用'
        ];
    }
    
    // 检查上次清理时间
    $lastCleanup = getSetting($db, 'last_cleanup_time', 0);
    $currentTime = time();
    $timeSinceLastCleanup = $currentTime - $lastCleanup;
    $requiredInterval = $cleanupInterval * 3600; // 转换为秒
    
    // 如果距离上次清理时间不够，跳过
    if ($timeSinceLastCleanup < $requiredInterval) {
        return [
            'executed' => false,
            'deleted' => 0,
            'message' => '距离上次清理时间不足，跳过清理',
            'next_cleanup_in' => $requiredInterval - $timeSinceLastCleanup
        ];
    }
    
    // 执行清理
    try {
        $deletedCount = cleanOldData($db, $retentionDays);
        
        // 更新最后清理时间
        setSetting($db, 'last_cleanup_time', $currentTime);
        
        return [
            'executed' => true,
            'deleted' => $deletedCount,
            'message' => "成功清理 {$retentionDays} 天前的数据，删除了 {$deletedCount} 条记录"
        ];
    } catch (Exception $e) {
        error_log('自动清理数据失败: ' . $e->getMessage());
        return [
            'executed' => false,
            'deleted' => 0,
            'message' => '清理失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 清理指定天数之前的数据
 * 
 * @param Database $db 数据库实例
 * @param int $days 保留天数
 * @return int 删除的总记录数
 */
function cleanOldData($db, $days = 30) {
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    $deletedCount = 0;
    
    // 开始事务
    $db->beginTransaction();
    
    try {
        // 清理设备统计数据
        $result = $db->query(
            'DELETE FROM device_stats WHERE timestamp < ?',
            [$cutoffDate]
        );
        $deletedCount += $result->rowCount();
        
        // 清理进程记录
        $result = $db->query(
            'DELETE FROM process_records WHERE timestamp < ?',
            [$cutoffDate]
        );
        $deletedCount += $result->rowCount();
        
        // 清理磁盘统计
        $result = $db->query(
            'DELETE FROM disk_stats WHERE timestamp < ?',
            [$cutoffDate]
        );
        $deletedCount += $result->rowCount();
        
        // 清理网络统计
        $result = $db->query(
            'DELETE FROM network_stats WHERE timestamp < ?',
            [$cutoffDate]
        );
        $deletedCount += $result->rowCount();
        
        // 提交事务
        $db->commit();
        
        // 记录清理日志
        error_log("数据清理完成: 删除了 {$days} 天前的 {$deletedCount} 条记录");
        
        return $deletedCount;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * 获取数据库大小和记录统计
 * 
 * @param Database $db 数据库实例
 * @return array 统计信息
 */
function getDatabaseStats($db) {
    $stats = [
        'device_stats' => 0,
        'process_records' => 0,
        'disk_stats' => 0,
        'network_stats' => 0,
        'total_records' => 0,
        'db_size' => 0,
        'db_size_formatted' => '0 B'
    ];
    
    // 统计各表记录数
    $tables = ['device_stats', 'process_records', 'disk_stats', 'network_stats'];
    
    foreach ($tables as $table) {
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM {$table}");
        $count = $result ? (int)$result['count'] : 0;
        $stats[$table] = $count;
        $stats['total_records'] += $count;
    }
    
    // 获取数据库大小
    if (DB_TYPE === 'sqlite') {
        $dbFile = SQLITE_DB_PATH;
        if (file_exists($dbFile)) {
            $stats['db_size'] = filesize($dbFile);
            $stats['db_size_formatted'] = formatBytes($stats['db_size']);
        }
    } else if (DB_TYPE === 'mysql') {
        $result = $db->fetchOne(
            "SELECT 
                SUM(data_length + index_length) as size 
             FROM information_schema.TABLES 
             WHERE table_schema = ?",
            [MYSQL_DATABASE]
        );
        if ($result && $result['size']) {
            $stats['db_size'] = (int)$result['size'];
            $stats['db_size_formatted'] = formatBytes($stats['db_size']);
        }
    }
    
    return $stats;
}

/**
 * 确保媒体播放表存在（自动迁移）
 * 
 * @param Database $db 数据库实例
 * @return bool 表是否存在或成功创建
 */
function ensureMediaPlaybackTable($db) {
    try {
        // 检查表是否存在
        if (DB_TYPE === 'mysql') {
            $result = $db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM information_schema.TABLES 
                 WHERE table_schema = ? AND table_name = 'media_playback'",
                [MYSQL_DATABASE]
            );
            
            $tableExists = $result && $result['count'] > 0;
        } else {
            // SQLite
            $result = $db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM sqlite_master 
                 WHERE type='table' AND name='media_playback'"
            );
            
            $tableExists = $result && $result['count'] > 0;
        }
        
        // 如果表不存在，创建它
        if (!$tableExists) {
            error_log("媒体播放表不存在，正在自动创建...");
            
            if (DB_TYPE === 'mysql') {
                $sql = "CREATE TABLE IF NOT EXISTS media_playback (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    device_id INT NOT NULL,
                    title VARCHAR(500) NOT NULL,
                    artist VARCHAR(255) DEFAULT NULL,
                    album VARCHAR(255) DEFAULT NULL,
                    duration INT UNSIGNED DEFAULT NULL COMMENT '总时长（秒）',
                    position INT UNSIGNED DEFAULT NULL COMMENT '当前播放位置（秒）',
                    playback_status VARCHAR(20) NOT NULL DEFAULT 'Playing' COMMENT 'Playing, Paused, Stopped',
                    media_type VARCHAR(20) NOT NULL DEFAULT 'Music' COMMENT 'Music, Video',
                    thumbnail MEDIUMTEXT DEFAULT NULL COMMENT 'Base64编码的缩略图',
                    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_device_timestamp (device_id, timestamp),
                    INDEX idx_timestamp (timestamp),
                    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='媒体播放状态记录'";
            } else {
                // SQLite
                $sql = "CREATE TABLE IF NOT EXISTS media_playback (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    device_id INTEGER NOT NULL,
                    title TEXT NOT NULL,
                    artist TEXT DEFAULT NULL,
                    album TEXT DEFAULT NULL,
                    duration INTEGER DEFAULT NULL,
                    position INTEGER DEFAULT NULL,
                    playback_status TEXT NOT NULL DEFAULT 'Playing',
                    media_type TEXT NOT NULL DEFAULT 'Music',
                    thumbnail TEXT DEFAULT NULL,
                    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
                )";
            }
            
            $db->execute($sql);
            
            // 创建索引（SQLite）
            if (DB_TYPE === 'sqlite') {
                $db->execute("CREATE INDEX IF NOT EXISTS idx_media_device_timestamp ON media_playback(device_id, timestamp)");
                $db->execute("CREATE INDEX IF NOT EXISTS idx_media_timestamp ON media_playback(timestamp)");
            }
            
            error_log("媒体播放表创建成功");
            return true;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("创建媒体播放表失败: " . $e->getMessage());
        return false;
    }
}

