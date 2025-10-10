<?php
// 设置响应头，允许跨域访问
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');

// API 配置
$config = array(
    'api_url' => 'http://localhost:3000/api/system', // 目标 API 地址
    'timeout' => 5 // 超时时间（秒）
);

// 错误处理函数
function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(array(
        'error' => true,
        'message' => $message
    ));
    exit;
}

// 初始化 CURL
$ch = curl_init();
if (!$ch) {
    sendError('无法初始化 CURL');
}

// 设置 CURL 选项
curl_setopt_array($ch, array(
    CURLOPT_URL => $config['api_url'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $config['timeout'],
    CURLOPT_FAILONERROR => true,
    CURLOPT_SSL_VERIFYPEER => false, // 如果使用 HTTPS，可能需要设置为 true
    CURLOPT_SSL_VERIFYHOST => false  // 如果使用 HTTPS，可能需要设置为 2
));

// 执行请求
$response = curl_exec($ch);

// 检查是否有错误发生
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    sendError('API 请求失败: ' . $error);
}

// 获取 HTTP 状态码
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 检查 HTTP 状态码
if ($httpCode !== 200) {
    sendError('API 返回错误状态码: ' . $httpCode, $httpCode);
}

// 验证 JSON 响应
$data = json_decode($response);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendError('无效的 JSON 响应: ' . json_last_error_msg());
}

// 输出 API 响应
echo $response;