<?php
/**
 * 统计数据API
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('只允许GET请求', 405);
}

$db = Database::getInstance();
$action = $_GET['action'] ?? 'overview';

switch ($action) {
    case 'overview':
        // 获取所有设备概览
        updateAllDevicesOnlineStatus($db);
        
        $devices = $db->fetchAll('SELECT * FROM devices ORDER BY is_online DESC, last_seen_at DESC');
        
        $overview = [];
        
        foreach ($devices as $device) {
            $latestStats = $db->fetchOne(
                'SELECT * FROM device_stats WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1',
                [$device['id']]
            );
            
            $overview[] = [
                'id' => $device['id'],
                'name' => $device['name'],
                'computer_name' => $device['computer_name'],
                'is_online' => (bool)$device['is_online'],
                'last_seen_at' => $device['last_seen_at'],
                'last_seen_ago' => timeAgo($device['last_seen_at']),
                'latest_stats' => $latestStats
            ];
        }
        
        successResponse($overview);
        break;
        
    case 'device':
        // 获取单个设备的详细统计
        $deviceId = $_GET['device_id'] ?? null;
        $date = $_GET['date'] ?? date('Y-m-d');
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        list($startDate, $endDate) = getDateRange($date);
        
        // 设备基本信息
        $device = $db->fetchOne('SELECT * FROM devices WHERE id = ?', [$deviceId]);
        
        if (!$device) {
            errorResponse('设备不存在', 404);
        }
        
        // 最新统计数据
        $latestStats = $db->fetchOne(
            'SELECT * FROM device_stats WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1',
            [$deviceId]
        );
        
        // 应用使用统计（饼图数据）
        $appUsage = aggregateProcessUsage($db, $deviceId, $startDate, $endDate);
        
        // 每小时使用统计（柱状图数据）
        $hourlyUsage = aggregateHourlyUsage($db, $deviceId, $startDate, $endDate);
        
        // 填充24小时数据（确保每个小时都有数据）
        $hourlyData = array_fill(0, 24, 0);
        foreach ($hourlyUsage as $hour) {
            $hourlyData[(int)$hour['hour']] = (int)$hour['total_seconds'];
        }
        
        // 最常用软件Top 10
        $topApps = array_slice($appUsage, 0, 10);
        
        // 计算总使用时间
        $totalUsageSeconds = array_sum(array_column($appUsage, 'total_seconds'));
        
        // 为饼图数据添加百分比
        $pieData = [];
        foreach ($topApps as $app) {
            $percentage = $totalUsageSeconds > 0 
                ? ($app['total_seconds'] / $totalUsageSeconds) * 100 
                : 0;
            
            $pieData[] = [
                'name' => $app['executable_name'],
                'value' => $app['total_seconds'],
                'percentage' => round($percentage, 2)
            ];
        }
        
        // 最常用时间段分析
        $hourlyStats = [];
        foreach ($hourlyData as $hour => $seconds) {
            if ($seconds > 0) {
                $hourlyStats[] = [
                    'hour' => $hour,
                    'seconds' => $seconds,
                    'formatted' => formatUptime($seconds)
                ];
            }
        }
        
        // 按使用时长排序
        usort($hourlyStats, function($a, $b) {
            return $b['seconds'] - $a['seconds'];
        });
        
        $mostActiveHours = array_slice($hourlyStats, 0, 5);
        
        $result = [
            'device' => [
                'id' => $device['id'],
                'name' => $device['name'],
                'computer_name' => $device['computer_name'],
                'is_online' => (bool)$device['is_online'],
                'last_seen_at' => $device['last_seen_at'],
                'last_seen_ago' => timeAgo($device['last_seen_at'])
            ],
            'latest_stats' => $latestStats,
            'date' => $date,
            'pie_chart' => $pieData,
            'hourly_chart' => $hourlyData,
            'top_apps' => array_map(function($app) use ($totalUsageSeconds) {
                return [
                    'name' => $app['executable_name'],
                    'window_title' => $app['window_title'],
                    'usage_seconds' => $app['total_seconds'],
                    'usage_formatted' => formatUptime($app['total_seconds']),
                    'percentage' => $totalUsageSeconds > 0 
                        ? round(($app['total_seconds'] / $totalUsageSeconds) * 100, 2) 
                        : 0,
                    'avg_cpu' => round($app['avg_cpu'], 2),
                    'avg_memory' => $app['avg_memory'],
                    'avg_memory_formatted' => formatBytes($app['avg_memory'])
                ];
            }, $appUsage),
            'most_active_hours' => $mostActiveHours,
            'total_usage' => [
                'seconds' => $totalUsageSeconds,
                'formatted' => formatUptime($totalUsageSeconds)
            ]
        ];
        
        successResponse($result);
        break;
        
    case 'realtime':
        // 获取实时系统资源数据
        $deviceId = $_GET['device_id'] ?? null;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        // 最新统计
        $latestStats = $db->fetchOne(
            'SELECT * FROM device_stats WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1',
            [$deviceId]
        );
        
        // 最新进程
        $latestProcesses = $db->fetchAll(
            'SELECT * FROM process_records WHERE device_id = ? ORDER BY timestamp DESC LIMIT 20',
            [$deviceId]
        );
        
        // 最新磁盘
        $latestDisks = $db->fetchAll(
            'SELECT * FROM disk_stats WHERE device_id = ? ORDER BY timestamp DESC LIMIT 10',
            [$deviceId]
        );
        
        // 最新网络
        $latestNetwork = $db->fetchAll(
            'SELECT * FROM network_stats WHERE device_id = ? ORDER BY timestamp DESC LIMIT 10',
            [$deviceId]
        );
        
        // 格式化进程数据
        $processes = array_map(function($p) {
            return [
                'name' => $p['executable_name'],
                'window_title' => $p['window_title'],
                'cpu_usage' => round($p['cpu_usage'], 2),
                'memory_usage' => $p['memory_usage'],
                'memory_formatted' => formatBytes($p['memory_usage']),
                'is_focused' => (bool)$p['is_focused']
            ];
        }, $latestProcesses);
        
        // 格式化磁盘数据
        $disks = array_map(function($d) {
            $used = $d['total_space'] - $d['available_space'];
            $percent = $d['total_space'] > 0 
                ? ($used / $d['total_space']) * 100 
                : 0;
            
            return [
                'name' => $d['name'],
                'mount_point' => $d['mount_point'],
                'total' => $d['total_space'],
                'used' => $used,
                'available' => $d['available_space'],
                'percent' => round($percent, 2),
                'total_formatted' => formatBytes($d['total_space']),
                'used_formatted' => formatBytes($used),
                'available_formatted' => formatBytes($d['available_space'])
            ];
        }, $latestDisks);
        
        // 格式化网络数据
        $network = array_map(function($n) {
            return [
                'name' => $n['interface_name'],
                'received' => $n['received'],
                'transmitted' => $n['transmitted'],
                'received_formatted' => formatBytes($n['received']),
                'transmitted_formatted' => formatBytes($n['transmitted'])
            ];
        }, $latestNetwork);
        
        successResponse([
            'stats' => $latestStats,
            'processes' => $processes,
            'disks' => $disks,
            'network' => $network
        ]);
        break;
        
    case 'history':
        // 获取历史数据（用于图表）
        $deviceId = $_GET['device_id'] ?? null;
        $days = $_GET['days'] ?? 7;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $endDate = date('Y-m-d H:i:s');
        
        $history = $db->fetchAll(
            'SELECT * FROM device_stats WHERE device_id = ? AND timestamp >= ? AND timestamp <= ? ORDER BY timestamp ASC',
            [$deviceId, $startDate, $endDate]
        );
        
        successResponse($history);
        break;
        
    default:
        errorResponse('未知操作', 400);
}

