// 设备详情页面逻辑

let deviceId = null;
let currentDate = null;
let currentViewMode = 'day'; // 'day', 'month', 'year'
let pieChart = null;
let barChart = null;
let realtimeChart = null;
let refreshInterval = null;
let countdownInterval = null;
let countdownSeconds = 10;

// 分页相关
let allApps = [];
let currentPage = 1;
let itemsPerPage = 10;

// 实时数据历史（用于折线统计图）
let realtimeHistory = {
    timestamps: [],
    cpu: [],
    memory: [],
    apps: [],
    maxPoints: 30 // 保留最近30个数据点（5分钟）
};

// 应用时间轴数据
let appTimeline = [];
let timelineUpdateInterval = null;

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
    
    // 初始化月份选择器
    const now = new Date();
    const currentMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    document.getElementById('month-picker').value = currentMonth;
    
    // 初始化年份选择器
    initYearPicker();
    
    // 视图模式切换
    document.getElementById('view-day').addEventListener('click', () => switchViewMode('day'));
    document.getElementById('view-month').addEventListener('click', () => switchViewMode('month'));
    document.getElementById('view-year').addEventListener('click', () => switchViewMode('year'));
    
    // 日期选择器变化事件
    document.getElementById('date-picker').addEventListener('change', function() {
        currentDate = this.value;
        loadDeviceData();
        loadRealtimeData();
    });
    
    // 月份选择器变化事件
    document.getElementById('month-picker').addEventListener('change', function() {
        currentDate = this.value;
        loadDeviceData();
        loadRealtimeData();
    });
    
    // 年份选择器变化事件
    document.getElementById('year-picker').addEventListener('change', function() {
        currentDate = this.value;
        loadDeviceData();
        loadRealtimeData();
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
        if (realtimeChart) realtimeChart.resize();
    });
    
    // 手机端菜单按钮
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', toggleSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    // 加载数据
    loadDeviceData();
    
    // 加载其他设备列表
    loadOtherDevices();
    
    // 加载Giscus评论配置
    loadGiscusConfig();
    
    // 加载应用时间轴
    loadAppTimeline();
    
    // 启动实时数据自动刷新（每10秒）
    startRealtimeRefresh();
    
    // 启动时间轴时间实时更新（每1秒）
    startTimelineUpdate();
});

// 清理定时器
window.addEventListener('beforeunload', function() {
    stopRealtimeRefresh();
    stopTimelineUpdate();
});

// 切换侧边栏（手机端）
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar.classList.contains('show')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

// 打开侧边栏
function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.add('show');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // 禁止背景滚动
}

// 关闭侧边栏
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.remove('show');
    overlay.classList.add('hidden');
    document.body.style.overflow = ''; // 恢复滚动
}

// 初始化年份选择器
function initYearPicker() {
    const yearPicker = document.getElementById('year-picker');
    const currentYear = new Date().getFullYear();
    
    // 生成最近5年的选项
    for (let i = 0; i < 5; i++) {
        const year = currentYear - i;
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year + '年';
        yearPicker.appendChild(option);
    }
}

