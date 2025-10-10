<?php
// 配置
$config = array(
    'valid_token' => 'your_secure_token_here', // 更改为你的安全token
    'data_file' => __DIR__ . '/system_data.json',
    'timestamp_file' => __DIR__ . '/last_update.txt'
);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => '仅支持 POST 请求'));
    exit();
}

// 验证 token
$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token !== $config['valid_token']) {
    http_response_code(401);
    echo json_encode(array('error' => 'Token 无效'));
    exit();
}

// 获取 POST 数据
$rawData = file_get_contents('php://input');
if (empty($rawData)) {
    http_response_code(400);
    echo json_encode(array('error' => '未收到数据'));
    exit();
}

// 验证 JSON 格式
$jsonData = json_decode($rawData);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array('error' => '无效的 JSON 格式: ' . json_last_error_msg()));
    exit();
}

try {
    // 保存数据
    if (file_put_contents($config['data_file'], $rawData) === false) {
        throw new Exception('无法保存数据文件');
    }

    // 保存时间戳
    if (file_put_contents($config['timestamp_file'], time()) === false) {
        throw new Exception('无法保存时间戳');
    }

    // 返回成功响应
    echo json_encode(array(
        'success' => true,
        'message' => '数据已成功保存',
        'timestamp' => time()
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
    exit();
}