<?php
/**
 * 公开设置API
 * 提供前端需要的公开配置（如Giscus配置）
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('只允许GET请求', 405);
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'homepage':
        // 获取首页配置（公开访问）
        $config = [
            'title' => getSetting($db, 'homepage_title', '设备监控中心'),
            'description' => getSetting($db, 'homepage_description', '实时监控您的设备运行状态，追踪应用使用情况')
        ];
        
        successResponse($config);
        break;
        
    case 'giscus':
        // 获取Giscus配置（公开访问）
        $giscusEnabled = getSetting($db, 'giscus_enabled', '0') === '1';
        
        $config = [
            'enabled' => $giscusEnabled,
            'repo' => '',
            'repo_id' => '',
            'category' => '',
            'category_id' => '',
            'theme' => 'light'
        ];
        
        if ($giscusEnabled) {
            $config['repo'] = getSetting($db, 'giscus_repo', '');
            $config['repo_id'] = getSetting($db, 'giscus_repo_id', '');
            $config['category'] = getSetting($db, 'giscus_category', '');
            $config['category_id'] = getSetting($db, 'giscus_category_id', '');
            $config['theme'] = getSetting($db, 'giscus_theme', 'light');
        }
        
        successResponse($config);
        break;
        
    default:
        errorResponse('未知操作', 400);
}

