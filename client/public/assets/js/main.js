// 主页面逻辑

let devices = [];

// 页面加载完成后获取设备列表
document.addEventListener('DOMContentLoaded', function() {
    // 加载首页配置
    loadHomePageConfig();
    
    // 加载设备列表
    loadDevices();
    
    // 每30秒自动刷新
    setInterval(loadDevices, 30000);
});

// 加载首页配置
async function loadHomePageConfig() {
    try {
        const response = await fetch('../api/settings.php?action=homepage');
        const result = await response.json();
        
        if (result.success && result.data) {
            const config = result.data;
            
            // 更新标题
            if (config.title) {
                document.getElementById('page-title').textContent = config.title;
                document.title = config.title + ' - Watch Me Do';
            }
            
            // 更新简介
            if (config.description) {
                document.getElementById('page-description').textContent = config.description;
            }
        }
    } catch (error) {
        console.error('加载首页配置失败:', error);
    }
}

// 加载设备列表
async function loadDevices() {
    try {
        const response = await fetch('../api/stats.php?action=overview');
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || '加载失败');
        }
        
        devices = result.data;
        renderDevices();
        
    } catch (error) {
        console.error('加载设备失败:', error);
        showError('加载设备列表失败: ' + error.message);
    }
}

// 渲染设备列表
function renderDevices() {
    const loading = document.getElementById('loading');
    const container = document.getElementById('devices-container');
    const noDevices = document.getElementById('no-devices');
    const grid = document.getElementById('devices-grid');
    const statsOverview = document.getElementById('stats-overview');
    
    loading.classList.add('hidden');
    
    if (devices.length === 0) {
        noDevices.classList.remove('hidden');
        container.classList.add('hidden');
        statsOverview.classList.add('hidden');
        return;
    }
    
    noDevices.classList.add('hidden');
    container.classList.remove('hidden');
    statsOverview.classList.remove('hidden');
    
    // 计算统计数据
    const onlineCount = devices.filter(d => d.is_online).length;
    const totalDevices = devices.length;
    
    // 计算平均CPU和内存（只计算在线设备）
    const onlineDevices = devices.filter(d => d.is_online && d.latest_stats);
    let totalCpu = 0;
    let totalMemory = 0;
    let validCpuCount = 0;
    let validMemoryCount = 0;
    
    onlineDevices.forEach(device => {
        if (device.latest_stats) {
            if (device.latest_stats.cpu_usage_avg !== null && device.latest_stats.cpu_usage_avg !== undefined) {
                totalCpu += parseFloat(device.latest_stats.cpu_usage_avg);
                validCpuCount++;
            }
            if (device.latest_stats.memory_percent !== null && device.latest_stats.memory_percent !== undefined) {
                totalMemory += parseFloat(device.latest_stats.memory_percent);
                validMemoryCount++;
            }
        }
    });
    
    const avgCpu = validCpuCount > 0 ? (totalCpu / validCpuCount).toFixed(1) : '-';
    const avgMemory = validMemoryCount > 0 ? (totalMemory / validMemoryCount).toFixed(1) : '-';
    
    // 更新统计概览
    document.getElementById('total-devices').textContent = totalDevices;
    document.getElementById('online-devices').textContent = onlineCount;
    document.getElementById('avg-cpu').textContent = avgCpu + (avgCpu !== '-' ? '%' : '');
    document.getElementById('avg-memory').textContent = avgMemory + (avgMemory !== '-' ? '%' : '');
    
    // 更新设备列表统计
    document.getElementById('device-count').textContent = totalDevices;
    document.getElementById('online-count').textContent = onlineCount;
    
    // 生成设备卡片
    grid.innerHTML = devices.map(device => createDeviceCard(device)).join('');
}

// 创建设备卡片HTML
function createDeviceCard(device) {
    const isOnline = device.is_online;
    const statusColor = isOnline ? 'bg-green-500' : 'bg-gray-400';
    const statusText = isOnline ? '在线' : '离线';
    
    const stats = device.latest_stats || {};
    const cpuUsage = stats.cpu_usage_avg ? stats.cpu_usage_avg.toFixed(1) : '-';
    const memoryPercent = stats.memory_percent ? stats.memory_percent.toFixed(1) : '-';
    const uptime = stats.uptime ? formatUptime(stats.uptime) : '-';
    
    return `
        <div class="device-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 cursor-pointer" 
             onclick="viewDevice(${device.id})">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">${escapeHtml(device.name)}</h3>
                    <p class="text-sm text-gray-500 mt-1">${escapeHtml(device.computer_name || '未知设备')}</p>
                </div>
                <div class="flex items-center">
                    <span class="online-indicator ${statusColor}"></span>
                    <span class="ml-2 text-sm font-medium ${isOnline ? 'text-green-600' : 'text-gray-500'}">${statusText}</span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <p class="text-xs text-gray-500">CPU使用率</p>
                    <p class="text-lg font-semibold text-blue-600">${cpuUsage}%</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">内存使用率</p>
                    <p class="text-lg font-semibold text-green-600">${memoryPercent}%</p>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">运行时间</span>
                    <span class="text-gray-900 font-medium">${uptime}</span>
                </div>
                <div class="flex justify-between text-sm mt-2">
                    <span class="text-gray-500">最后上报</span>
                    <span class="text-gray-900 font-medium">${device.last_seen_ago}</span>
                </div>
            </div>
        </div>
    `;
}

// 查看设备详情
function viewDevice(deviceId) {
    window.location.href = `device.php?id=${deviceId}`;
}

// 格式化运行时间
function formatUptime(seconds) {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    
    const parts = [];
    if (days > 0) parts.push(`${days}天`);
    if (hours > 0) parts.push(`${hours}小时`);
    if (minutes > 0) parts.push(`${minutes}分钟`);
    
    return parts.join(' ') || '0分钟';
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 显示错误
function showError(message) {
    const loading = document.getElementById('loading');
    loading.classList.remove('hidden');
    loading.innerHTML = `
        <div class="text-red-600">
            <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="mt-4">${message}</p>
        </div>
    `;
}

