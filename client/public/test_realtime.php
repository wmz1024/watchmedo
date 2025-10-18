<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>测试实时数据API</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>实时数据API测试</h1>
    
    <div>
        <label>设备ID: <input type="text" id="deviceId" value="1" style="width: 100px;"></label>
        <button onclick="testAPI()">测试API</button>
    </div>
    
    <h2>API响应:</h2>
    <pre id="response">等待测试...</pre>
    
    <script>
        // 格式化时间（包含秒）
        function formatDurationTest(seconds) {
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
        
        async function testAPI() {
            const deviceId = document.getElementById('deviceId').value;
            const responseEl = document.getElementById('response');
            
            try {
                responseEl.textContent = '加载中...';
                
                const response = await fetch(`../api/stats.php?action=realtime&device_id=${deviceId}`);
                const result = await response.json();
                
                responseEl.innerHTML = '<span class="success">✓ API调用成功</span>\n\n' + 
                    JSON.stringify(result, null, 2);
                
                // 分析数据
                if (result.success && result.data) {
                    const data = result.data;
                    responseEl.innerHTML += '\n\n=== 数据分析 ===\n';
                    responseEl.innerHTML += `进程数量: ${data.processes ? data.processes.length : 0}\n`;
                    responseEl.innerHTML += `网络接口数量: ${data.network ? data.network.length : 0}\n`;
                    responseEl.innerHTML += `磁盘数量: ${data.disks ? data.disks.length : 0}\n`;
                    
                    if (data.processes && data.processes.length > 0) {
                        const focusedApps = data.processes.filter(p => p.is_focused);
                        responseEl.innerHTML += `\n聚焦的应用数量: ${focusedApps.length}\n`;
                        if (focusedApps.length > 0) {
                            responseEl.innerHTML += `聚焦应用: ${focusedApps[0].name}\n`;
                            responseEl.innerHTML += `窗口标题: ${focusedApps[0].window_title}\n`;
                            responseEl.innerHTML += `连续停留秒数: ${focusedApps[0].focused_duration}秒\n`;
                            responseEl.innerHTML += `PHP格式化显示: ${focusedApps[0].focused_duration_formatted}\n`;
                            responseEl.innerHTML += `前端格式化显示: ${formatDurationTest(focusedApps[0].focused_duration)}\n`;
                        }
                    }
                }
                
            } catch (error) {
                responseEl.innerHTML = '<span class="error">✗ 请求失败</span>\n\n' + error.toString();
            }
        }
        
        // 页面加载时自动测试
        window.addEventListener('DOMContentLoaded', testAPI);
    </script>
</body>
</html>

