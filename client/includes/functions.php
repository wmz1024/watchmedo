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

