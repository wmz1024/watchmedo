// 设备详情页面逻辑

let deviceId = null;
let currentDate = null;
let pieChart = null;
let barChart = null;
let refreshInterval = null;
let countdownInterval = null;
let countdownSeconds = 10;

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 获取设备ID
    const urlParams = new URLSearchParams(window.location.search);
    deviceId = urlParams.get('id');
    
    if (!deviceId) {
        alert('缺少设备ID');
        window.location.href = 'index.php';
        return;
    }
    
    // 设置默认日期为今天
    currentDate = new Date().toISOString().split('T')[0];
    document.getElementById('date-picker').value = currentDate;
    
    // 日期选择器变化事件
    document.getElementById('date-picker').addEventListener('change', function() {
        currentDate = this.value;
        loadDeviceData();
    });
    
    // 刷新按钮
    document.getElementById('refresh-btn').addEventListener('click', function() {
        loadDeviceData();
        loadRealtimeData();
    });
    
    // AI分析按钮
    document.getElementById('generate-ai-btn').addEventListener('click', generateAIAnalysis);
    
    // 窗口大小改变时重绘图表
    window.addEventListener('resize', function() {
        if (pieChart) pieChart.resize();
        if (barChart) barChart.resize();
    });
    
    // 加载数据
    loadDeviceData();
    
    // 启动实时数据自动刷新（每10秒）
    startRealtimeRefresh();
});

// 清理定时器
window.addEventListener('beforeunload', function() {
    stopRealtimeRefresh();
});

