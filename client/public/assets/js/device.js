// 设备详情页面逻辑

let deviceId = null;
let currentDate = null;
let pieChart = null;
let barChart = null;

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
    document.getElementById('refresh-btn').addEventListener('click', loadDeviceData);
    
    // AI分析按钮
    document.getElementById('generate-ai-btn').addEventListener('click', generateAIAnalysis);
    
    // 窗口大小改变时重绘图表
    window.addEventListener('resize', function() {
        if (pieChart) pieChart.resize();
        if (barChart) barChart.resize();
    });
    
    // 加载数据
    loadDeviceData();
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

