<?php
/**
 * 设备管理API
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

// 更新所有设备在线状态
updateAllDevicesOnlineStatus($db);

switch ($action) {
    case 'list':
        // 获取设备列表
        if ($method !== 'GET') {
            errorResponse('只允许GET请求', 405);
        }
        
        $devices = $db->fetchAll('SELECT * FROM devices ORDER BY created_at DESC');
        
        // 为每个设备添加额外信息
        foreach ($devices as &$device) {
            $device['last_seen_ago'] = timeAgo($device['last_seen_at']);
            $device['is_online'] = (bool)$device['is_online'];
        }
        
        successResponse($devices);
        break;
        
    case 'get':
        // 获取单个设备详情
        if ($method !== 'GET') {
            errorResponse('只允许GET请求', 405);
        }
        
        $deviceId = $_GET['id'] ?? null;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        $device = $db->fetchOne('SELECT * FROM devices WHERE id = ?', [$deviceId]);
        
        if (!$device) {
            errorResponse('设备不存在', 404);
        }
        
        $device['last_seen_ago'] = timeAgo($device['last_seen_at']);
        $device['is_online'] = (bool)$device['is_online'];
        
        // 获取最新统计数据
        $latestStats = $db->fetchOne(
            'SELECT * FROM device_stats WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1',
            [$deviceId]
        );
        
        $device['latest_stats'] = $latestStats;
        
        successResponse($device);
        break;
        
    case 'create':
        // 创建新设备（仅管理员）
        if ($method !== 'POST') {
            errorResponse('只允许POST请求', 405);
        }
        
        $data = getPostData();
        $name = $data['name'] ?? '';
        
        if (empty($name)) {
            errorResponse('设备名称不能为空');
        }
        
        // 生成token
        $token = Auth::generateToken();
        
        $db->execute(
            'INSERT INTO devices (name, token) VALUES (?, ?)',
            [$name, $token]
        );
        
        $deviceId = $db->lastInsertId();
        
        successResponse([
            'id' => $deviceId,
            'name' => $name,
            'token' => $token
        ], '设备创建成功');
        break;
        
    case 'update':
        // 更新设备信息（仅管理员）
        if ($method !== 'POST') {
            errorResponse('只允许POST请求', 405);
        }
        
        $data = getPostData();
        $deviceId = $data['id'] ?? null;
        $name = $data['name'] ?? null;
        $onlineThreshold = $data['online_threshold'] ?? null;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        $updates = [];
        $params = [];
        
        if (!empty($name)) {
            $updates[] = 'name = ?';
            $params[] = $name;
        }
        
        if ($onlineThreshold !== null) {
            $updates[] = 'online_threshold = ?';
            $params[] = $onlineThreshold;
        }
        
        if (empty($updates)) {
            errorResponse('没有要更新的字段');
        }
        
        $params[] = $deviceId;
        $sql = 'UPDATE devices SET ' . implode(', ', $updates) . ' WHERE id = ?';
        
        $db->execute($sql, $params);
        
        successResponse([], '设备更新成功');
        break;
        
    case 'delete':
        // 删除设备（仅管理员）
        if ($method !== 'POST') {
            errorResponse('只允许POST请求', 405);
        }
        
        $data = getPostData();
        $deviceId = $data['id'] ?? null;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        $db->execute('DELETE FROM devices WHERE id = ?', [$deviceId]);
        
        successResponse([], '设备删除成功');
        break;
        
    case 'regenerate_token':
        // 重新生成设备token（仅管理员）
        if ($method !== 'POST') {
            errorResponse('只允许POST请求', 405);
        }
        
        $data = getPostData();
        $deviceId = $data['id'] ?? null;
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        $newToken = Auth::generateToken();
        
        $db->execute('UPDATE devices SET token = ? WHERE id = ?', [$newToken, $deviceId]);
        
        successResponse([
            'token' => $newToken
        ], 'Token重新生成成功');
        break;
        
    default:
        errorResponse('未知操作', 400);
}