// 加载设备数据
async function loadDeviceData() {
    try {
        const response = await fetch(`../api/stats.php?action=device&device_id=${deviceId}&date=${currentDate}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || '加载失败');
        }
        
        renderDeviceData(result.data);
        
    } catch (error) {
        console.error('加载设备数据失败:', error);
        alert('加载数据失败: ' + error.message);
    }
}

// 渲染设备数据
function renderDeviceData(data) {
    const loading = document.getElementById('loading');
    const content = document.getElementById('device-content');
    
    loading.classList.add('hidden');
    content.classList.remove('hidden');
    
    // 初始化图表（如果还没有初始化）
    if (!pieChart) {
        pieChart = echarts.init(document.getElementById('pie-chart'));
    }
    if (!barChart) {
        barChart = echarts.init(document.getElementById('bar-chart'));
    }
    
    // 设备基本信息
    document.getElementById('device-name').textContent = data.device.name;
    document.getElementById('computer-name').textContent = data.device.computer_name || '-';
    
    const statusIndicator = document.getElementById('status-indicator');
    const statusText = document.getElementById('status-text');
    
    if (data.device.is_online) {
        statusIndicator.className = 'w-3 h-3 rounded-full mr-2 bg-green-500 animate-pulse';
        statusText.textContent = '在线';
        statusText.className = 'text-green-600';
    } else {
        statusIndicator.className = 'w-3 h-3 rounded-full mr-2 bg-gray-400';
        statusText.textContent = '离线';
        statusText.className = 'text-gray-500';
    }
    
    document.getElementById('last-seen').textContent = data.device.last_seen_ago;
    
    // 系统资源
    const stats = data.latest_stats || {};
    
    document.getElementById('cpu-usage').textContent = stats.cpu_usage_avg 
        ? stats.cpu_usage_avg.toFixed(1) + '%' 
        : '-';
    
    document.getElementById('memory-usage').textContent = stats.memory_percent 
        ? stats.memory_percent.toFixed(1) + '%' 
        : '-';
    
    document.getElementById('uptime').textContent = stats.uptime 
        ? formatUptime(stats.uptime) 
        : '-';
    
    document.getElementById('total-usage').textContent = data.total_usage.formatted;
    document.getElementById('active-apps').textContent = data.top_apps.length;
    
    // 渲染图表
    renderPieChart(data.pie_chart);
    renderBarChart(data.hourly_chart);
    
    // 确保图表正确显示
    setTimeout(function() {
        if (pieChart) pieChart.resize();
        if (barChart) barChart.resize();
    }, 100);
    
    // 渲染最常用时间段
    renderActiveHours(data.most_active_hours);
    
    // 渲染应用列表
    renderAppList(data.top_apps);
}

// 渲染饼图
function renderPieChart(data) {
    const option = {
        tooltip: {
            trigger: 'item',
            formatter: function(params) {
                return `${params.name}<br/>使用时间: ${formatUptime(params.value)}<br/>占比: ${params.percent}%`;
            }
        },
        legend: {
            orient: 'vertical',
            right: 10,
            top: 'center',
            textStyle: {
                fontSize: 12
            }
        },
        series: [
            {
                name: '应用使用时间',
                type: 'pie',
                radius: ['40%', '70%'],
                center: ['40%', '50%'],
                avoidLabelOverlap: true,
                itemStyle: {
                    borderRadius: 10,
                    borderColor: '#fff',
                    borderWidth: 2
                },
                label: {
                    show: true,
                    formatter: '{b}: {d}%',
                    fontSize: 11
                },
                emphasis: {
                    label: {
                        show: true,
                        fontSize: 14,
                        fontWeight: 'bold'
                    }
                },
                data: data.map(item => ({
                    name: item.name,
                    value: item.value
                }))
            }
        ]
    };
    
    pieChart.setOption(option);
}

// 渲染柱状图
function renderBarChart(data) {
    const hours = Array.from({length: 24}, (_, i) => i + ':00');
    
    const option = {
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            },
            formatter: function(params) {
                const value = params[0].value;
                return `${params[0].name}<br/>使用时间: ${formatUptime(value)}`;
            }
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            data: hours,
            axisLabel: {
                fontSize: 10,
                rotate: 45
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: function(value) {
                    return Math.floor(value / 60) + '分';
                }
            }
        },
        series: [
            {
                name: '使用时间',
                type: 'bar',
                data: data,
                itemStyle: {
                    color: '#3B82F6',
                    borderRadius: [4, 4, 0, 0]
                },
                emphasis: {
                    itemStyle: {
                        color: '#2563EB'
                    }
                }
            }
        ]
    };
    
    barChart.setOption(option);
}

// 渲染最常用时间段
function renderActiveHours(hours) {
    const container = document.getElementById('active-hours');
    
    if (hours.length === 0) {
        container.innerHTML = '<p class="text-gray-500 col-span-5 text-center">暂无数据</p>';
        return;
    }
    
    container.innerHTML = hours.map((item, index) => `
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 font-bold mb-2">
                ${index + 1}
            </div>
            <p class="text-sm font-medium text-gray-900">${item.hour}:00 - ${item.hour + 1}:00</p>
            <p class="text-xs text-gray-500 mt-1">${item.formatted}</p>
        </div>
    `).join('');
}

// 渲染应用列表
function renderAppList(apps) {
    const container = document.getElementById('app-list');
    
    if (apps.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center">暂无数据</p>';
        return;
    }
    
    container.innerHTML = apps.map((app, index) => `
        <div class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-start justify-between mb-2">
                <div class="flex-1">
                    <div class="flex items-center">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-600 text-xs font-bold mr-2">
                            ${index + 1}
                        </span>
                        <h4 class="text-sm font-semibold text-gray-900">${escapeHtml(app.name)}</h4>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 ml-8">${escapeHtml(app.window_title)}</p>
                </div>
                <div class="text-right ml-4">
                    <p class="text-sm font-semibold text-gray-900">${app.usage_formatted}</p>
                    <p class="text-xs text-gray-500">${app.percentage}%</p>
                </div>
            </div>
            
            <div class="ml-8">
                <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                    <div class="progress-bar bg-blue-600 h-2 rounded-full" style="width: ${app.percentage}%"></div>
                </div>
                
                <div class="flex justify-between text-xs text-gray-500">
                    <span>CPU: ${app.avg_cpu}%</span>
                    <span>内存: ${app.avg_memory_formatted}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// 生成AI分析
async function generateAIAnalysis() {
    const btn = document.getElementById('generate-ai-btn');
    const loading = document.getElementById('ai-loading');
    const content = document.getElementById('ai-content');
    const result = document.getElementById('ai-result');
    
    btn.disabled = true;
    btn.textContent = '生成中...';
    loading.classList.remove('hidden');
    content.classList.add('hidden');
    
    try {
        const response = await fetch('../api/ai.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'summary',
                device_id: deviceId,
                date: currentDate
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'AI分析失败');
        }
        
        result.textContent = data.data.summary;
        content.classList.remove('hidden');
        
    } catch (error) {
        console.error('AI分析失败:', error);
        alert('AI分析失败: ' + error.message);
    } finally {
        loading.classList.add('hidden');
        btn.disabled = false;
        btn.textContent = '重新生成';
    }
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

// 启动实时数据刷新
function startRealtimeRefresh() {
    // 立即加载一次
    loadRealtimeData();
    
    // 每10秒刷新一次
    refreshInterval = setInterval(function() {
        loadRealtimeData();
    }, 10000);
    
    // 倒计时显示
    startCountdown();
}

// 停止实时数据刷新
function stopRealtimeRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
}

// 倒计时
function startCountdown() {
    countdownSeconds = 10;
    updateCountdownDisplay();
    
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    
    countdownInterval = setInterval(function() {
        countdownSeconds--;
        if (countdownSeconds <= 0) {
            countdownSeconds = 10;
        }
        updateCountdownDisplay();
    }, 1000);
}

// 更新倒计时显示
function updateCountdownDisplay() {
    const countdownEl = document.getElementById('refresh-countdown');
    if (countdownEl) {
        countdownEl.textContent = `${countdownSeconds}秒后刷新`;
    }
}

// 加载实时数据
async function loadRealtimeData() {
    try {
        const response = await fetch(`../api/stats.php?action=realtime&device_id=${deviceId}`);
        const result = await response.json();
        
        console.log('实时数据响应:', result); // 调试日志
        
        if (result.success && result.data) {
            renderRealtimeData(result.data);
            // 重置倒计时
            startCountdown();
        } else {
            console.error('实时数据加载失败:', result.error || '未知错误');
            // 显示错误信息
            showRealtimeError(result.error || '暂无实时数据');
        }
    } catch (error) {
        console.error('加载实时数据失败:', error);
        showRealtimeError('网络请求失败');
    }
}

// 渲染实时数据
function renderRealtimeData(data) {
    console.log('渲染实时数据:', data); // 调试日志
    
    // 渲染正在聚焦的应用
    if (data.processes) {
        renderFocusedApp(data.processes);
    } else {
        console.warn('没有进程数据');
    }
    
    // 渲染网络流量
    if (data.network) {
        renderNetworkStats(data.network);
    } else {
        console.warn('没有网络数据');
    }
}

// 显示实时数据错误
function showRealtimeError(message) {
    const focusedContainer = document.getElementById('focused-app');
    const networkContainer = document.getElementById('network-stats');
    
    if (focusedContainer) {
        focusedContainer.innerHTML = `<p class="text-gray-500 text-sm">${escapeHtml(message)}</p>`;
    }
    
    if (networkContainer) {
        networkContainer.innerHTML = `<p class="text-gray-500 text-sm">${escapeHtml(message)}</p>`;
    }
}

// 渲染正在聚焦的应用
function renderFocusedApp(processes) {
    const container = document.getElementById('focused-app');
    
    if (!container) {
        console.error('找不到focused-app容器');
        return;
    }
    
    console.log('进程列表:', processes); // 调试日志
    
    // 找到正在聚焦的应用
    const focusedApp = processes.find(p => p.is_focused);
    
    console.log('聚焦的应用:', focusedApp); // 调试日志
    
    if (focusedApp) {
        container.innerHTML = `
            <div class="flex items-center justify-center space-x-4">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-2">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="text-left flex-1">
                    <h4 class="text-xl font-bold text-gray-900">${escapeHtml(focusedApp.name)}</h4>
                    <p class="text-sm text-gray-600 mt-1">${escapeHtml(focusedApp.window_title)}</p>
                    <div class="flex items-center space-x-4 mt-2">
                        <span class="text-xs text-gray-500">
                            <span class="font-semibold">CPU:</span> ${focusedApp.cpu_usage}%
                        </span>
                        <span class="text-xs text-gray-500">
                            <span class="font-semibold">内存:</span> ${focusedApp.memory_formatted}
                        </span>
                    </div>
                </div>
            </div>
        `;
    } else {
        container.innerHTML = `
            <div class="text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <p class="text-sm">当前无活跃应用</p>
            </div>
        `;
    }
}

// 渲染网络流量
function renderNetworkStats(network) {
    const container = document.getElementById('network-stats');
    
    if (!container) {
        console.error('找不到network-stats容器');
        return;
    }
    
    console.log('网络数据:', network); // 调试日志
    
    if (!network || network.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center">暂无网络数据</p>';
        return;
    }
    
    // 计算总流量
    let totalReceived = 0;
    let totalTransmitted = 0;
    
    network.forEach(n => {
        totalReceived += n.received;
        totalTransmitted += n.transmitted;
    });
    
    container.innerHTML = `
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="flex items-center justify-center mb-2">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M7 16l-4-4m0 0l4-4m-4 4h18"></path>
                    </svg>
                </div>
                <p class="text-xs text-gray-500">下载</p>
                <p class="text-lg font-bold text-green-600">${formatBytes(totalReceived)}</p>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="flex items-center justify-center mb-2">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                </div>
                <p class="text-xs text-gray-500">上传</p>
                <p class="text-lg font-bold text-blue-600">${formatBytes(totalTransmitted)}</p>
            </div>
        </div>
        <div class="space-y-2">
            ${network.map(n => `
                <div class="border-t border-gray-200 pt-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-gray-700">${escapeHtml(n.name)}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
                        <span>↓ ${n.received_formatted}</span>
                        <span>↑ ${n.transmitted_formatted}</span>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// 格式化字节大小
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

