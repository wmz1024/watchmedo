<?php
/**
 * 测试数据推送脚本
 * 用于测试数据接收API是否正常工作
 * 
 * 使用方法：
 * 1. 在管理后台创建设备并获取Token
 * 2. 运行：php test_push.php YOUR_TOKEN
 */

if (php_sapi_name() !== 'cli') {
    die('此脚本只能在命令行中运行');
}

if ($argc < 2) {
    echo "使用方法: php test_push.php YOUR_TOKEN\n";
    exit(1);
}

$token = $argv[1];
$apiUrl = 'http://localhost/watchmedo/api/receive.php'; // 根据实际情况修改

// 构造测试数据
$testData = [
    'computer_name' => 'TestDevice-' . gethostname(),
    'uptime' => 3600,
    'cpu_usage' => [25.5, 30.2, 15.8, 40.1, 35.6, 28.3, 42.1, 38.9],
    'memory_usage' => [
        'total' => 17179869184,
        'used' => 8589934592,
        'percent' => 50.0
    ],
    'processes' => [
        [
            'executable_name' => 'chrome.exe',
            'window_title' => 'Google Chrome - Test',
            'cpu_usage' => 15.5,
            'memory' => 524288000,
            'is_focused' => true,
            'pid' => 12345
        ],
        [
            'executable_name' => 'code.exe',
            'window_title' => 'Visual Studio Code',
            'cpu_usage' => 8.2,
            'memory' => 314572800,
            'is_focused' => false,
            'pid' => 23456
        ]
    ],
    'disks' => [
        [
            'name' => 'C:',
            'mount_point' => 'C:\\',
            'total_space' => 256000000000,
            'available_space' => 128000000000
        ]
    ],
    'network' => [
        [
            'name' => 'Ethernet',
            'received' => 1048576000,
            'transmitted' => 524288000
        ]
    ]
];

// 发送请求
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Device-Token: ' . $token
]);

echo "正在发送测试数据到: $apiUrl\n";
echo "使用Token: " . substr($token, 0, 10) . "...\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP状态码: $httpCode\n";

if ($error) {
    echo "错误: $error\n";
    exit(1);
}

echo "响应内容:\n";
echo $response . "\n";

$result = json_decode($response, true);

if ($result && isset($result['success']) && $result['success']) {
    echo "\n✓ 测试成功！数据已成功推送。\n";
    exit(0);
} else {
    echo "\n✗ 测试失败！请检查错误信息。\n";
    exit(1);
}

