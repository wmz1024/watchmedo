<?php
/**
 * AI分析API
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('只允许POST请求', 405);
}

$db = Database::getInstance();
$data = getPostData();
$action = $data['action'] ?? 'summary';

// 检查AI是否启用
$aiEnabled = getSetting($db, 'ai_enabled', AI_ENABLED);
if (!$aiEnabled) {
    errorResponse('AI功能未启用');
}

// 获取AI配置
$aiApiUrl = getSetting($db, 'ai_api_url', AI_API_URL);
$aiModel = getSetting($db, 'ai_model', AI_MODEL);
$aiApiKey = getSetting($db, 'ai_api_key', AI_API_KEY);

if (empty($aiApiKey)) {
    errorResponse('AI API密钥未配置');
}

/**
 * 调用OpenAI API
 */
function callOpenAI($apiUrl, $apiKey, $model, $messages) {
    $data = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 2000
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('AI API调用失败: HTTP ' . $httpCode);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('AI返回数据格式错误');
    }
    
    return $result['choices'][0]['message']['content'];
}

switch ($action) {
    case 'summary':
        // 生成使用总结
        $deviceId = $data['device_id'] ?? null;
        $date = $data['date'] ?? date('Y-m-d');
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        list($startDate, $endDate) = getDateRange($date);
        
        // 获取设备信息
        $device = $db->fetchOne('SELECT * FROM devices WHERE id = ?', [$deviceId]);
        
        if (!$device) {
            errorResponse('设备不存在', 404);
        }
        
        // 获取统计数据
        $stats = $db->fetchOne(
            'SELECT * FROM device_stats WHERE device_id = ? AND timestamp >= ? AND timestamp <= ? ORDER BY timestamp DESC LIMIT 1',
            [$deviceId, $startDate, $endDate]
        );
        
        // 获取应用使用情况
        $appUsage = aggregateProcessUsage($db, $deviceId, $startDate, $endDate);
        $topApps = array_slice($appUsage, 0, 10);
        
        // 获取每小时使用情况
        $hourlyUsage = aggregateHourlyUsage($db, $deviceId, $startDate, $endDate);
        
        // 构建Prompt
        $appList = '';
        foreach ($topApps as $i => $app) {
            $appList .= sprintf(
                "%d. %s - 使用时长: %s\n",
                $i + 1,
                $app['executable_name'],
                formatUptime($app['total_seconds'])
            );
        }
        
        $hourlyList = '';
        foreach ($hourlyUsage as $hour) {
            if ($hour['total_seconds'] > 0) {
                $hourlyList .= sprintf(
                    "%02d:00 - %s\n",
                    $hour['hour'],
                    formatUptime($hour['total_seconds'])
                );
            }
        }
        
        $prompt = "请分析以下计算机使用数据，生成一份简洁的使用总结报告（中文）：\n\n";
        $prompt .= "设备名称：" . ($device['computer_name'] ?: $device['name']) . "\n";
        $prompt .= "日期：" . $date . "\n\n";
        
        if ($stats) {
            $prompt .= "系统状态：\n";
            $prompt .= "- 运行时长：" . formatUptime($stats['uptime']) . "\n";
            $prompt .= "- 平均CPU使用率：" . round($stats['cpu_usage_avg'], 2) . "%\n";
            $prompt .= "- 内存使用率：" . round($stats['memory_percent'], 2) . "%\n\n";
        }
        
        if (!empty($appList)) {
            $prompt .= "最常用应用（Top 10）：\n" . $appList . "\n";
        }
        
        if (!empty($hourlyList)) {
            $prompt .= "每小时使用情况：\n" . $hourlyList . "\n";
        }
        
        $prompt .= "\n请从以下角度分析：\n";
        $prompt .= "1. 主要使用应用类型和工作习惯\n";
        $prompt .= "2. 活跃时间段分析\n";
        $prompt .= "3. 系统资源使用情况\n";
        $prompt .= "4. 可能的改进建议\n";
        
        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => '你是一个专业的计算机使用行为分析师，擅长分析用户的计算机使用习惯并提供有价值的见解。'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];
            
            $summary = callOpenAI($aiApiUrl, $aiApiKey, $aiModel, $messages);
            
            successResponse([
                'summary' => $summary,
                'date' => $date,
                'device_name' => $device['computer_name'] ?: $device['name']
            ]);
            
        } catch (Exception $e) {
            errorResponse('AI分析失败: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'question':
        // 回答用户问题
        $deviceId = $data['device_id'] ?? null;
        $question = $data['question'] ?? '';
        $date = $data['date'] ?? date('Y-m-d');
        
        if (empty($deviceId)) {
            errorResponse('缺少设备ID');
        }
        
        if (empty($question)) {
            errorResponse('缺少问题');
        }
        
        list($startDate, $endDate) = getDateRange($date);
        
        // 获取设备信息和统计数据
        $device = $db->fetchOne('SELECT * FROM devices WHERE id = ?', [$deviceId]);
        $stats = $db->fetchOne(
            'SELECT * FROM device_stats WHERE device_id = ? AND timestamp >= ? AND timestamp <= ? ORDER BY timestamp DESC LIMIT 1',
            [$deviceId, $startDate, $endDate]
        );
        $appUsage = aggregateProcessUsage($db, $deviceId, $startDate, $endDate);
        
        // 构建上下文
        $context = "设备: " . ($device['computer_name'] ?: $device['name']) . "\n";
        $context .= "日期: " . $date . "\n\n";
        
        if ($stats) {
            $context .= "系统状态:\n";
            $context .= "- 运行时长: " . formatUptime($stats['uptime']) . "\n";
            $context .= "- CPU使用率: " . round($stats['cpu_usage_avg'], 2) . "%\n";
            $context .= "- 内存使用率: " . round($stats['memory_percent'], 2) . "%\n\n";
        }
        
        $context .= "应用使用情况:\n";
        foreach (array_slice($appUsage, 0, 10) as $app) {
            $context .= sprintf(
                "- %s: %s\n",
                $app['executable_name'],
                formatUptime($app['total_seconds'])
            );
        }
        
        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => '你是一个计算机使用数据分析助手，基于提供的数据回答用户的问题。请用中文简洁准确地回答。'
                ],
                [
                    'role' => 'user',
                    'content' => "数据：\n" . $context . "\n\n问题：" . $question
                ]
            ];
            
            $answer = callOpenAI($aiApiUrl, $aiApiKey, $aiModel, $messages);
            
            successResponse([
                'question' => $question,
                'answer' => $answer,
                'date' => $date
            ]);
            
        } catch (Exception $e) {
            errorResponse('AI问答失败: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        errorResponse('未知操作', 400);
}

