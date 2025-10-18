<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - Watch Me Do</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-center text-gray-900 mb-8">Watch Me Do 安装向导</h1>
            
            <?php
            // 检查是否已安装
            $configFile = __DIR__ . '/../includes/config.php';
            $dataDir = __DIR__ . '/../data';
            
            if (!isset($_POST['install'])) {
                // 显示安装表单
                ?>
                <form method="POST" class="space-y-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">环境检测</h2>
                        <div class="space-y-2">
                            <?php
                            $checks = [
                                'PHP版本 >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                                'PDO扩展' => extension_loaded('pdo'),
                                'PDO SQLite驱动' => extension_loaded('pdo_sqlite'),
                                'JSON扩展' => extension_loaded('json'),
                                'cURL扩展' => extension_loaded('curl'),
                                '配置文件可写' => is_writable(dirname($configFile))
                            ];
                            
                            $allPassed = true;
                            foreach ($checks as $check => $passed) {
                                $allPassed = $allPassed && $passed;
                                $icon = $passed ? '✓' : '✗';
                                $color = $passed ? 'text-green-600' : 'text-red-600';
                                echo "<div class='flex items-center'>";
                                echo "<span class='$color font-bold mr-2'>$icon</span>";
                                echo "<span class='text-gray-700'>$check</span>";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">数据库配置</h2>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">数据库类型</label>
                            <select name="db_type" id="db_type" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="toggleDbConfig()">
                                <option value="sqlite">SQLite（推荐，无需额外配置）</option>
                                <option value="mysql">MySQL</option>
                            </select>
                        </div>
                        
                        <div id="mysql_config" class="hidden space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">MySQL主机</label>
                                <input type="text" name="mysql_host" value="localhost" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">MySQL端口</label>
                                <input type="text" name="mysql_port" value="3306" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">数据库名</label>
                                <input type="text" name="mysql_database" value="watchmedo" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">用户名</label>
                                <input type="text" name="mysql_username" value="root" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">密码</label>
                                <input type="password" name="mysql_password" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">管理员配置</h2>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">管理员密码</label>
                            <input type="password" name="admin_password" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <p class="text-xs text-gray-500 mt-1">请设置管理后台的登录密码</p>
                        </div>
                    </div>
                    
                    <?php if ($allPassed): ?>
                    <button type="submit" name="install" class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 font-semibold">
                        开始安装
                    </button>
                    <?php else: ?>
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <p class="text-red-800 text-sm">环境检测未通过，请先满足所有必需条件</p>
                    </div>
                    <?php endif; ?>
                </form>
                
                <script>
                    function toggleDbConfig() {
                        const dbType = document.getElementById('db_type').value;
                        const mysqlConfig = document.getElementById('mysql_config');
                        
                        if (dbType === 'mysql') {
                            mysqlConfig.classList.remove('hidden');
                        } else {
                            mysqlConfig.classList.add('hidden');
                        }
                    }
                </script>
                <?php
            } else {
                // 执行安装
                try {
                    $dbType = $_POST['db_type'];
                    $adminPassword = $_POST['admin_password'];
                    
                    // 生成配置文件
                    $configContent = "<?php\n";
                    $configContent .= "/**\n * 配置文件\n */\n\n";
                    $configContent .= "// 数据库配置\n";
                    $configContent .= "define('DB_TYPE', '$dbType');\n\n";
                    
                    if ($dbType === 'sqlite') {
                        $configContent .= "// SQLite配置\n";
                        $configContent .= "define('SQLITE_DB_PATH', __DIR__ . '/../data/watchmedo.db');\n\n";
                    } else {
                        $mysqlHost = $_POST['mysql_host'];
                        $mysqlPort = $_POST['mysql_port'];
                        $mysqlDatabase = $_POST['mysql_database'];
                        $mysqlUsername = $_POST['mysql_username'];
                        $mysqlPassword = $_POST['mysql_password'];
                        
                        $configContent .= "// SQLite配置\n";
                        $configContent .= "define('SQLITE_DB_PATH', __DIR__ . '/../data/watchmedo.db');\n\n";
                        $configContent .= "// MySQL配置\n";
                        $configContent .= "define('MYSQL_HOST', '$mysqlHost');\n";
                        $configContent .= "define('MYSQL_PORT', '$mysqlPort');\n";
                        $configContent .= "define('MYSQL_DATABASE', '$mysqlDatabase');\n";
                        $configContent .= "define('MYSQL_USERNAME', '$mysqlUsername');\n";
                        $configContent .= "define('MYSQL_PASSWORD', '$mysqlPassword');\n";
                        $configContent .= "define('MYSQL_CHARSET', 'utf8mb4');\n\n";
                    }
                    
                    $configContent .= "// 应用配置\n";
                    $configContent .= "define('APP_TIMEZONE', 'Asia/Shanghai');\n";
                    $configContent .= "define('APP_DEBUG', false);\n\n";
                    
                    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                    $configContent .= "// 管理后台配置\n";
                    $configContent .= "define('ADMIN_PASSWORD_HASH', '$passwordHash');\n\n";
                    
                    $configContent .= "// 在线检测阈值（秒）\n";
                    $configContent .= "define('DEVICE_ONLINE_THRESHOLD', 300);\n\n";
                    
                    $configContent .= "// AI配置\n";
                    $configContent .= "define('AI_ENABLED', false);\n";
                    $configContent .= "define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');\n";
                    $configContent .= "define('AI_MODEL', 'gpt-3.5-turbo');\n";
                    $configContent .= "define('AI_API_KEY', '');\n\n";
                    
                    $configContent .= "// 设置时区\n";
                    $configContent .= "date_default_timezone_set(APP_TIMEZONE);\n\n";
                    
                    $configContent .= "// 错误报告\n";
                    $configContent .= "if (APP_DEBUG) {\n";
                    $configContent .= "    error_reporting(E_ALL);\n";
                    $configContent .= "    ini_set('display_errors', 1);\n";
                    $configContent .= "} else {\n";
                    $configContent .= "    error_reporting(0);\n";
                    $configContent .= "    ini_set('display_errors', 0);\n";
                    $configContent .= "}\n";
                    
                    // 保存配置文件
                    file_put_contents($configFile, $configContent);
                    
                    // 创建数据目录
                    if (!file_exists($dataDir)) {
                        mkdir($dataDir, 0755, true);
                    }
                    
                    // 初始化数据库
                    require_once $configFile;
                    require_once __DIR__ . '/../includes/database.php';
                    
                    $db = Database::getInstance();
                    $db->initializeTables();
                    
                    echo '<div class="bg-green-50 border border-green-200 rounded-md p-6 mb-6">';
                    echo '<h2 class="text-xl font-semibold text-green-800 mb-4">✓ 安装成功！</h2>';
                    echo '<p class="text-green-700 mb-4">Watch Me Do 已成功安装并配置。</p>';
                    echo '<ul class="list-disc list-inside text-green-700 space-y-2 mb-4">';
                    echo '<li>数据库已初始化</li>';
                    echo '<li>配置文件已生成</li>';
                    echo '<li>管理员密码已设置</li>';
                    echo '</ul>';
                    echo '</div>';
                    
                    echo '<div class="space-y-4">';
                    echo '<h3 class="text-lg font-semibold text-gray-900">下一步操作：</h3>';
                    echo '<ol class="list-decimal list-inside text-gray-700 space-y-2">';
                    echo '<li>访问 <a href="../admin/" class="text-blue-600 hover:underline">管理后台</a> 并使用刚才设置的密码登录</li>';
                    echo '<li>在管理后台添加设备并获取Token</li>';
                    echo '<li>在客户端配置远程推送URL和Token</li>';
                    echo '<li>访问 <a href="../public/" class="text-blue-600 hover:underline">前台页面</a> 查看监控数据</li>';
                    echo '</ol>';
                    echo '</div>';
                    
                    echo '<div class="mt-8 flex space-x-4">';
                    echo '<a href="../admin/" class="flex-1 bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 text-center font-semibold">进入管理后台</a>';
                    echo '<a href="../public/" class="flex-1 bg-gray-600 text-white py-3 px-4 rounded-md hover:bg-gray-700 text-center font-semibold">查看前台</a>';
                    echo '</div>';
                    
                    // 删除安装文件提示
                    echo '<div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-md p-4">';
                    echo '<p class="text-yellow-800 text-sm"><strong>重要：</strong>为了安全，建议删除 install 目录。</p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    echo '<div class="bg-red-50 border border-red-200 rounded-md p-6">';
                    echo '<h2 class="text-xl font-semibold text-red-800 mb-4">✗ 安装失败</h2>';
                    echo '<p class="text-red-700">' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '<a href="" class="mt-4 inline-block text-blue-600 hover:underline">返回重试</a>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>

    <!-- 页脚 -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <p class="text-center text-sm text-gray-600">
                Powered by <span class="font-semibold text-gray-900">WatchMeDo</span> with <span class="text-red-500">❤</span>
                <span class="mx-2">·</span>
                <a href="https://github.com/wmz1024/watchmedo" target="_blank" rel="noopener noreferrer" 
                   class="text-blue-600 hover:text-blue-800 hover:underline">
                    GitHub
                </a>
            </p>
        </div>
    </footer>
</body>
</html>

