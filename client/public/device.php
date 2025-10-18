<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设备详情 - Watch Me Do</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- ECharts -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
        }
        
        .chart-container {
            height: 400px;
        }
        
        .progress-bar {
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 导航栏 -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-4">← 返回</a>
                    <h1 class="text-xl font-bold text-gray-900" id="device-name">设备详情</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <input type="date" id="date-picker" class="border border-gray-300 rounded-md px-3 py-1 text-sm">
                    <button id="refresh-btn" class="text-sm text-gray-600 hover:text-gray-900">刷新</button>
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

        <!-- 设备信息 -->
        <div id="device-content" class="hidden">
            <!-- 实时信息卡片 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- 当前聚焦应用 -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 rounded-full bg-green-500 animate-pulse mr-2"></span>
                                正在使用的应用
                            </span>
                        </h3>
                        <span id="refresh-countdown" class="text-xs text-gray-500">刷新中...</span>
                    </div>
                    <div id="focused-app" class="text-center py-4">
                        <p class="text-gray-500">加载中...</p>
                    </div>
                </div>

                <!-- 网络流量 -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">网络流量</h3>
                    <div id="network-stats" class="space-y-3">
                        <p class="text-gray-500 text-center py-4">加载中...</p>
                    </div>
                </div>
            </div>

            <!-- 设备概览 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500">计算机名称</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900" id="computer-name">-</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">运行时间</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900" id="uptime">-</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">在线状态</p>
                        <p class="mt-1 text-lg font-semibold" id="online-status">
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" id="status-indicator"></span>
                                <span id="status-text">-</span>
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">最后上报</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900" id="last-seen">-</p>
                    </div>
                </div>
            </div>

            <!-- 系统资源 -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-sm text-gray-500">CPU使用率</p>
                    <p class="mt-2 text-3xl font-bold text-blue-600" id="cpu-usage">-</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-sm text-gray-500">内存使用率</p>
                    <p class="mt-2 text-3xl font-bold text-green-600" id="memory-usage">-</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-sm text-gray-500">总使用时间</p>
                    <p class="mt-2 text-3xl font-bold text-purple-600" id="total-usage">-</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-sm text-gray-500">活跃应用数</p>
                    <p class="mt-2 text-3xl font-bold text-orange-600" id="active-apps">-</p>
                </div>
            </div>

            <!-- 电池信息（仅笔记本显示，兼容旧版本） -->
            <div id="battery-info" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6 hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <svg id="battery-icon" class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                            <!-- 电池图标将通过JavaScript动态设置 -->
                        </svg>
                        <div>
                            <p class="text-sm text-gray-500">电池状态</p>
                            <p class="mt-1 text-xl font-semibold" id="battery-status">-</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-4xl font-bold" id="battery-percentage">-</p>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div id="battery-bar" class="h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <!-- 图表 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- 应用使用时间饼图 -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">应用使用时间分布</h3>
                    <div id="pie-chart" class="chart-container"></div>
                </div>

                <!-- 每小时使用时间柱状图 -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">24小时使用时间</h3>
                    <div id="bar-chart" class="chart-container"></div>
                </div>
            </div>

            <!-- 最常用时间段 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">最常用时间段</h3>
                <div id="active-hours" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <!-- 动态生成 -->
                </div>
            </div>

            <!-- 应用详细列表 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">应用使用详情</h3>
                    <div class="text-sm text-gray-500">
                        <span id="app-count-info">-</span>
                    </div>
                </div>
                <div id="app-list" class="space-y-4 mb-4">
                    <!-- 动态生成 -->
                </div>
                <!-- 分页控件 -->
                <div id="pagination" class="flex items-center justify-between border-t border-gray-200 pt-4">
                    <div class="text-sm text-gray-500" id="pagination-info">
                        显示 <span id="page-start">0</span> - <span id="page-end">0</span> / 共 <span id="total-apps">0</span> 个应用
                    </div>
                    <div class="flex space-x-2" id="pagination-buttons">
                        <!-- 动态生成分页按钮 -->
                    </div>
                </div>
            </div>

            <!-- AI分析 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mt-6" id="ai-section">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">AI 使用分析</h3>
                    <button id="generate-ai-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                        生成分析报告
                    </button>
                </div>
                <div id="ai-content" class="hidden">
                    <div class="prose max-w-none">
                        <div id="ai-result" class="text-gray-700 whitespace-pre-wrap"></div>
                    </div>
                </div>
                <div id="ai-loading" class="hidden text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-600">AI分析中...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 页脚 -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
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

    <script src="assets/js/device.js"></script>
</body>
</html>

