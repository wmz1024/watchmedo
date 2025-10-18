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
            'homepage_title' => getSetting($db, 'homepage_title', 'Watch Me Do'),
            'homepage_description' => getSetting($db, 'homepage_description', '实时监控您的设备运行状态，追踪应用使用情况'),
            'ai_enabled' => getSetting($db, 'ai_enabled', AI_ENABLED),
            'ai_api_url' => getSetting($db, 'ai_api_url', AI_API_URL),
            'ai_model' => getSetting($db, 'ai_model', AI_MODEL),
            'ai_api_key' => getSetting($db, 'ai_api_key', '') ? '已设置' : '未设置',
            'auto_clean_enabled' => getSetting($db, 'auto_clean_enabled', '1') === '1',
            'data_retention_days' => (int)getSetting($db, 'data_retention_days', 30),
            'cleanup_interval_hours' => (int)getSetting($db, 'cleanup_interval_hours', 24),
            'last_cleanup_time' => getSetting($db, 'last_cleanup_time', 0),
            'giscus_enabled' => getSetting($db, 'giscus_enabled', '0') === '1',
            'giscus_repo' => getSetting($db, 'giscus_repo', ''),
            'giscus_repo_id' => getSetting($db, 'giscus_repo_id', ''),
            'giscus_category' => getSetting($db, 'giscus_category', ''),
            'giscus_category_id' => getSetting($db, 'giscus_category_id', ''),
            'giscus_theme' => getSetting($db, 'giscus_theme', 'light')
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
        
        // 保存首页设置
        if (isset($data['homepage_title'])) {
            setSetting($db, 'homepage_title', $data['homepage_title']);
        }
        
        if (isset($data['homepage_description'])) {
            setSetting($db, 'homepage_description', $data['homepage_description']);
        }
        
        // 保存自动清理设置
        if (isset($data['auto_clean_enabled'])) {
            setSetting($db, 'auto_clean_enabled', $data['auto_clean_enabled'] ? '1' : '0');
        }
        
        if (isset($data['data_retention_days'])) {
            $days = max(1, (int)$data['data_retention_days']); // 至少保留1天
            setSetting($db, 'data_retention_days', $days);
        }
        
        if (isset($data['cleanup_interval_hours'])) {
            $hours = max(1, (int)$data['cleanup_interval_hours']); // 至少1小时
            setSetting($db, 'cleanup_interval_hours', $hours);
        }
        
        // 保存Giscus设置
        if (isset($data['giscus_enabled'])) {
            setSetting($db, 'giscus_enabled', $data['giscus_enabled'] ? '1' : '0');
        }
        
        if (isset($data['giscus_repo'])) {
            setSetting($db, 'giscus_repo', $data['giscus_repo']);
        }
        
        if (isset($data['giscus_repo_id'])) {
            setSetting($db, 'giscus_repo_id', $data['giscus_repo_id']);
        }
        
        if (isset($data['giscus_category'])) {
            setSetting($db, 'giscus_category', $data['giscus_category']);
        }
        
        if (isset($data['giscus_category_id'])) {
            setSetting($db, 'giscus_category_id', $data['giscus_category_id']);
        }
        
        if (isset($data['giscus_theme'])) {
            setSetting($db, 'giscus_theme', $data['giscus_theme']);
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
        // 手动清理旧数据
        if ($method !== 'POST') {
            errorResponse('只允许POST请求', 405);
        }
        
        if (!Auth::checkAdminSession()) {
            errorResponse('未登录', 401);
        }
        
        $data = getPostData();
        $days = max(1, (int)($data['days'] ?? 30)); // 至少保留1天
        
        try {
            $deletedCount = cleanOldData($db, $days);
            
            // 更新最后清理时间
            setSetting($db, 'last_cleanup_time', time());
            
            // SQLite优化
            if (DB_TYPE === 'sqlite') {
                $db->execute('VACUUM');
            }
            
            successResponse([
                'deleted' => $deletedCount
            ], "已清理 {$days} 天前的数据，共删除 {$deletedCount} 条记录");
        } catch (Exception $e) {
            errorResponse('清理失败: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'get_cleanup_status':
        // 获取清理状态
        if (!Auth::checkAdminSession()) {
            errorResponse('未登录', 401);
        }
        
        $retentionDays = (int)getSetting($db, 'data_retention_days', 30);
        $lastCleanup = (int)getSetting($db, 'last_cleanup_time', 0);
        $cleanupInterval = (int)getSetting($db, 'cleanup_interval_hours', 24);
        
        $status = [
            'retention_days' => $retentionDays,
            'last_cleanup_time' => $lastCleanup,
            'last_cleanup_formatted' => $lastCleanup > 0 ? date('Y-m-d H:i:s', $lastCleanup) : '从未',
            'last_cleanup_ago' => $lastCleanup > 0 ? timeAgo(date('Y-m-d H:i:s', $lastCleanup)) : '从未',
            'cleanup_interval_hours' => $cleanupInterval,
            'next_cleanup_in_seconds' => max(0, ($cleanupInterval * 3600) - (time() - $lastCleanup))
        ];
        
        successResponse($status);
        break;
        
    default:
        errorResponse('未知操作', 400);
}

