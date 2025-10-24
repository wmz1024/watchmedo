<?php
/**
 * 媒体播放状态功能 - 数据库迁移脚本
 * 为支持媒体播放状态监控功能创建必要的数据库表
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = Database::getInstance();

try {
    echo "开始迁移媒体播放状态功能的数据库表...\n\n";
    
    // 检查表是否已存在
    $tables = $db->fetchAll("SHOW TABLES LIKE 'media_playback'");
    
    if (count($tables) > 0) {
        echo "表 media_playback 已存在，跳过创建。\n";
    } else {
        echo "创建表: media_playback\n";
        
        $sql = "CREATE TABLE IF NOT EXISTS media_playback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NOT NULL,
            title VARCHAR(500) NOT NULL,
            artist VARCHAR(255) DEFAULT NULL,
            album VARCHAR(255) DEFAULT NULL,
            duration INT UNSIGNED DEFAULT NULL COMMENT '总时长（秒）',
            position INT UNSIGNED DEFAULT NULL COMMENT '当前播放位置（秒）',
            playback_status VARCHAR(20) NOT NULL DEFAULT 'Playing' COMMENT 'Playing, Paused, Stopped',
            media_type VARCHAR(20) NOT NULL DEFAULT 'Music' COMMENT 'Music, Video',
            thumbnail MEDIUMTEXT DEFAULT NULL COMMENT 'Base64编码的缩略图',
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device_timestamp (device_id, timestamp),
            INDEX idx_timestamp (timestamp),
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='媒体播放状态记录'";
        
        $db->execute($sql);
        echo "✓ 表 media_playback 创建成功\n";
    }
    
    echo "\n数据库迁移完成！\n";
    echo "\n注意事项：\n";
    echo "1. 该功能需要客户端升级到最新版本才能使用\n";
    echo "2. 旧版本客户端不会发送媒体数据，不影响现有功能\n";
    echo "3. 媒体数据（包括缩略图）可能占用较多存储空间\n";
    echo "4. 建议定期清理旧的媒体记录\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}

