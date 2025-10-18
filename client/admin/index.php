<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - Watch Me Do</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 登录界面 -->
    <div id="login-screen" class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">管理后台登录</h2>
            <form id="login-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">管理密码</label>
                    <input type="password" id="password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="请输入管理密码" required>
                </div>
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    登录
                </button>
                <p class="text-xs text-gray-500 text-center mt-4">默认密码: password</p>
            </form>
        </div>
    </div>

    <!-- 管理界面 -->
    <div id="admin-screen" class="hidden">
        <!-- 导航栏 -->
        <nav class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-bold text-gray-900">管理后台</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="../public/index.php" class="text-sm text-gray-600 hover:text-gray-900">查看前台</a>
                        <button id="logout-btn" class="text-sm text-red-600 hover:text-red-800">登出</button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- 主内容 -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- 统计卡片 -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-sm text-gray-500">设备总数</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2" id="stat-devices">-</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-sm text-gray-500">在线设备</p>
                    <p class="text-3xl font-bold text-green-600 mt-2" id="stat-online">-</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-sm text-gray-500">总记录数</p>
                    <p class="text-3xl font-bold text-purple-600 mt-2" id="stat-records">-</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-sm text-gray-500">数据库大小</p>
                    <p class="text-3xl font-bold text-orange-600 mt-2" id="stat-dbsize">-</p>
                </div>
            </div>

            <!-- 导航标签 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <button class="tab-btn active px-6 py-3 text-sm font-medium" data-tab="devices">设备管理</button>
                        <button class="tab-btn px-6 py-3 text-sm font-medium" data-tab="settings">系统设置</button>
                        <button class="tab-btn px-6 py-3 text-sm font-medium" data-tab="ai">AI配置</button>
                        <button class="tab-btn px-6 py-3 text-sm font-medium" data-tab="maintenance">维护</button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- 设备管理 -->
                    <div id="tab-devices" class="tab-content">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">设备列表</h3>
                            <button id="add-device-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                                添加设备
                            </button>
                        </div>
                        <div id="devices-table" class="overflow-x-auto">
                            <!-- 动态生成 -->
                        </div>
                    </div>

                    <!-- 系统设置 -->
                    <div id="tab-settings" class="tab-content hidden">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">系统设置</h3>
                        
                        <!-- 首页配置 -->
                        <div class="border border-gray-200 rounded-lg p-4 mb-6">
                            <h4 class="font-medium text-gray-900 mb-4">首页配置</h4>
                            <form id="homepage-form" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">首页标题</label>
                                    <input type="text" id="homepage-title" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                           placeholder="设备监控中心">
                                    <p class="text-xs text-gray-500 mt-1">显示在首页顶部的标题</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">首页简介</label>
                                    <textarea id="homepage-description" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                              placeholder="实时监控您的设备运行状态，追踪应用使用情况"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">显示在标题下方的描述文字</p>
                                </div>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    保存首页配置
                                </button>
                            </form>
                        </div>
                        
                        <!-- 基础设置 -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-4">基础设置</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">数据库类型</label>
                                    <input type="text" id="db-type" readonly
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">在线检测阈值（秒）</label>
                                    <input type="number" id="online-threshold" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md" value="300">
                                    <p class="text-xs text-gray-500 mt-1">设备超过此时间未上报将被标记为离线</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 系统配置 -->
                    <div id="tab-ai" class="tab-content hidden">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">系统配置</h3>
                        
                        <!-- AI配置 -->
                        <div class="border border-gray-200 rounded-lg p-4 mb-6">
                            <h4 class="font-medium text-gray-900 mb-4">AI配置</h4>
                        <form id="ai-form" class="space-y-4">
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" id="ai-enabled" class="rounded border-gray-300 text-blue-600">
                                    <span class="ml-2 text-sm font-medium text-gray-700">启用AI功能</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API地址</label>
                                <input type="url" id="ai-api-url" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                       placeholder="https://api.openai.com/v1/chat/completions">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">模型</label>
                                <input type="text" id="ai-model" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                       placeholder="gpt-3.5-turbo">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API密钥</label>
                                <input type="password" id="ai-api-key" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                       placeholder="sk-...">
                            </div>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                保存AI配置
                            </button>
                        </form>
                        </div>
                        
                        <!-- Giscus评论配置 -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-4">Giscus评论配置</h4>
                            <form id="giscus-form" class="space-y-4">
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="giscus-enabled" class="rounded border-gray-300 text-blue-600">
                                        <span class="ml-2 text-sm font-medium text-gray-700">启用Giscus评论</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">GitHub仓库</label>
                                    <input type="text" id="giscus-repo" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                           placeholder="username/repo">
                                    <p class="text-xs text-gray-500 mt-1">格式: username/repository</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Repository ID</label>
                                    <input type="text" id="giscus-repo-id" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                           placeholder="R_...">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                    <input type="text" id="giscus-category" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                           placeholder="Announcements">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Category ID</label>
                                    <input type="text" id="giscus-category-id" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                           placeholder="DIC_...">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">主题</label>
                                    <select id="giscus-theme" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="light">浅色</option>
                                        <option value="dark">深色</option>
                                        <option value="preferred_color_scheme">跟随系统</option>
                                        <option value="transparent_dark">透明深色</option>
                                    </select>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                                    <p class="text-xs text-blue-800">
                                        <strong>获取配置信息：</strong><br>
                                        访问 <a href="https://giscus.app/zh-CN" target="_blank" class="underline">https://giscus.app</a>，
                                        按照步骤配置后获取这些ID值
                                    </p>
                                </div>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    保存Giscus配置
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- 维护 -->
                    <div id="tab-maintenance" class="tab-content hidden">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">数据维护</h3>
                        <div class="space-y-4">
                            <!-- 自动清理配置 -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 mb-2">自动清理配置</h4>
                                <p class="text-sm text-gray-500 mb-4">系统会在每次接收数据后检测并自动清理旧数据</p>
                                
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="auto-clean-enabled" class="rounded border-gray-300 text-blue-600">
                                        <label for="auto-clean-enabled" class="ml-2 text-sm font-medium text-gray-700">启用自动清理</label>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">数据保留天数</label>
                                            <div class="flex items-center space-x-2">
                                                <input type="number" id="retention-days" value="30" min="1"
                                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md">
                                                <span class="text-sm text-gray-700">天</span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">超过此天数的数据将被删除</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">清理检测间隔</label>
                                            <div class="flex items-center space-x-2">
                                                <input type="number" id="cleanup-interval" value="24" min="1"
                                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md">
                                                <span class="text-sm text-gray-700">小时</span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">每隔此时间执行一次检测</p>
                                        </div>
                                    </div>
                                    
                                    <button id="save-cleanup-config-btn" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                        保存配置
                                    </button>
                                </div>
                                
                                <!-- 清理状态 -->
                                <div class="mt-4 p-3 bg-gray-50 rounded-md">
                                    <div class="text-sm space-y-1">
                                        <p class="text-gray-700">上次清理时间: <span id="last-cleanup-time" class="font-medium">-</span></p>
                                        <p class="text-gray-700">下次清理: <span id="next-cleanup-time" class="font-medium">-</span></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 手动清理 -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 mb-2">手动清理数据</h4>
                                <p class="text-sm text-gray-500 mb-4">立即删除指定天数之前的历史数据</p>
                                <div class="flex items-center space-x-4">
                                    <input type="number" id="clean-days" value="30" min="1"
                                           class="w-32 px-3 py-2 border border-gray-300 rounded-md">
                                    <span class="text-sm text-gray-700">天前的数据</span>
                                    <button id="clean-data-btn" 
                                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                        执行清理
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加/编辑设备模态框 -->
    <div id="device-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-semibold text-gray-900 mb-4" id="modal-title">添加设备</h3>
            <form id="device-form" class="space-y-4">
                <input type="hidden" id="device-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">设备名称</label>
                    <input type="text" id="device-name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div id="token-display" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">设备Token</label>
                    <div class="flex items-center space-x-2">
                        <input type="text" id="device-token" readonly
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm">
                        <button type="button" id="copy-token-btn"
                                class="px-3 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm">
                            复制
                        </button>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancel-btn"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        保存
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 管理后台逻辑
        let isLoggedIn = false;

        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            checkSession();
            
            // 登录表单
            document.getElementById('login-form').addEventListener('submit', handleLogin);
            
            // 登出按钮
            document.getElementById('logout-btn').addEventListener('click', handleLogout);
            
            // 标签切换
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchTab(this.dataset.tab);
                });
            });
            
            // 添加设备
            document.getElementById('add-device-btn').addEventListener('click', showAddDeviceModal);
            document.getElementById('device-form').addEventListener('submit', handleDeviceSubmit);
            document.getElementById('cancel-btn').addEventListener('click', hideDeviceModal);
            document.getElementById('copy-token-btn').addEventListener('click', copyToken);
            
            // AI配置
            document.getElementById('ai-form').addEventListener('submit', handleAISubmit);
            
            // 首页配置
            document.getElementById('homepage-form').addEventListener('submit', handleHomepageSubmit);
            
            // Giscus配置
            document.getElementById('giscus-form').addEventListener('submit', handleGiscusSubmit);
            
            // 数据清理
            document.getElementById('clean-data-btn').addEventListener('click', handleCleanData);
            
            // 自动清理配置
            document.getElementById('save-cleanup-config-btn').addEventListener('click', handleSaveCleanupConfig);
        });

        // 检查登录状态
        async function checkSession() {
            try {
                const response = await fetch('../api/admin.php?action=check_session');
                const result = await response.json();
                
                if (result.success && result.data.logged_in) {
                    showAdminScreen();
                } else {
                    showLoginScreen();
                }
            } catch (error) {
                showLoginScreen();
            }
        }

        // 处理登录
        async function handleLogin(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            
            try {
                const response = await fetch('../api/admin.php?action=login', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({password})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAdminScreen();
                } else {
                    alert(result.error || '登录失败');
                }
            } catch (error) {
                alert('登录失败: ' + error.message);
            }
        }

        // 处理登出
        async function handleLogout() {
            try {
                await fetch('../api/admin.php?action=logout');
                showLoginScreen();
            } catch (error) {
                console.error('登出失败:', error);
            }
        }

        // 显示登录界面
        function showLoginScreen() {
            document.getElementById('login-screen').classList.remove('hidden');
            document.getElementById('admin-screen').classList.add('hidden');
            isLoggedIn = false;
        }

        // 显示管理界面
        function showAdminScreen() {
            document.getElementById('login-screen').classList.add('hidden');
            document.getElementById('admin-screen').classList.remove('hidden');
            isLoggedIn = true;
            
            loadStats();
            loadDevices();
            loadSettings();
        }

        // 切换标签
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active', 'border-b-2', 'border-blue-600', 'text-blue-600');
                btn.classList.add('text-gray-500');
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            const activeBtn = document.querySelector(`[data-tab="${tab}"]`);
            activeBtn.classList.add('active', 'border-b-2', 'border-blue-600', 'text-blue-600');
            activeBtn.classList.remove('text-gray-500');
            
            document.getElementById(`tab-${tab}`).classList.remove('hidden');
            
            // 如果切换到维护标签，刷新清理状态
            if (tab === 'maintenance') {
                loadCleanupStatus();
            }
        }

        // 加载统计数据
        async function loadStats() {
            try {
                const response = await fetch('../api/admin.php?action=stats');
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('stat-devices').textContent = result.data.device_count;
                    document.getElementById('stat-online').textContent = result.data.online_count;
                    document.getElementById('stat-records').textContent = result.data.total_records.toLocaleString();
                    document.getElementById('stat-dbsize').textContent = result.data.db_size_formatted;
                }
            } catch (error) {
                console.error('加载统计失败:', error);
            }
        }

        // 加载设备列表
        async function loadDevices() {
            try {
                const response = await fetch('../api/devices.php?action=list');
                const result = await response.json();
                
                if (result.success) {
                    renderDevicesTable(result.data);
                }
            } catch (error) {
                console.error('加载设备失败:', error);
            }
        }

        // 渲染设备表格
        function renderDevicesTable(devices) {
            const table = document.getElementById('devices-table');
            
            if (devices.length === 0) {
                table.innerHTML = '<p class="text-gray-500 text-center py-8">暂无设备</p>';
                return;
            }
            
            table.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">名称</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">电脑名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">最后上报</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        ${devices.map(device => `
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">${device.name}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">${device.computer_name || '-'}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs ${device.is_online ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                                        ${device.is_online ? '在线' : '离线'}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">${device.last_seen_ago}</td>
                                <td class="px-4 py-3 text-sm space-x-2">
                                    <button onclick="viewToken(${device.id}, '${device.token}')" class="text-blue-600 hover:text-blue-800">查看Token</button>
                                    <button onclick="deleteDevice(${device.id})" class="text-red-600 hover:text-red-800">删除</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // 显示添加设备模态框
        function showAddDeviceModal() {
            document.getElementById('modal-title').textContent = '添加设备';
            document.getElementById('device-form').reset();
            document.getElementById('device-id').value = '';
            document.getElementById('token-display').classList.add('hidden');
            document.getElementById('device-modal').classList.remove('hidden');
        }

        // 隐藏设备模态框
        function hideDeviceModal() {
            document.getElementById('device-modal').classList.add('hidden');
        }

        // 处理设备提交
        async function handleDeviceSubmit(e) {
            e.preventDefault();
            
            const name = document.getElementById('device-name').value;
            
            try {
                const response = await fetch('../api/devices.php?action=create', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({name})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 显示token
                    document.getElementById('device-token').value = result.data.token;
                    document.getElementById('token-display').classList.remove('hidden');
                    document.querySelector('#device-form button[type="submit"]').classList.add('hidden');
                    
                    alert('设备创建成功！请复制Token并配置到客户端');
                    
                    loadDevices();
                    loadStats();
                } else {
                    alert(result.error || '创建失败');
                }
            } catch (error) {
                alert('创建失败: ' + error.message);
            }
        }

        // 查看Token
        function viewToken(id, token) {
            alert('设备Token:\n\n' + token + '\n\n请妥善保管，不要泄露给他人');
        }

        // 删除设备
        async function deleteDevice(id) {
            if (!confirm('确定要删除这个设备吗？相关数据也会被删除')) {
                return;
            }
            
            try {
                const response = await fetch('../api/devices.php?action=delete', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('删除成功');
                    loadDevices();
                    loadStats();
                } else {
                    alert(result.error || '删除失败');
                }
            } catch (error) {
                alert('删除失败: ' + error.message);
            }
        }

        // 复制Token
        function copyToken() {
            const token = document.getElementById('device-token');
            token.select();
            document.execCommand('copy');
            alert('Token已复制到剪贴板');
        }

        // 加载设置
        async function loadSettings() {
            try {
                const response = await fetch('../api/admin.php?action=get_settings');
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('db-type').value = result.data.db_type;
                    document.getElementById('ai-enabled').checked = result.data.ai_enabled;
                    document.getElementById('ai-api-url').value = result.data.ai_api_url;
                    document.getElementById('ai-model').value = result.data.ai_model;
                    
                    // 加载自动清理设置
                    document.getElementById('auto-clean-enabled').checked = result.data.auto_clean_enabled;
                    document.getElementById('retention-days').value = result.data.data_retention_days;
                    document.getElementById('cleanup-interval').value = result.data.cleanup_interval_hours;
                    
                    // 加载首页设置
                    document.getElementById('homepage-title').value = result.data.homepage_title || '设备监控中心';
                    document.getElementById('homepage-description').value = result.data.homepage_description || '实时监控您的设备运行状态，追踪应用使用情况';
                    
                    // 加载Giscus设置
                    if (result.data.giscus_enabled !== undefined) {
                        document.getElementById('giscus-enabled').checked = result.data.giscus_enabled;
                        document.getElementById('giscus-repo').value = result.data.giscus_repo || '';
                        document.getElementById('giscus-repo-id').value = result.data.giscus_repo_id || '';
                        document.getElementById('giscus-category').value = result.data.giscus_category || '';
                        document.getElementById('giscus-category-id').value = result.data.giscus_category_id || '';
                        document.getElementById('giscus-theme').value = result.data.giscus_theme || 'light';
                    }
                    
                    // 加载清理状态
                    loadCleanupStatus();
                }
            } catch (error) {
                console.error('加载设置失败:', error);
            }
        }
        
        // 处理首页配置提交
        async function handleHomepageSubmit(e) {
            e.preventDefault();
            
            const data = {
                homepage_title: document.getElementById('homepage-title').value,
                homepage_description: document.getElementById('homepage-description').value
            };
            
            try {
                const response = await fetch('../api/admin.php?action=save_settings', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('首页配置保存成功');
                } else {
                    alert(result.error || '保存失败');
                }
            } catch (error) {
                alert('保存失败: ' + error.message);
            }
        }
        
        // 处理Giscus配置提交
        async function handleGiscusSubmit(e) {
            e.preventDefault();
            
            const data = {
                giscus_enabled: document.getElementById('giscus-enabled').checked,
                giscus_repo: document.getElementById('giscus-repo').value,
                giscus_repo_id: document.getElementById('giscus-repo-id').value,
                giscus_category: document.getElementById('giscus-category').value,
                giscus_category_id: document.getElementById('giscus-category-id').value,
                giscus_theme: document.getElementById('giscus-theme').value
            };
            
            try {
                const response = await fetch('../api/admin.php?action=save_settings', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Giscus配置保存成功');
                } else {
                    alert(result.error || '保存失败');
                }
            } catch (error) {
                alert('保存失败: ' + error.message);
            }
        }
        
        // 加载清理状态
        async function loadCleanupStatus() {
            try {
                const response = await fetch('../api/admin.php?action=get_cleanup_status');
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('last-cleanup-time').textContent = result.data.last_cleanup_ago;
                    
                    const nextCleanupSeconds = result.data.next_cleanup_in_seconds;
                    if (nextCleanupSeconds > 0) {
                        const hours = Math.floor(nextCleanupSeconds / 3600);
                        const minutes = Math.floor((nextCleanupSeconds % 3600) / 60);
                        document.getElementById('next-cleanup-time').textContent = 
                            hours > 0 ? `${hours}小时${minutes}分钟后` : `${minutes}分钟后`;
                    } else {
                        document.getElementById('next-cleanup-time').textContent = '下次数据接收时';
                    }
                }
            } catch (error) {
                console.error('加载清理状态失败:', error);
            }
        }
        
        // 保存自动清理配置
        async function handleSaveCleanupConfig() {
            const data = {
                auto_clean_enabled: document.getElementById('auto-clean-enabled').checked,
                data_retention_days: parseInt(document.getElementById('retention-days').value),
                cleanup_interval_hours: parseInt(document.getElementById('cleanup-interval').value)
            };
            
            try {
                const response = await fetch('../api/admin.php?action=save_settings', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('自动清理配置保存成功');
                    loadCleanupStatus();
                } else {
                    alert(result.error || '保存失败');
                }
            } catch (error) {
                alert('保存失败: ' + error.message);
            }
        }

        // 处理AI配置提交
        async function handleAISubmit(e) {
            e.preventDefault();
            
            const data = {
                ai_enabled: document.getElementById('ai-enabled').checked,
                ai_api_url: document.getElementById('ai-api-url').value,
                ai_model: document.getElementById('ai-model').value,
                ai_api_key: document.getElementById('ai-api-key').value
            };
            
            try {
                const response = await fetch('../api/admin.php?action=save_settings', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('AI配置保存成功');
                    document.getElementById('ai-api-key').value = '';
                } else {
                    alert(result.error || '保存失败');
                }
            } catch (error) {
                alert('保存失败: ' + error.message);
            }
        }

        // 处理数据清理
        async function handleCleanData() {
            const days = document.getElementById('clean-days').value;
            
            if (!confirm(`确定要清理${days}天前的数据吗？此操作不可恢复！`)) {
                return;
            }
            
            const btn = document.getElementById('clean-data-btn');
            btn.disabled = true;
            btn.textContent = '清理中...';
            
            try {
                const response = await fetch('../api/admin.php?action=clean_data', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({days: parseInt(days)})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message + '\n删除记录数: ' + result.data.deleted);
                    loadStats();
                    loadCleanupStatus();
                } else {
                    alert(result.error || '清理失败');
                }
            } catch (error) {
                alert('清理失败: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '执行清理';
            }
        }
    </script>

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
</body>
</html>

