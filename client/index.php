<?php
/**
 * Watch Me Do - 远程监控接收端
 * 入口文件
 */

// 检查是否已安装
$configFile = __DIR__ . '/includes/config.php';
$isInstalled = file_exists($configFile);

if ($isInstalled) {
    // 已安装，重定向到公开页面
    header('Location: public/index.php');
} else {
    // 未安装，重定向到安装向导
    header('Location: install/setup.php');
}
exit;