// 切换视图模式
function switchViewMode(mode) {
    currentViewMode = mode;
    
    // 更新按钮样式
    document.querySelectorAll('.view-mode-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById(`view-${mode}`).classList.add('active');
    
    // 显示/隐藏对应的选择器
    document.getElementById('date-picker').classList.toggle('hidden', mode !== 'day');
    document.getElementById('month-picker').classList.toggle('hidden', mode !== 'month');
    document.getElementById('year-picker').classList.toggle('hidden', mode !== 'year');
    
    // 设置当前日期值
    const now = new Date();
    if (mode === 'day') {
        currentDate = now.toISOString().split('T')[0];
        document.getElementById('date-picker').value = currentDate;
    } else if (mode === 'month') {
        currentDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        document.getElementById('month-picker').value = currentDate;
    } else if (mode === 'year') {
        currentDate = now.getFullYear().toString();
        document.getElementById('year-picker').value = currentDate;
    }
    
    // 重新加载所有数据（统计数据 + 实时数据）
    loadDeviceData();
    loadRealtimeData();
}

// 加载设备数据
async function loadDeviceData() {
    try {
        const url = `../api/stats.php?action=device&device_id=${deviceId}&date=${currentDate}&view_mode=${currentViewMode}`;
        const response = await fetch(url);
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
    if (!realtimeChart) {
        realtimeChart = echarts.init(document.getElementById('realtime-chart'));
        initRealtimeChart();
    }
    
    // 设备基本信息
    document.getElementById('device-name').textContent = data.device.name;
    updateDeviceStatus(data.device);
    
    // 系统资源
    const stats = data.latest_stats || {};
    
    document.getElementById('total-usage').textContent = data.total_usage.formatted;
    
    // 初始化实时折线图数据（首次加载）
    const cpuValue = stats.cpu_usage_avg || 0;
    const memoryValue = stats.memory_percent || 0;
    const appsValue = data.total_apps_count || data.top_apps.length;
    
    // 缓存活跃应用数
    window.lastAppsCount = appsValue;
    
    // 添加初始数据到折线统计图
    addRealtimeDataPoint('cpu', cpuValue);
    addRealtimeDataPoint('memory', memoryValue);
    addRealtimeDataPoint('apps', appsValue);
    updateRealtimeChart();
    
    // 渲染电池信息（兼容旧版本）
    renderBatteryInfo(stats);
    
    // 渲染图表
    renderPieChart(data.pie_chart);
    
    // 使用返回的view_mode或当前的viewMode
    const viewMode = data.view_mode || currentViewMode;
    renderTimeChart(data.time_chart, viewMode);
    
    // 确保图表正确显示
    setTimeout(function() {
        if (pieChart) pieChart.resize();
        if (barChart) barChart.resize();
        if (realtimeChart) realtimeChart.resize();
    }, 100);
    
    // 渲染最常用时间段
    renderActiveHours(data.most_active_hours);
    
    // 保存所有应用数据并渲染第一页
    allApps = data.top_apps;
    currentPage = 1;
    renderAppListWithPagination();
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

// 渲染时间维度图表（日/月/年）
function renderTimeChart(chartData, viewMode) {
    const chartTitle = viewMode === 'year' ? '月度使用时间' : 
                      viewMode === 'month' ? '每日使用时间' : 
                      '24小时使用时间';
    
    // 更新图表标题
    const chartTitleEl = document.querySelector('#bar-chart').parentElement.querySelector('h3');
    if (chartTitleEl) {
        chartTitleEl.textContent = chartTitle;
    }
    
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
            data: chartData.labels,
            axisLabel: {
                fontSize: 10,
                rotate: viewMode === 'month' ? 45 : 0
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: function(value) {
                    if (viewMode === 'year' || viewMode === 'month') {
                        // 年视图和月视图：显示小时
                        const hours = Math.floor(value / 3600);
                        if (hours > 0) {
                            return hours + '时';
                        }
                        return Math.floor(value / 60) + '分';
                    } else {
                        // 日视图：显示分钟
                        return Math.floor(value / 60) + '分';
                    }
                }
            }
        },
        series: [
            {
                name: '使用时间',
                type: 'bar',
                data: chartData.data,
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
    
    barChart.setOption(option, true); // true表示不合并，直接替换
}

// 渲染最常用时间段
function renderActiveHours(hours) {
    const container = document.getElementById('active-hours');
    const titleEl = document.getElementById('active-periods-title');
    
    // 根据视图模式更新标题
    const title = currentViewMode === 'year' ? '使用最多的月份' :
                  currentViewMode === 'month' ? '使用最多的日期' :
                  '最常用时间段';
    if (titleEl) {
        titleEl.textContent = title;
    }
    
    if (hours.length === 0) {
        container.innerHTML = '<p class="text-gray-500 col-span-5 text-center">暂无数据</p>';
        return;
    }
    
    container.innerHTML = hours.map((item, index) => {
        let periodText = '';
        
        if (currentViewMode === 'year') {
            // 年视图：显示月份
            periodText = `${item.period}月`;
        } else if (currentViewMode === 'month') {
            // 月视图：显示日期
            periodText = `${item.period}日`;
        } else {
            // 日视图：显示小时
            periodText = `${item.hour}:00 - ${item.hour + 1}:00`;
        }
        
        return `
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 font-bold mb-2">
                ${index + 1}
            </div>
            <p class="text-sm font-medium text-gray-900">${periodText}</p>
            <p class="text-xs text-gray-500 mt-1">${item.formatted}</p>
        </div>
    `;
    }).join('');
}

// 渲染应用列表（带分页）
function renderAppListWithPagination() {
    const container = document.getElementById('app-list');
    const totalApps = allApps.length;
    
    // 更新应用计数信息
    document.getElementById('app-count-info').textContent = `共 ${totalApps} 个应用（已过滤0分钟应用）`;
    
    if (totalApps === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">暂无使用时间超过1分钟的应用</p>';
        document.getElementById('pagination').classList.add('hidden');
        return;
    }
    
    document.getElementById('pagination').classList.remove('hidden');
    
    // 计算分页
    const totalPages = Math.ceil(totalApps / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, totalApps);
    const pageApps = allApps.slice(startIndex, endIndex);
    
    // 渲染当前页的应用
    container.innerHTML = pageApps.map((app, index) => {
        const globalIndex = startIndex + index + 1;
        return `
        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-2">
                <div class="flex-1">
                    <div class="flex items-center">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-600 text-xs font-bold mr-2">
                            ${globalIndex}
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
    `;
    }).join('');
    
    // 更新分页信息
    document.getElementById('page-start').textContent = startIndex + 1;
    document.getElementById('page-end').textContent = endIndex;
    document.getElementById('total-apps').textContent = totalApps;
    
    // 渲染分页按钮
    renderPagination(totalPages);
}

// 渲染分页控件
function renderPagination(totalPages) {
    const container = document.getElementById('pagination-buttons');
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let buttons = [];
    
    // 上一页按钮
    buttons.push(`
        <button onclick="changePage(${currentPage - 1})" 
                ${currentPage === 1 ? 'disabled' : ''}
                class="px-3 py-1 border border-gray-300 rounded-md text-sm ${currentPage === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}">
            ← 上一页
        </button>
    `);
    
    // 页码按钮
    const maxButtons = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    let endPage = Math.min(totalPages, startPage + maxButtons - 1);
    
    if (endPage - startPage < maxButtons - 1) {
        startPage = Math.max(1, endPage - maxButtons + 1);
    }
    
    if (startPage > 1) {
        buttons.push(`<button onclick="changePage(1)" class="px-3 py-1 border border-gray-300 rounded-md text-sm bg-white text-gray-700 hover:bg-gray-50">1</button>`);
        if (startPage > 2) {
            buttons.push(`<span class="px-2 text-gray-500">...</span>`);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        buttons.push(`
            <button onclick="changePage(${i})" 
                    class="px-3 py-1 border rounded-md text-sm ${i === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'}">
                ${i}
            </button>
        `);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            buttons.push(`<span class="px-2 text-gray-500">...</span>`);
        }
        buttons.push(`<button onclick="changePage(${totalPages})" class="px-3 py-1 border border-gray-300 rounded-md text-sm bg-white text-gray-700 hover:bg-gray-50">${totalPages}</button>`);
    }
    
    // 下一页按钮
    buttons.push(`
        <button onclick="changePage(${currentPage + 1})" 
                ${currentPage === totalPages ? 'disabled' : ''}
                class="px-3 py-1 border border-gray-300 rounded-md text-sm ${currentPage === totalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}">
            下一页 →
        </button>
    `);
    
    container.innerHTML = buttons.join('');
}

// 切换页码
function changePage(page) {
    const totalPages = Math.ceil(allApps.length / itemsPerPage);
    
    if (page < 1 || page > totalPages) {
        return;
    }
    
    currentPage = page;
    renderAppListWithPagination();
    
    // 滚动到应用列表顶部
    document.getElementById('app-list').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

// 格式化停留时间（包含秒）
function formatDuration(seconds) {
    if (seconds < 0) seconds = 0;
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    const parts = [];
    if (hours > 0) parts.push(`${hours}小时`);
    if (minutes > 0) parts.push(`${minutes}分钟`);
    parts.push(`${secs}秒`);
    
    return parts.join(' ');
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
    loadCurrentMedia(); // 加载媒体播放状态
    
    // 每10秒刷新一次
    refreshInterval = setInterval(function() {
        loadRealtimeData();
        loadOtherDevices(); // 同时刷新其他设备列表
        loadAppTimeline(); // 同时刷新应用时间轴
        loadCurrentMedia(); // 刷新媒体播放状态
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
    
    // 计算活跃应用数（有CPU或内存使用的进程）
    let activeAppsCount = 0;
    if (data.processes && data.processes.length > 0) {
        activeAppsCount = data.processes.filter(p => 
            p.cpu_usage > 0 || p.memory_usage > 0
        ).length;
        window.lastAppsCount = activeAppsCount;
    }
    
    // 更新系统资源信息
    if (data.stats) {
        updateSystemResources(data.stats, data.device);
    } else {
        console.warn('没有系统资源数据');
    }
    
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

// 更新系统资源信息
function updateSystemResources(stats, deviceInfo) {
    // 获取当前活跃应用数（从进程数据中获取，或使用缓存值）
    const appsValue = window.lastAppsCount || 0;
    
    // 更新CPU使用率
    if (stats.cpu_usage_avg !== null && stats.cpu_usage_avg !== undefined) {
        const cpuValue = parseFloat(stats.cpu_usage_avg) || 0;
        addRealtimeDataPoint('cpu', cpuValue);
    }
    
    // 更新内存使用率
    if (stats.memory_percent !== null && stats.memory_percent !== undefined) {
        const memoryValue = parseFloat(stats.memory_percent) || 0;
        addRealtimeDataPoint('memory', memoryValue);
    }
    
    // 更新活跃应用数
    addRealtimeDataPoint('apps', appsValue);
    
        // 更新实时折线统计图
    updateRealtimeChart();
    
    // 更新电池信息（兼容旧版本）
    renderBatteryInfo(stats);
    
    // 更新设备在线状态（如果提供了设备信息）
    if (deviceInfo) {
        updateDeviceStatus(deviceInfo);
    }
    
    // 更新最后上报时间（如果有）
    if (stats.timestamp) {
        const lastSeenAgo = timeAgoFromTimestamp(stats.timestamp);
        const lastSeenEl = document.getElementById('last-seen');
        if (lastSeenEl) {
            lastSeenEl.textContent = lastSeenAgo;
        }
    }
}

// 更新设备状态
function updateDeviceStatus(device) {
    // 更新计算机名称
    if (device.computer_name) {
        const computerNameEl = document.getElementById('computer-name');
        if (computerNameEl) {
            computerNameEl.textContent = device.computer_name;
        }
    }
    
    // 更新在线状态
    const statusIndicator = document.getElementById('status-indicator');
    const statusText = document.getElementById('status-text');
    
    if (statusIndicator && statusText) {
        if (device.is_online) {
            statusIndicator.className = 'w-3 h-3 rounded-full mr-2 bg-green-500 animate-pulse';
            statusText.textContent = '在线';
            statusText.className = 'text-green-600';
        } else {
            statusIndicator.className = 'w-3 h-3 rounded-full mr-2 bg-gray-400';
            statusText.textContent = '离线';
            statusText.className = 'text-gray-500';
        }
    }
    
    // 更新最后上报时间
    if (device.last_seen_ago) {
        const lastSeenEl = document.getElementById('last-seen');
        if (lastSeenEl) {
            lastSeenEl.textContent = device.last_seen_ago;
        }
    }
}

// 计算时间差（前端版本）
function timeAgoFromTimestamp(timestamp) {
    const now = new Date();
    const then = new Date(timestamp);
    const diffMs = now - then;
    const diffSecs = Math.floor(diffMs / 1000);
    
    if (diffSecs < 60) return diffSecs + '秒前';
    if (diffSecs < 3600) return Math.floor(diffSecs / 60) + '分钟前';
    if (diffSecs < 86400) return Math.floor(diffSecs / 3600) + '小时前';
    return Math.floor(diffSecs / 86400) + '天前';
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
            <div class="space-y-3">
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
                
                <!-- 连续停留时间显示 -->
                <div class="border-t border-gray-200 pt-3">
                    <div class="flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">连续停留:</span>
                        <span class="text-lg font-bold text-blue-600">${escapeHtml(focusedApp.focused_duration_formatted || '0秒')}</span>
                    </div>
                    <div class="text-center mt-1">
                        <span class="text-xs text-gray-400">未切换其他应用的持续时间 (精确到秒)</span>
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

// 加载Giscus评论配置
async function loadGiscusConfig() {
    try {
        const response = await fetch('../api/settings.php?action=giscus');
        const result = await response.json();
        
        if (result.success && result.data && result.data.enabled) {
            initGiscus(result.data);
        }
    } catch (error) {
        console.error('加载Giscus配置失败:', error);
    }
}

// 初始化Giscus评论
function initGiscus(config) {
    // 验证必需配置
    if (!config.repo || !config.repo_id || !config.category_id) {
        console.warn('Giscus配置不完整');
        return;
    }
    
    const section = document.getElementById('giscus-section');
    const container = document.getElementById('giscus-container');
    
    if (!section || !container) {
        console.error('找不到Giscus容器');
        return;
    }
    
    // 显示评论区
    section.classList.remove('hidden');
    
    // 创建giscus脚本
    const script = document.createElement('script');
    script.src = 'https://giscus.app/client.js';
    script.setAttribute('data-repo', config.repo);
    script.setAttribute('data-repo-id', config.repo_id);
    script.setAttribute('data-category', config.category || 'General');
    script.setAttribute('data-category-id', config.category_id);
    script.setAttribute('data-mapping', 'pathname');
    script.setAttribute('data-strict', '0');
    script.setAttribute('data-reactions-enabled', '1');
    script.setAttribute('data-emit-metadata', '0');
    script.setAttribute('data-input-position', 'top');
    script.setAttribute('data-theme', config.theme || 'light');
    script.setAttribute('data-lang', 'zh-CN');
    script.setAttribute('data-loading', 'lazy');
    script.crossOrigin = 'anonymous';
    script.async = true;
    
    // 清空容器并添加脚本
    container.innerHTML = '';
    container.appendChild(script);
}

// 加载其他设备列表
async function loadOtherDevices() {
    try {
        const response = await fetch('../api/stats.php?action=overview');
        const result = await response.json();
        
        if (result.success && result.data) {
            renderOtherDevices(result.data);
        }
    } catch (error) {
        console.error('加载其他设备失败:', error);
    }
}

// 渲染其他设备列表
function renderOtherDevices(devices) {
    const container = document.getElementById('other-devices-list');
    
    // 过滤掉当前设备
    const otherDevices = devices.filter(d => d.id != deviceId);
    
    // 更新设备数量
    document.getElementById('other-devices-count').textContent = otherDevices.length + '台';
    
    if (otherDevices.length === 0) {
        container.innerHTML = '<p class="text-xs text-gray-500 text-center py-4">暂无其他设备</p>';
        return;
    }
    
    container.innerHTML = otherDevices.map(device => {
        const isOnline = device.is_online;
        const statusColor = isOnline ? 'bg-green-500' : 'bg-gray-400';
        const statusClass = isOnline ? 'animate-pulse' : '';
        
        return `
        <a href="device.php?id=${device.id}" 
           onclick="closeSidebar()"
           class="block p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors ${device.id == deviceId ? 'bg-blue-50 border-blue-300' : ''}">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(device.name)}</p>
                    <p class="text-xs text-gray-500 truncate">${escapeHtml(device.computer_name || '-')}</p>
                </div>
                <div class="ml-2">
                    <span class="w-2 h-2 rounded-full ${statusColor} ${statusClass} inline-block"></span>
                </div>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                <span>${isOnline ? '在线' : '离线'}</span>
                <span>${device.last_seen_ago}</span>
            </div>
        </a>
        `;
    }).join('');
}

// 初始化实时折线统计图
function initRealtimeChart() {
    const option = {
        title: {
            left: 'center',
            textStyle: {
                fontSize: 14,
                color: '#666'
            }
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'cross',
                label: {
                    backgroundColor: '#6a7985'
                }
            },
            formatter: function(params) {
                let result = params[0].name + '<br/>';
                params.forEach(function(item) {
                    const unit = item.seriesName === '应用数' ? '个' : '%';
                    result += item.marker + item.seriesName + ': ' + item.value + unit + '<br/>';
                });
                return result;
            }
        },
        legend: {
            data: ['CPU使用率', '内存使用率', '活跃应用数'],
            top: 10,
            textStyle: {
                fontSize: 12
            }
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '5%',
            top: '15%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: [],
            axisLabel: {
                fontSize: 11,
                rotate: 0
            }
        },
        yAxis: [
            {
                type: 'value',
                name: '使用率(%)',
                position: 'left',
                min: 0,
                max: 100,
                axisLabel: {
                    formatter: '{value}%'
                },
                splitLine: {
                    lineStyle: {
                        type: 'dashed'
                    }
                }
            },
            {
                type: 'value',
                name: '应用数',
                position: 'right',
                min: 0,
                axisLabel: {
                    formatter: '{value}'
                },
                splitLine: {
                    show: false
                }
            }
        ],
        series: [
            {
                name: 'CPU使用率',
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                data: [],
                lineStyle: {
                    width: 2
                },
                itemStyle: {
                    color: '#3b82f6'
                },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [{
                            offset: 0, color: 'rgba(59, 130, 246, 0.3)'
                        }, {
                            offset: 1, color: 'rgba(59, 130, 246, 0.05)'
                        }]
                    }
                }
            },
            {
                name: '内存使用率',
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                data: [],
                lineStyle: {
                    width: 2
                },
                itemStyle: {
                    color: '#10b981'
                },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [{
                            offset: 0, color: 'rgba(16, 185, 129, 0.3)'
                        }, {
                            offset: 1, color: 'rgba(16, 185, 129, 0.05)'
                        }]
                    }
                }
            },
            {
                name: '活跃应用数',
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                yAxisIndex: 1,
                data: [],
                lineStyle: {
                    width: 2,
                    type: 'dashed'
                },
                itemStyle: {
                    color: '#f59e0b'
                }
            }
        ]
    };
    
    realtimeChart.setOption(option);
}

// 添加实时数据点
function addRealtimeDataPoint(type, value) {
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2, '0') + ':' + 
                    now.getMinutes().toString().padStart(2, '0') + ':' + 
                    now.getSeconds().toString().padStart(2, '0');
    
    // 添加时间戳（只添加一次）
    if (realtimeHistory.timestamps.length === 0 || 
        realtimeHistory.timestamps[realtimeHistory.timestamps.length - 1] !== timeStr) {
        realtimeHistory.timestamps.push(timeStr);
        
        // 限制数据点数量
        if (realtimeHistory.timestamps.length > realtimeHistory.maxPoints) {
            realtimeHistory.timestamps.shift();
        }
    }
    
    // 添加对应类型的数据
    if (realtimeHistory[type]) {
        realtimeHistory[type].push(value);
        
        // 限制数据点数量
        if (realtimeHistory[type].length > realtimeHistory.maxPoints) {
            realtimeHistory[type].shift();
        }
    }
}

// 更新实时趋势图
function updateRealtimeChart() {
    if (!realtimeChart) return;
    
    const option = {
        xAxis: {
            data: realtimeHistory.timestamps
        },
        series: [
            {
                data: realtimeHistory.cpu
            },
            {
                data: realtimeHistory.memory
            },
            {
                data: realtimeHistory.apps
            }
        ]
    };
    
    realtimeChart.setOption(option);
}

// 渲染电池信息（兼容旧版本）
function renderBatteryInfo(stats) {
    const batteryContainer = document.getElementById('battery-info');
    
    // 检查是否有电池数据（兼容旧版本）
    if (!stats || stats.battery_percentage === null || stats.battery_percentage === undefined) {
        batteryContainer.classList.add('hidden');
        return;
    }
    
    // 显示电池信息
    batteryContainer.classList.remove('hidden');
    
    const percentage = parseFloat(stats.battery_percentage) || 0;
    const isCharging = Boolean(stats.battery_is_charging);
    const status = stats.battery_status || '未知';
    
    // 更新显示
    document.getElementById('battery-percentage').textContent = percentage.toFixed(0) + '%';
    document.getElementById('battery-status').textContent = status;
    
    // 更新进度条
    const batteryBar = document.getElementById('battery-bar');
    batteryBar.style.width = percentage + '%';
    
    // 根据电量和充电状态设置颜色
    let barColor = '';
    let textColor = '';
    let iconColor = '';
    
    if (isCharging) {
        barColor = 'bg-green-500';
        textColor = 'text-green-600';
        iconColor = 'text-green-600';
    } else if (percentage > 50) {
        barColor = 'bg-blue-500';
        textColor = 'text-blue-600';
        iconColor = 'text-blue-600';
    } else if (percentage > 20) {
        barColor = 'bg-yellow-500';
        textColor = 'text-yellow-600';
        iconColor = 'text-yellow-600';
    } else {
        barColor = 'bg-red-500';
        textColor = 'text-red-600';
        iconColor = 'text-red-600';
    }
    
    batteryBar.className = `h-3 rounded-full transition-all duration-300 ${barColor}`;
    document.getElementById('battery-percentage').className = `text-4xl font-bold ${textColor}`;
    document.getElementById('battery-status').className = `mt-1 text-xl font-semibold ${textColor}`;
    
    // 更新电池图标
    const batteryIcon = document.getElementById('battery-icon');
    batteryIcon.className = `w-8 h-8 ${iconColor}`;
    
    // 根据充电状态和电量显示不同图标
    if (isCharging) {
        batteryIcon.innerHTML = `
            <path d="M3.5 6A1.5 1.5 0 015 4.5h7A1.5 1.5 0 0113.5 6v12a1.5 1.5 0 01-1.5 1.5H5A1.5 1.5 0 013.5 18V6z"/>
            <path d="M7 12l3-3v3h2l-3 3v-3H7z" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"/>
            <path d="M15.5 10v4M17.5 10v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        `;
    } else {
        // 根据电量显示不同的电池图标
        const level = percentage > 75 ? 'full' : percentage > 50 ? 'high' : percentage > 25 ? 'medium' : 'low';
        batteryIcon.innerHTML = getBatteryIconSVG(level);
    }
}

// 获取电池图标SVG
function getBatteryIconSVG(level) {
    const base = '<rect x="2" y="6" width="18" height="12" rx="1" stroke="currentColor" fill="none" stroke-width="1.5"/><rect x="20" y="9" width="2" height="6" rx="1" fill="currentColor"/>';
    
    let fill = '';
    switch(level) {
        case 'full':
            fill = '<rect x="4" y="8" width="14" height="8" rx="0.5" fill="currentColor"/>';
            break;
        case 'high':
            fill = '<rect x="4" y="8" width="10" height="8" rx="0.5" fill="currentColor"/>';
            break;
        case 'medium':
            fill = '<rect x="4" y="8" width="7" height="8" rx="0.5" fill="currentColor"/>';
            break;
        case 'low':
            fill = '<rect x="4" y="8" width="4" height="8" rx="0.5" fill="currentColor"/>';
            break;
    }
    
    return base + fill;
}

// 加载应用时间轴
async function loadAppTimeline() {
    try {
        const response = await fetch(`../api/stats.php?action=app_timeline&device_id=${deviceId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            appTimeline = result.data.timeline || [];
            renderAppTimeline();
        } else {
            console.error('加载时间轴失败:', result.error);
            showTimelineError(result.error || '加载失败');
        }
    } catch (error) {
        console.error('加载应用时间轴失败:', error);
        showTimelineError('网络请求失败');
    }
}

// 渲染应用时间轴
function renderAppTimeline() {
    const container = document.getElementById('app-timeline');
    
    if (!container) {
        console.error('找不到app-timeline容器');
        return;
    }
    
    if (appTimeline.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-gray-500">暂无应用切换记录</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = appTimeline.map((item, index) => {
        const isCurrent = item.is_current === true || index === 0;
        const startTime = new Date(item.start_time);
        const duration = calculateDuration(item.start_time, isCurrent ? null : item.end_time);
        const durationFormatted = formatDurationWithSeconds(duration);
        
        // 格式化时间显示
        const timeStr = formatTimeString(startTime);
        
        return `
            <div class="timeline-item ${isCurrent ? 'current' : ''}" data-index="${index}">
                <div class="timeline-dot"></div>
                <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2">
                                <h4 class="text-sm font-semibold text-gray-900 truncate">${escapeHtml(item.app_name)}</h4>
                                ${isCurrent ? '<span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-600 rounded">当前</span>' : ''}
                            </div>
                            <p class="text-xs text-gray-600 mt-1 truncate" title="${escapeHtml(item.window_title)}">${escapeHtml(item.window_title)}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center text-gray-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>${timeStr}</span>
                        </div>
                        <div class="timeline-duration font-semibold ${isCurrent ? 'text-blue-600' : 'text-gray-700'}" data-start="${item.start_time}" data-end="${isCurrent ? '' : item.end_time}" data-is-current="${isCurrent}">
                            ${durationFormatted}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// 显示时间轴错误
function showTimelineError(message) {
    const container = document.getElementById('app-timeline');
    if (container) {
        container.innerHTML = `
            <div class="text-center py-8">
                <p class="text-gray-500">${escapeHtml(message)}</p>
            </div>
        `;
    }
}

// 启动时间轴实时更新
function startTimelineUpdate() {
    // 每秒更新一次停留时间
    timelineUpdateInterval = setInterval(function() {
        updateTimelineDurations();
    }, 1000);
}

// 停止时间轴更新
function stopTimelineUpdate() {
    if (timelineUpdateInterval) {
        clearInterval(timelineUpdateInterval);
        timelineUpdateInterval = null;
    }
}

// 更新时间轴中的停留时间（实时更新）
function updateTimelineDurations() {
    const durationElements = document.querySelectorAll('.timeline-duration');
    
    durationElements.forEach((el, index) => {
        const startTime = el.getAttribute('data-start');
        const endTime = el.getAttribute('data-end');
        const isCurrent = el.getAttribute('data-is-current') === 'true';
        
        if (startTime) {
            // 对于当前应用，使用当前时间作为结束时间
            const duration = calculateDuration(startTime, isCurrent ? null : endTime);
            el.textContent = formatDurationWithSeconds(duration);
        }
    });
}

// 计算持续时间（秒）
function calculateDuration(startTime, endTime) {
    const start = new Date(startTime);
    const end = endTime ? new Date(endTime) : new Date();
    return Math.floor((end - start) / 1000);
}

// 格式化时间字符串（显示具体时间）
function formatTimeString(date) {
    const now = new Date();
    const diff = now - date;
    const diffHours = diff / (1000 * 60 * 60);
    
    // 如果是今天，只显示时间
    if (diffHours < 24 && now.getDate() === date.getDate()) {
        return date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    
    // 否则显示日期和时间
    return date.toLocaleString('zh-CN', { 
        month: '2-digit', 
        day: '2-digit', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

// 格式化持续时间（包含秒，精确显示）
function formatDurationWithSeconds(seconds) {
    if (seconds < 0) seconds = 0;
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    const parts = [];
    if (hours > 0) parts.push(`${hours}小时`);
    if (minutes > 0 || hours > 0) parts.push(`${minutes}分`);
    parts.push(`${secs}秒`);
    
    return parts.join(' ');
}

// 加载当前媒体播放状态
async function loadCurrentMedia() {
    try {
        const response = await fetch(`../api/stats.php?action=current_media&device_id=${deviceId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            renderMediaPlayback(result.data);
        } else {
            // 没有媒体数据，隐藏卡片
            hideMediaPlayback();
        }
    } catch (error) {
        console.error('加载媒体播放状态失败:', error);
        hideMediaPlayback();
    }
}

// 渲染媒体播放状态
function renderMediaPlayback(media) {
    const card = document.getElementById('media-playback-card');
    
    if (!card) {
        return;
    }
    
    // 显示卡片
    card.classList.remove('hidden');
    
    // 更新标题
    document.getElementById('media-title').textContent = media.title || '-';
    
    // 更新艺术家/专辑
    const artistText = media.artist ? 
        (media.album ? `${media.artist} - ${media.album}` : media.artist) : 
        (media.album || '-');
    document.getElementById('media-artist').textContent = artistText;
    
    // 更新媒体类型标签
    const typeLabel = media.media_type === 'Music' ? '音乐' : '视频';
    document.getElementById('media-type-badge').textContent = typeLabel;
    
    // 更新播放状态标签
    const statusBadge = document.getElementById('media-status-badge');
    if (media.playback_status === 'Playing') {
        statusBadge.textContent = '播放中';
        statusBadge.className = 'px-2 py-1 bg-green-100 text-green-600 rounded';
        document.getElementById('media-status-title').textContent = '正在播放';
    } else if (media.playback_status === 'Paused') {
        statusBadge.textContent = '已暂停';
        statusBadge.className = 'px-2 py-1 bg-yellow-100 text-yellow-600 rounded';
        document.getElementById('media-status-title').textContent = '已暂停';
    } else {
        statusBadge.textContent = media.playback_status || '未知';
        statusBadge.className = 'px-2 py-1 bg-gray-100 text-gray-600 rounded';
    }
    
    // 更新缩略图
    const thumbnailContainer = document.getElementById('media-thumbnail-container');
    const thumbnail = document.getElementById('media-thumbnail');
    if (media.thumbnail) {
        thumbnail.src = 'data:image/jpeg;base64,' + media.thumbnail;
        thumbnailContainer.classList.remove('hidden');
    } else {
        thumbnailContainer.classList.add('hidden');
    }
    
    // 更新进度条
    const progressContainer = document.getElementById('media-progress-container');
    if (media.duration && media.position !== null && media.position !== undefined) {
        progressContainer.classList.remove('hidden');
        
        const progress = (media.position / media.duration) * 100;
        document.getElementById('media-progress-bar').style.width = progress + '%';
        
        document.getElementById('media-current-time').textContent = formatMediaTime(media.position);
        document.getElementById('media-total-time').textContent = formatMediaTime(media.duration);
    } else {
        progressContainer.classList.add('hidden');
    }
}

// 隐藏媒体播放卡片
function hideMediaPlayback() {
    const card = document.getElementById('media-playback-card');
    if (card) {
        card.classList.add('hidden');
    }
}

// 格式化媒体时间（秒转为 mm:ss 或 hh:mm:ss）
function formatMediaTime(seconds) {
    if (!seconds || seconds < 0) return '0:00';
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    } else {
        return `${minutes}:${String(secs).padStart(2, '0')}`;
    }
}

