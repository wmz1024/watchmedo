<?php
/**
 * 管理后台API
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        // 管理员登录
        if ($method !== 'POST') {
            errorResponse('只允许POST请求', 405);
        }
        
        $data = getPostData();
        $password = $data['password'] ?? '';
        
        if (Auth::verifyAdminPassword($password)) {
            Auth::setAdminSession();
            successResponse([], '登录成功');
        } else {
            errorResponse('密码错误', 401);
        }
        break;
        
    case 'logout':
        // 管理员登出
        Auth::destroyAdminSession();
        successResponse([], '登出成功');
        break;
        
    case 'check_session':
        // 检查登录状态
        $isLoggedIn = Auth::checkAdminSession();
        successResponse(['logged_in' => $isLoggedIn]);
        break;
        
    case 'get_settings':
        // 获取系统设置
        if (!Auth::checkAdminSession()) {
            errorResponse('未登录', 401);
        }
        
        $settings = [
            'db_type' => DB_TYPE,
            'device_online_threshold' => DEVICE_ONLINE_THRESHOLD,
            'ai_enabled' => getSetting($db, 'ai_enabled', AI_ENABLED),
            'ai_api_url' => getSetting($db, 'ai_api_url', AI_API_URL),
            'ai_model' => getSetting($db, 'ai_model', AI_MODEL),
            'ai_api_key' => getSetting($db, 'ai_api_key', '') ? '已设置' : '未设置'
        ];
        
        successResponse($settings);
        break;
        
    case 'save_settings':
        // 保存系统设置
        if ($method !== 'POST') {
            errorResponse('只允许POST请求', 405);
        }
        
        if (!Auth::checkAdminSession()) {
            errorResponse('未登录', 401);
        }
        
        $data = getPostData();
        
        // 保存AI设置
        if (isset($data['ai_enabled'])) {
            setSetting($db, 'ai_enabled', $data['ai_enabled'] ? '1' : '0');
        }
        
        if (isset($data['ai_api_url'])) {
            setSetting($db, 'ai_api_url', $data['ai_api_url']);
        }
        
        if (isset($data['ai_model'])) {
            setSetting($db, 'ai_model', $data['ai_model']);
        }
        
        if (isset($data['ai_api_key']) && !empty($data['ai_api_key'])) {
            setSetting($db, 'ai_api_key', $data['ai_api_key']);
        }
        
        successResponse([], '设置保存成功');
        break;
        
    case 'stats':
        // 获取统计数据
        if (!Auth::checkAdminSession()) {
            errorResponse('未登录', 401);
        }
        
        $deviceCount = $db->fetchOne('SELECT COUNT(*) as count FROM devices')['count'];
        $onlineCount = $db->fetchOne('SELECT COUNT(*) as count FROM devices WHERE is_online = 1')['count'];
        $totalRecords = $db->fetchOne('SELECT COUNT(*) as count FROM process_records')['count'];
        
        // 数据库大小（SQLite）
        $dbSize = 0;
        if (DB_TYPE === 'sqlite' && file_exists(SQLITE_DB_PATH)) {
            $dbSize = filesize(SQLITE_DB_PATH);
        }
        
        successResponse([
            'device_count' => $deviceCount,
            'online_count' => $onlineCount,
            'total_records' => $totalRecords,
            'db_size' => $dbSize,
            'db_size_formatted' => formatBytes($dbSize)
        ]);
        break;
        
    case 'clean_data':
        // 清理旧数据
        if ($method !== 'POST') {
            errorResponse('只允许POST请求', 405);
        }
        
        if (!Auth::checkAdminSession()) {
            errorResponse('未登录', 401);
        }
        
        $data = getPostData();
        $days = $data['days'] ?? 30;
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $db->execute('DELETE FROM device_stats WHERE timestamp < ?', [$cutoffDate]);
        $db->execute('DELETE FROM process_records WHERE timestamp < ?', [$cutoffDate]);
        $db->execute('DELETE FROM disk_stats WHERE timestamp < ?', [$cutoffDate]);
        $db->execute('DELETE FROM network_stats WHERE timestamp < ?', [$cutoffDate]);
        
        // SQLite优化
        if (DB_TYPE === 'sqlite') {
            $db->execute('VACUUM');
        }
        
        successResponse([], "已清理{$days}天前的数据");
        break;
        
    default:
        errorResponse('未知操作', 400);
}

