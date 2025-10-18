<?php
/**
 * 数据库迁移脚本 - 添加电池字段
 * 为现有数据库的device_stats表添加电池信息字段
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

echo "<h2>数据库迁移：添加电池字段</h2>";

try {
    if (DB_TYPE === 'sqlite') {
        // SQLite: 检查列是否存在
        $result = $pdo->query("PRAGMA table_info(device_stats)")->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_column($result, 'name');
        
        if (!in_array('battery_percentage', $columns)) {
            echo "<p>添加电池字段到SQLite数据库...</p>";
            
            $pdo->exec("ALTER TABLE device_stats ADD COLUMN battery_percentage REAL");
            $pdo->exec("ALTER TABLE device_stats ADD COLUMN battery_is_charging INTEGER");
            $pdo->exec("ALTER TABLE device_stats ADD COLUMN battery_status TEXT");
            
            echo "<p style='color: green;'>✓ SQLite数据库迁移成功！</p>";
        } else {
            echo "<p style='color: blue;'>✓ 电池字段已存在，无需迁移</p>";
        }
    } else if (DB_TYPE === 'mysql') {
        // MySQL: 检查列是否存在
        $result = $pdo->query("SHOW COLUMNS FROM device_stats LIKE 'battery_percentage'")->fetch();
        
        if (!$result) {
            echo "<p>添加电池字段到MySQL数据库...</p>";
            
            $pdo->exec("ALTER TABLE device_stats ADD COLUMN battery_percentage FLOAT AFTER memory_percent");
            $pdo->exec("ALTER TABLE device_stats ADD COLUMN battery_is_charging TINYINT AFTER battery_percentage");
            $pdo->exec("ALTER TABLE device_stats ADD COLUMN battery_status VARCHAR(50) AFTER battery_is_charging");
            
            echo "<p style='color: green;'>✓ MySQL数据库迁移成功！</p>";
        } else {
            echo "<p style='color: blue;'>✓ 电池字段已存在，无需迁移</p>";
        }
    }
    
    echo "<p><a href='../public/index.php'>返回首页</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 迁移失败: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>请检查数据库配置或手动执行以下SQL：</p>";
    
    if (DB_TYPE === 'sqlite') {
        echo "<pre>
ALTER TABLE device_stats ADD COLUMN battery_percentage REAL;
ALTER TABLE device_stats ADD COLUMN battery_is_charging INTEGER;
ALTER TABLE device_stats ADD COLUMN battery_status TEXT;
</pre>";
    } else {
        echo "<pre>
ALTER TABLE device_stats ADD COLUMN battery_percentage FLOAT AFTER memory_percent;
ALTER TABLE device_stats ADD COLUMN battery_is_charging TINYINT AFTER battery_percentage;
ALTER TABLE device_stats ADD COLUMN battery_status VARCHAR(50) AFTER battery_is_charging;
</pre>";
    }
}

