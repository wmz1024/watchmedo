<?php
/**
 * 接收远程推送数据API
 * 接收客户端发送的监控数据并存储到数据库
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('只允许POST请求', 405);
}

// 获取token
$token = Auth::getTokenFromRequest();

if (empty($token)) {
    errorResponse('缺少设备token', 401);
}

// 验证token
$auth = new Auth();
$device = $auth->verifyDeviceToken($token);

if (!$device) {
    errorResponse('无效的设备token', 401);
}

// 获取POST数据
$data = getPostData();

if (empty($data)) {
    errorResponse('缺少数据');
}

// 数据库实例
$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    $deviceId = $device['id'];
    $timestamp = date('Y-m-d H:i:s');
    
    // 更新设备最后在线时间
    $db->execute(
        'UPDATE devices SET last_seen_at = ?, is_online = 1 WHERE id = ?',
        [$timestamp, $deviceId]
    );
    
    // 如果有computer_name，更新设备信息
    if (isset($data['computer_name']) && !empty($data['computer_name'])) {
        $db->execute(
            'UPDATE devices SET computer_name = ? WHERE id = ?',
            [$data['computer_name'], $deviceId]
        );
    }
    
    // 插入设备统计数据
    $cpuUsageAvg = isset($data['cpu_usage']) && is_array($data['cpu_usage']) 
        ? calculateCpuAverage($data['cpu_usage']) 
        : 0;
    
    $memoryTotal = $data['memory_usage']['total'] ?? 0;
    $memoryUsed = $data['memory_usage']['used'] ?? 0;
    $memoryPercent = $data['memory_usage']['percent'] ?? 0;
    
    $db->execute(
        'INSERT INTO device_stats (device_id, timestamp, computer_name, uptime, cpu_usage_avg, memory_total, memory_used, memory_percent) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $deviceId,
            $timestamp,
            $data['computer_name'] ?? null,
            $data['uptime'] ?? 0,
            $cpuUsageAvg,
            $memoryTotal,
            $memoryUsed,
            $memoryPercent
        ]
    );
    
    // 插入进程记录
    if (isset($data['processes']) && is_array($data['processes'])) {
        foreach ($data['processes'] as $process) {
            $db->execute(
                'INSERT INTO process_records (device_id, timestamp, executable_name, window_title, cpu_usage, memory_usage, is_focused) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $deviceId,
                    $timestamp,
                    $process['executable_name'] ?? '',
                    $process['window_title'] ?? '',
                    $process['cpu_usage'] ?? 0,
                    $process['memory'] ?? 0,
                    $process['is_focused'] ?? 0
                ]
            );
        }
    }
    
    // 插入磁盘统计
    if (isset($data['disks']) && is_array($data['disks'])) {
        foreach ($data['disks'] as $disk) {
            $db->execute(
                'INSERT INTO disk_stats (device_id, timestamp, name, mount_point, total_space, available_space) 
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $deviceId,
                    $timestamp,
                    $disk['name'] ?? '',
                    $disk['mount_point'] ?? '',
                    $disk['total_space'] ?? 0,
                    $disk['available_space'] ?? 0
                ]
            );
        }
    }
    
    // 插入网络统计
    if (isset($data['network']) && is_array($data['network'])) {
        foreach ($data['network'] as $network) {
            $db->execute(
                'INSERT INTO network_stats (device_id, timestamp, interface_name, received, transmitted) 
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $deviceId,
                    $timestamp,
                    $network['name'] ?? '',
                    $network['received'] ?? 0,
                    $network['transmitted'] ?? 0
                ]
            );
        }
    }
    
    $db->commit();
    
    successResponse([
        'device_id' => $deviceId,
        'timestamp' => $timestamp
    ], '数据接收成功');
    
} catch (Exception $e) {
    $db->rollBack();
    errorResponse('数据保存失败: ' . $e->getMessage(), 500);
}

