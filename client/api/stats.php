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
        $viewMode = $_GET['view_mode'] ?? 'day'; // 'day', 'month', 'year'
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        // 根据视图模式获取日期范围
        list($startDate, $endDate) = getDateRangeByViewMode($date, $viewMode);
        
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
        
        // 根据视图模式获取时间维度统计（柱状图数据）
        if ($viewMode === 'year') {
            // 年视图：按月统计
            $timeUsage = aggregateMonthlyUsage($db, $deviceId, $startDate, $endDate);
            $timeData = array_fill(0, 12, 0);
            $timeLabels = [];
            for ($i = 1; $i <= 12; $i++) {
                $timeLabels[] = $i . '月';
            }
            foreach ($timeUsage as $item) {
                $month = (int)$item['month'];
                if ($month >= 1 && $month <= 12) {
                    $timeData[$month - 1] = (int)$item['total_seconds'];
                }
            }
        } else if ($viewMode === 'month') {
            // 月视图：按日统计
            $timeUsage = aggregateDailyUsage($db, $deviceId, $startDate, $endDate);
            $daysInMonth = (int)date('t', strtotime($startDate));
            $timeData = array_fill(0, $daysInMonth, 0);
            $timeLabels = [];
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $timeLabels[] = $i . '日';
            }
            foreach ($timeUsage as $item) {
                $day = (int)$item['day'];
                if ($day >= 1 && $day <= $daysInMonth) {
                    $timeData[$day - 1] = (int)$item['total_seconds'];
                }
            }
        } else {
            // 日视图：按小时统计
            $hourlyUsage = aggregateHourlyUsage($db, $deviceId, $startDate, $endDate);
            $timeData = array_fill(0, 24, 0);
            $timeLabels = [];
            for ($i = 0; $i < 24; $i++) {
                $timeLabels[] = $i . ':00';
            }
            foreach ($hourlyUsage as $hour) {
                $hourIndex = (int)$hour['hour'];
                if ($hourIndex >= 0 && $hourIndex < 24) {
                    $timeData[$hourIndex] = (int)$hour['total_seconds'];
                }
            }
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
        
        // 最常用时间段分析（根据视图模式）
        $periodStats = [];
        foreach ($timeData as $index => $seconds) {
            if ($seconds > 0) {
                $period = $index;
                if ($viewMode === 'year') {
                    $period = $index + 1; // 月份从1开始
                } else if ($viewMode === 'month') {
                    $period = $index + 1; // 日期从1开始
                }
                
                $periodStats[] = [
                    'hour' => $index, // 保持字段名以兼容
                    'period' => $period, // 实际的时间段值（小时/日/月）
                    'seconds' => $seconds,
                    'formatted' => formatUptime($seconds)
                ];
            }
        }
        
        // 按使用时长排序
        usort($periodStats, function($a, $b) {
            return $b['seconds'] - $a['seconds'];
        });
        
        $mostActiveHours = array_slice($periodStats, 0, 5);
        
        // 过滤掉使用时间少于60秒（1分钟）的应用
        $filteredAppUsage = array_filter($appUsage, function($app) {
            return $app['total_seconds'] >= 60;
        });
        
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
            'view_mode' => $viewMode,
            'pie_chart' => $pieData,
            'time_chart' => [
                'data' => $timeData,
                'labels' => $timeLabels
            ],
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
            }, $filteredAppUsage),
            'most_active_hours' => $mostActiveHours,
            'total_usage' => [
                'seconds' => $totalUsageSeconds,
                'formatted' => formatUptime($totalUsageSeconds)
            ],
            'total_apps_count' => count($filteredAppUsage)
        ];
        
        successResponse($result);
        break;
        
    case 'current_media':
        // 获取设备当前播放的媒体信息
        $deviceId = $_GET['device_id'] ?? null;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        // 确保媒体表存在
        ensureMediaPlaybackTable($db);
        
        // 获取最新的媒体播放记录（1分钟内）
        try {
            $latestMedia = $db->fetchOne(
                'SELECT * FROM media_playback 
                 WHERE device_id = ? 
                   AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                 ORDER BY timestamp DESC 
                 LIMIT 1',
                [$deviceId]
            );
            
            if ($latestMedia) {
                // 格式化数据
                $mediaInfo = [
                    'title' => $latestMedia['title'],
                    'artist' => $latestMedia['artist'],
                    'album' => $latestMedia['album'],
                    'duration' => $latestMedia['duration'] ? (int)$latestMedia['duration'] : null,
                    'position' => $latestMedia['position'] ? (int)$latestMedia['position'] : null,
                    'playback_status' => $latestMedia['playback_status'],
                    'media_type' => $latestMedia['media_type'],
                    'thumbnail' => $latestMedia['thumbnail'],
                    'timestamp' => $latestMedia['timestamp'],
                    'time_ago' => timeAgo($latestMedia['timestamp'])
                ];
                
                successResponse($mediaInfo);
            } else {
                // 没有最近的媒体播放记录
                successResponse(null);
            }
        } catch (Exception $e) {
            // 如果表不存在或其他错误，返回null
            error_log("获取媒体信息失败: " . $e->getMessage());
            successResponse(null);
        }
        break;
        
    case 'realtime':
        // 获取实时系统资源数据
        $deviceId = $_GET['device_id'] ?? null;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        // 更新设备在线状态
        updateDeviceOnlineStatus($db, $deviceId);
        
        // 获取设备信息
        $device = $db->fetchOne('SELECT * FROM devices WHERE id = ?', [$deviceId]);
        
        // 最新统计
        $latestStats = $db->fetchOne(
            'SELECT * FROM device_stats WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1',
            [$deviceId]
        );
        
        // 获取最新时间戳（从最新的进程记录中获取）
        $latestTimestamp = $db->fetchOne(
            'SELECT timestamp FROM process_records WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1',
            [$deviceId]
        );
        
        $timestamp = $latestTimestamp ? $latestTimestamp['timestamp'] : null;
        
        // 获取该时间戳的所有进程（确保是同一次上报的数据）
        $latestProcesses = [];
        if ($timestamp) {
            $latestProcesses = $db->fetchAll(
                'SELECT * FROM process_records WHERE device_id = ? AND timestamp = ? ORDER BY cpu_usage DESC',
                [$deviceId, $timestamp]
            );
        }
        
        // 最新磁盘（使用同一时间戳）
        $latestDisks = [];
        if ($timestamp) {
            $latestDisks = $db->fetchAll(
                'SELECT * FROM disk_stats WHERE device_id = ? AND timestamp = ?',
                [$deviceId, $timestamp]
            );
        }
        
        // 最新网络（使用同一时间戳）
        $latestNetwork = [];
        if ($timestamp) {
            $latestNetwork = $db->fetchAll(
                'SELECT * FROM network_stats WHERE device_id = ? AND timestamp = ?',
                [$deviceId, $timestamp]
            );
        }
        
        // 格式化进程数据
        $processes = array_map(function($p) use ($db, $deviceId, $timestamp) {
            $focusedDuration = 0;
            $focusedDurationFormatted = '0秒';
            
            // 如果是聚焦的应用，计算连续停留时间（在PHP端计算）
            if ($p['is_focused']) {
                $currentAppName = $p['executable_name'];
                $currentTime = strtotime($p['timestamp']);
                
                // 获取当前应用最近的所有记录（按时间倒序）
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
                
                // 查找连续聚焦的起始时间
                $startTime = $currentTime;
                $consecutiveFocused = true;
                
                foreach ($appRecords as $record) {
                    if ($record['is_focused'] == 0) {
                        // 找到非聚焦记录，说明连续聚焦在这之后开始
                        $consecutiveFocused = false;
                        break;
                    }
                    // 如果一直是聚焦状态，更新起始时间
                    $startTime = strtotime($record['timestamp']);
                }
                
                // 如果一直是聚焦状态，还需要检查是否有其他应用获得过焦点
                if ($consecutiveFocused) {
                    // 查询同时间段内是否有其他应用获得焦点
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
                        [$deviceId, $currentAppName, date('Y-m-d H:i:s', $startTime), $p['timestamp']]
                    );
                    
                    if ($otherFocusedApp) {
                        // 有其他应用获得过焦点，找到当前应用重新获得焦点的时间
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
                            [$deviceId, $currentAppName, $otherFocusedApp['timestamp'], $p['timestamp']]
                        );
                        
                        if ($regainFocus) {
                            $startTime = strtotime($regainFocus['timestamp']);
                        }
                    }
                }
                
                // 计算连续停留时间（秒）
                $focusedDuration = max(0, $currentTime - $startTime);
                
                // 格式化时间（PHP端格式化，包含秒）
                $hours = floor($focusedDuration / 3600);
                $minutes = floor(($focusedDuration % 3600) / 60);
                $seconds = $focusedDuration % 60;
                
                $parts = [];
                if ($hours > 0) $parts[] = $hours . '小时';
                if ($minutes > 0) $parts[] = $minutes . '分钟';
                $parts[] = $seconds . '秒';
                
                $focusedDurationFormatted = implode(' ', $parts);
            }
            
            return [
                'name' => $p['executable_name'],
                'window_title' => $p['window_title'],
                'cpu_usage' => round($p['cpu_usage'], 2),
                'memory_usage' => $p['memory_usage'],
                'memory_formatted' => formatBytes($p['memory_usage']),
                'is_focused' => (bool)$p['is_focused'],
                'focused_duration' => $focusedDuration,
                'focused_duration_formatted' => $focusedDurationFormatted
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
            'device' => [
                'id' => $device['id'],
                'name' => $device['name'],
                'computer_name' => $device['computer_name'],
                'is_online' => (bool)$device['is_online'],
                'last_seen_at' => $device['last_seen_at'],
                'last_seen_ago' => timeAgo($device['last_seen_at'])
            ],
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
        
    case 'app_timeline':
        // 获取应用切换时间轴（最近24小时）
        $deviceId = $_GET['device_id'] ?? null;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        // 获取最近24小时的聚焦应用切换记录
        $startTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $endTime = date('Y-m-d H:i:s');
        
        // 获取所有聚焦记录，按时间正序
        $focusedRecords = $db->fetchAll(
            'SELECT executable_name, window_title, timestamp
             FROM process_records 
             WHERE device_id = ? 
               AND timestamp >= ? 
               AND timestamp <= ?
               AND is_focused = 1
             ORDER BY timestamp ASC',
            [$deviceId, $startTime, $endTime]
        );
        
        // 构建时间轴数据（检测应用切换）
        $timeline = [];
        $lastApp = null;
        $currentSession = null;
        
        foreach ($focusedRecords as $record) {
            $appName = $record['executable_name'];
            $windowTitle = $record['window_title'];
            $timestamp = $record['timestamp'];
            
            // 检测应用是否切换
            if ($lastApp !== $appName) {
                // 如果有上一个会话，结束它
                if ($currentSession !== null) {
                    $currentSession['end_time'] = $timestamp;
                    $currentSession['duration'] = strtotime($timestamp) - strtotime($currentSession['start_time']);
                    $timeline[] = $currentSession;
                }
                
                // 开始新的会话
                $currentSession = [
                    'app_name' => $appName,
                    'window_title' => $windowTitle,
                    'start_time' => $timestamp,
                    'end_time' => null,
                    'duration' => null
                ];
                
                $lastApp = $appName;
            }
        }
        
        // 处理最后一个会话（当前正在使用的应用）
        if ($currentSession !== null) {
            $currentSession['end_time'] = date('Y-m-d H:i:s');
            $currentSession['duration'] = time() - strtotime($currentSession['start_time']);
            $currentSession['is_current'] = true;
            $timeline[] = $currentSession;
        }
        
        // 反转数组，使最新的在前面
        $timeline = array_reverse($timeline);
        
        // 限制返回数量（最近100个切换）
        $timeline = array_slice($timeline, 0, 100);
        
        successResponse([
            'timeline' => $timeline,
            'total' => count($timeline)
        ]);
        break;
        
    default:
        errorResponse('未知操作', 400);
}

