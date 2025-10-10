<?php
// 配置
$config = array(
    'data_file' => __DIR__ . '/system_data.json',
    'timestamp_file' => __DIR__ . '/last_update.txt',
    'max_age' => 10 // 数据最大年龄（秒）
);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 检查文件是否存在
if (!file_exists($config['data_file']) || !file_exists($config['timestamp_file'])) {
    http_response_code(404);
    echo json_encode(array('error' => '数据不存在'));
    exit();
}

// 读取时间戳
$timestamp = (int)file_get_contents($config['timestamp_file']);
$currentTime = time();
$age = $currentTime - $timestamp;

// 检查数据年龄
if ($age > $config['max_age']) {
    http_response_code(500);
    echo json_encode(array(
        'error' => '数据已过期',
        'age' => $age,
        'max_age' => $config['max_age']
    ));
    exit();
}

// 读取数据文件
$data = file_get_contents($config['data_file']);
if ($data === false) {
    http_response_code(500);
    echo json_encode(array('error' => '无法读取数据文件'));
    exit();
}

// 验证 JSON 格式
$jsonData = json_decode($data);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(array('error' => '数据文件包含无效的 JSON: ' . json_last_error_msg()));
    exit();
}

// 添加元数据到响应
$response = array(
    'data' => $jsonData,
    'metadata' => array(
        'timestamp' => $timestamp,
        'age' => $age,
        'max_age' => $config['max_age']
    )
);

// 返回数据
echo json_encode($response);