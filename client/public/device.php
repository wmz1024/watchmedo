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
        
        .view-mode-btn {
            transition: all 0.2s ease;
        }
        
        .view-mode-btn.active {
            background-color: #3b82f6;
            color: white;
        }
        
        .view-mode-btn:not(.active):hover {
            background-color: #f3f4f6;
        }
        
        /* 手机端侧边栏 */
        @media (max-width: 1023px) {
            #sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                overflow-y: auto;
                max-width: 280px;
            }
            
            #sidebar.show {
                display: block !important;
                transform: translateX(0);
            }
        }
        
        /* 时间轴样式 */
        .timeline-item {
            position: relative;
            padding-left: 2.5rem;
            padding-bottom: 1rem;
            border-left: 2px solid #e5e7eb;
        }
        
        .timeline-item:last-child {
            border-left-color: transparent;
            padding-bottom: 0;
        }
        
        .timeline-item.current {
            border-left-color: #3b82f6;
        }
        
        .timeline-dot {
            position: absolute;
            left: -0.5rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: #9ca3af;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e5e7eb;
        }
        
        .timeline-item.current .timeline-dot {
            background-color: #3b82f6;
            box-shadow: 0 0 0 2px #3b82f6, 0 0 10px rgba(59, 130, 246, 0.5);
            animation: pulse-dot 2s ease-in-out infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% {
                box-shadow: 0 0 0 2px #3b82f6, 0 0 10px rgba(59, 130, 246, 0.5);
            }
            50% {
                box-shadow: 0 0 0 4px #3b82f6, 0 0 20px rgba(59, 130, 246, 0.8);
            }
        }
        
        .timeline-duration {
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 导航栏 -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <button id="mobile-menu-btn" class="lg:hidden mr-4 text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <h1 class="text-xl font-bold text-gray-900" id="device-name">设备详情</h1>
                </div>
                <div class="flex items-center">
                    <button id="refresh-btn" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-1">刷新</button>
                </div>
            </div>
        </div>
    </nav>

    <!-- 主内容 -->
    <div class="flex max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- 左侧边栏 -->
        <aside id="sidebar" class="w-64 mr-6 flex-shrink-0 lg:block hidden">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sticky top-4 space-y-4">
                <!-- 返回按钮 -->
                <a href="index.php" class="flex items-center text-blue-600 hover:text-blue-800 py-2 border-b border-gray-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    返回首页
                </a>
                
                <!-- 时间切换组件 -->
                <div class="border-b border-gray-200 pb-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">时间范围</h3>
                    
                    <!-- 视图模式选择 -->
                    <div class="flex items-center space-x-1 mb-3 bg-gray-100 rounded-md p-1">
                        <button id="view-day" class="view-mode-btn active flex-1 px-2 py-1 text-xs rounded">日</button>
                        <button id="view-month" class="view-mode-btn flex-1 px-2 py-1 text-xs rounded">月</button>
                        <button id="view-year" class="view-mode-btn flex-1 px-2 py-1 text-xs rounded">年</button>
                    </div>
                    
                    <!-- 日期选择器（日视图） -->
                    <input type="date" id="date-picker" class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs">
                    
                    <!-- 月份选择器（月视图） -->
                    <input type="month" id="month-picker" class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs hidden">
                    
                    <!-- 年份选择器（年视图） -->
                    <select id="year-picker" class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs hidden">
                        <!-- 动态生成年份选项 -->
                    </select>
                </div>
                
                <!-- 其他设备列表 -->
                <div>
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold text-gray-900">其他设备</h3>
                        <span id="other-devices-count" class="text-xs text-gray-500">-</span>
                    </div>
                    <div id="other-devices-list" class="space-y-2 max-h-96 overflow-y-auto">
                        <p class="text-xs text-gray-500 text-center py-4">加载中...</p>
                    </div>
                </div>
                
                <!-- Giscus评论区 -->
                <div id="giscus-section" class="border-t border-gray-200 pt-4 hidden">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">评论</h3>
                    <div id="giscus-container"></div>
                </div>
            </div>
        </aside>
        
        <!-- 手机端遮罩层 -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>

        <!-- 主内容区域 -->
        <div class="flex-1">
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm text-gray-500">计算机名称</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900" id="computer-name">-</p>
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

            <!-- 应用时间轴 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <span class="inline-flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            应用时间轴
                        </span>
                    </h3>
                    <span class="text-xs text-gray-500">最近24小时应用切换记录</span>
                </div>
                <div id="app-timeline" class="space-y-3 max-h-96 overflow-y-auto">
                    <p class="text-gray-500 text-center py-8">加载中...</p>
                </div>
            </div>

            <!-- 实时系统监控 - 折线图 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">实时系统监控</h3>
                    <span class="text-xs text-gray-500">最近5分钟变化趋势</span>
                </div>
                <div id="realtime-chart" style="height: 350px;"></div>
            </div>
            
            <!-- 总使用时间卡片 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <p class="text-sm text-gray-500">总使用时间</p>
                <p class="mt-2 text-3xl font-bold text-purple-600" id="total-usage">-</p>
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
                <h3 class="text-lg font-semibold text-gray-900 mb-4" id="active-periods-title">最常用时间段</h3>
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

