<?php
/**
 * 配置文件
 * 注意：首次安装后，此文件会被install/setup.php自动生成
 */

// 数据库配置
define('DB_TYPE', 'sqlite'); // 'sqlite' 或 'mysql'

// SQLite配置
define('SQLITE_DB_PATH', __DIR__ . '/../data/watchmedo.db');

// MySQL配置
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', '3306');
define('MYSQL_DATABASE', 'watchmedo');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', '');
define('MYSQL_CHARSET', 'utf8mb4');

// 应用配置
define('APP_TIMEZONE', 'Asia/Shanghai');
define('APP_DEBUG', true);

// 管理后台配置
define('ADMIN_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // 默认密码: password

// 在线检测阈值（秒）
define('DEVICE_ONLINE_THRESHOLD', 300); // 5分钟

// AI配置（可在管理后台修改）
define('AI_ENABLED', false);
define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('AI_MODEL', 'gpt-3.5-turbo');
define('AI_API_KEY', '');

// 设置时区
date_default_timezone_set(APP_TIMEZONE);

// 错误报告
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

