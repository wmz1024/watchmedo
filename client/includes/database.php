<?php
/**
 * 数据库连接类
 * 支持SQLite和MySQL
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            if (DB_TYPE === 'sqlite') {
                $dbPath = SQLITE_DB_PATH;
                $dbDir = dirname($dbPath);
                
                // 确保数据目录存在
                if (!file_exists($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                
                $this->pdo = new PDO('sqlite:' . $dbPath);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // 启用外键约束
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            } else if (DB_TYPE === 'mysql') {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    MYSQL_HOST,
                    MYSQL_PORT,
                    MYSQL_DATABASE,
                    MYSQL_CHARSET
                );
                
                $this->pdo = new PDO(
                    $dsn,
                    MYSQL_USERNAME,
                    MYSQL_PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } else {
                throw new Exception('不支持的数据库类型: ' . DB_TYPE);
            }
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                throw $e;
            }
            error_log('数据库查询错误: ' . $e->getMessage());
            return false;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    }
    
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt !== false;
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    /**
     * 初始化数据库表
     */
    public function initializeTables() {
        $sql = $this->getTableSchema();
        
        // 分割SQL语句并执行
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $this->execute($statement);
            }
        }
    }
    
    /**
     * 获取数据库表结构
     */
    private function getTableSchema() {
        if (DB_TYPE === 'sqlite') {
            return $this->getSQLiteSchema();
        } else {
            return $this->getMySQLSchema();
        }
    }
    
    private function getSQLiteSchema() {
        return "
        -- 设备表
        CREATE TABLE IF NOT EXISTS devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            token TEXT UNIQUE NOT NULL,
            computer_name TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME,
            is_online INTEGER DEFAULT 0,
            online_threshold INTEGER DEFAULT 300
        );
        
        -- 设备统计表
        CREATE TABLE IF NOT EXISTS device_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id INTEGER NOT NULL,
            timestamp DATETIME NOT NULL,
            computer_name TEXT,
            uptime INTEGER,
            cpu_usage_avg REAL,
            memory_total BIGINT,
            memory_used BIGINT,
            memory_percent REAL,
            battery_percentage REAL,
            battery_is_charging INTEGER,
            battery_status TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        );
        
        -- 进程记录表
        CREATE TABLE IF NOT EXISTS process_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id INTEGER NOT NULL,
            timestamp DATETIME NOT NULL,
            executable_name TEXT NOT NULL,
            window_title TEXT,
            cpu_usage REAL,
            memory_usage BIGINT,
            is_focused INTEGER DEFAULT 0,
            duration_seconds INTEGER DEFAULT 0,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        );
        
        -- 磁盘统计表
        CREATE TABLE IF NOT EXISTS disk_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id INTEGER NOT NULL,
            timestamp DATETIME NOT NULL,
            name TEXT,
            mount_point TEXT,
            total_space BIGINT,
            available_space BIGINT,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        );
        
        -- 网络统计表
        CREATE TABLE IF NOT EXISTS network_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id INTEGER NOT NULL,
            timestamp DATETIME NOT NULL,
            interface_name TEXT,
            received BIGINT,
            transmitted BIGINT,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        );
        
        -- 系统设置表
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );
        
        -- 创建索引
        CREATE INDEX IF NOT EXISTS idx_device_stats_device_timestamp ON device_stats(device_id, timestamp);
        CREATE INDEX IF NOT EXISTS idx_process_records_device_timestamp ON process_records(device_id, timestamp);
        CREATE INDEX IF NOT EXISTS idx_process_records_focused ON process_records(device_id, is_focused, timestamp);
        CREATE INDEX IF NOT EXISTS idx_disk_stats_device_timestamp ON disk_stats(device_id, timestamp);
        CREATE INDEX IF NOT EXISTS idx_network_stats_device_timestamp ON network_stats(device_id, timestamp);
        ";
    }
    
    private function getMySQLSchema() {
        return "
        -- 设备表
        CREATE TABLE IF NOT EXISTS devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            token VARCHAR(255) UNIQUE NOT NULL,
            computer_name VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME,
            is_online TINYINT DEFAULT 0,
            online_threshold INT DEFAULT 300,
            INDEX idx_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- 设备统计表
        CREATE TABLE IF NOT EXISTS device_stats (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NOT NULL,
            timestamp DATETIME NOT NULL,
            computer_name VARCHAR(255),
            uptime BIGINT,
            cpu_usage_avg FLOAT,
            memory_total BIGINT,
            memory_used BIGINT,
            memory_percent FLOAT,
            battery_percentage FLOAT,
            battery_is_charging TINYINT,
            battery_status VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
            INDEX idx_device_timestamp (device_id, timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- 进程记录表
        CREATE TABLE IF NOT EXISTS process_records (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NOT NULL,
            timestamp DATETIME NOT NULL,
            executable_name VARCHAR(255) NOT NULL,
            window_title TEXT,
            cpu_usage FLOAT,
            memory_usage BIGINT,
            is_focused TINYINT DEFAULT 0,
            duration_seconds INT DEFAULT 0,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
            INDEX idx_device_timestamp (device_id, timestamp),
            INDEX idx_focused (device_id, is_focused, timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- 磁盘统计表
        CREATE TABLE IF NOT EXISTS disk_stats (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NOT NULL,
            timestamp DATETIME NOT NULL,
            name VARCHAR(255),
            mount_point VARCHAR(255),
            total_space BIGINT,
            available_space BIGINT,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
            INDEX idx_device_timestamp (device_id, timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- 网络统计表
        CREATE TABLE IF NOT EXISTS network_stats (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NOT NULL,
            timestamp DATETIME NOT NULL,
            interface_name VARCHAR(255),
            received BIGINT,
            transmitted BIGINT,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
            INDEX idx_device_timestamp (device_id, timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- 系统设置表
        CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(255) PRIMARY KEY,
            `value` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
}

