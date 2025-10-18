<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设备监控 - Watch Me Do</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- ECharts -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
        }
        
        .device-card {
            transition: all 0.3s ease;
        }
        
        .device-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }
        
        .online-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 导航栏 -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Watch Me Do</h1>
                    <span class="ml-4 text-sm text-gray-500">设备监控系统</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="../admin/" class="text-sm text-gray-600 hover:text-gray-900">管理后台</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- 主内容 -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- 加载状态 -->
        <div id="loading" class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <p class="mt-4 text-gray-600">加载中...</p>
        </div>

        <!-- 设备列表 -->
        <div id="devices-container" class="hidden">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-900">在线设备</h2>
                <p class="text-sm text-gray-500 mt-1">共 <span id="device-count">0</span> 台设备，<span id="online-count">0</span> 台在线</p>
            </div>

            <div id="devices-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- 设备卡片将动态生成 -->
            </div>
        </div>

        <!-- 无设备提示 -->
        <div id="no-devices" class="hidden text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">暂无设备</h3>
            <p class="mt-2 text-sm text-gray-500">请在管理后台添加设备并配置客户端</p>
            <div class="mt-6">
                <a href="../admin/" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                    前往管理后台
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>

