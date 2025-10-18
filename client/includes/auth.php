<?php
/**
 * Token验证类
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 验证设备token
     */
    public function verifyDeviceToken($token) {
        if (empty($token)) {
            return null;
        }
        
        $device = $this->db->fetchOne(
            'SELECT * FROM devices WHERE token = ?',
            [$token]
        );
        
        return $device;
    }
    
    /**
     * 从请求中获取token
     */
    public static function getTokenFromRequest() {
        // 从Header获取
        $headers = getallheaders();
        if (isset($headers['X-Device-Token'])) {
            return $headers['X-Device-Token'];
        }
        if (isset($headers['Authorization'])) {
            return str_replace('Bearer ', '', $headers['Authorization']);
        }
        
        // 从POST获取
        if (isset($_POST['token'])) {
            return $_POST['token'];
        }
        
        // 从GET获取
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }
        
        return null;
    }
    
    /**
     * 验证管理员密码
     */
    public static function verifyAdminPassword($password) {
        return password_verify($password, ADMIN_PASSWORD_HASH);
    }
    
    /**
     * 生成随机token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 检查管理员会话
     */
    public static function checkAdminSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    /**
     * 设置管理员会话
     */
    public static function setAdminSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_time'] = time();
    }
    
    /**
     * 销毁管理员会话
     */
    public static function destroyAdminSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_unset();
        session_destroy();
    }
}

