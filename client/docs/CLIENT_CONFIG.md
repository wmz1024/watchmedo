# 客户端配置指南

本文档说明如何在 WatchMeDo 客户端应用中配置远程推送功能。

## 准备工作

### 1. 获取设备Token

1. 访问管理后台：`http://your-domain.com/watchmedo/admin/`
2. 使用管理员密码登录
3. 在"设备管理"页面点击"添加设备"
4. 输入设备名称（例如：我的工作电脑）
5. 系统会生成一个唯一的Token，请复制保存

### 2. 确定API地址

远程推送的API地址格式为：

```
http://your-domain.com/watchmedo/api/receive.php
```

或使用HTTPS（推荐）：

```
https://your-domain.com/watchmedo/api/receive.php
```

## 客户端配置步骤

### 方法1：通过设置界面配置

1. 打开 WatchMeDo 客户端应用
2. 进入"设置"页面（Settings）
3. 找到"远程推送"（Remote Push）部分
4. 填写以下信息：

   - **启用远程推送**: 勾选启用
   - **远程URL**: 填入完整的API地址
   - **推送间隔**: 建议设置为 60 秒（可根据需要调整）

5. 点击"保存"按钮保存设置
6. 点击"启动远程推送"按钮开始推送

### 方法2：编辑配置文件

如果需要直接编辑配置，可以修改应用的配置文件。

## 客户端推送数据格式

客户端应按照以下JSON格式推送数据：

```json
{
  "computer_name": "MyComputer",
  "uptime": 3600,
  "cpu_usage": [25.5, 30.2, 15.8, 40.1],
  "memory_usage": {
    "total": 17179869184,
    "used": 8589934592,
    "percent": 50.0
  },
  "processes": [
    {
      "executable_name": "chrome.exe",
      "window_title": "Google Chrome",
      "cpu_usage": 15.5,
      "memory": 524288000,
      "is_focused": true,
      "pid": 12345
    }
  ],
  "disks": [
    {
      "name": "C:",
      "mount_point": "C:\\",
      "total_space": 256000000000,
      "available_space": 128000000000
    }
  ],
  "network": [
    {
      "name": "Ethernet",
      "received": 1048576000,
      "transmitted": 524288000
    }
  ]
}
```

## HTTP请求配置

### Headers

必须包含设备Token，有以下几种方式：

**方式1：使用自定义Header（推荐）**
```
X-Device-Token: your_device_token_here
```

**方式2：使用Authorization Header**
```
Authorization: Bearer your_device_token_here
```

**方式3：在POST Body中包含**
```json
{
  "token": "your_device_token_here",
  "computer_name": "...",
  ...
}
```

### 请求方法

- **Method**: POST
- **Content-Type**: application/json
- **Body**: JSON格式的监控数据

## Rust客户端示例代码

在您的 `main.rs` 中，已经实现了远程推送功能。配置方法：

```rust
// 在设置中配置
RemoteSettings {
    enabled: true,
    url: "http://your-domain.com/watchmedo/api/receive.php".to_string(),
    interval_seconds: 60,
}
```

客户端会自动：
1. 每隔指定秒数从本地API获取数据
2. 将数据POST到远程服务器
3. 在HTTP Header中包含Token（需要添加）

### 需要修改的代码

在 `src-tauri/src/main.rs` 的 `start_remote_push` 函数中，需要添加Token Header：

```rust
match client
    .post(&remote_settings.url)
    .header("Content-Type", "application/json")
    .header("X-Device-Token", "YOUR_TOKEN_HERE") // 添加这一行
    .body(json_data)
    .send()
    .await
{
    // ...
}
```

## 推荐配置

### 推送间隔

根据不同场景选择合适的推送间隔：

- **测试环境**: 30-60秒（快速查看数据）
- **日常使用**: 60-120秒（平衡数据及时性和服务器负载）
- **节省流量**: 300-600秒（降低网络使用和服务器压力）

### 网络配置

- **局域网部署**: 使用HTTP即可，速度快
- **公网部署**: 强烈建议使用HTTPS，保护数据安全
- **防火墙**: 确保客户端能访问服务器的80/443端口

### 故障处理

客户端应实现：

1. **重试机制**: 推送失败后自动重试
2. **错误日志**: 记录推送错误信息
3. **状态显示**: 在界面显示推送状态
4. **离线缓存**: 可选，网络恢复后补推数据

## 验证配置

### 1. 测试连接

使用提供的测试脚本验证API是否可访问：

```bash
php test_push.php YOUR_TOKEN
```

### 2. 检查日志

- 查看客户端推送日志
- 检查服务器PHP错误日志
- 在管理后台查看设备在线状态

### 3. 查看数据

1. 访问前台页面：`http://your-domain.com/watchmedo/public/`
2. 查看设备是否显示为在线
3. 点击设备查看详细数据
4. 确认数据更新时间

## 常见问题

### Q: 设备显示离线

**A:** 
1. 检查客户端是否成功推送（查看日志）
2. 验证Token是否正确
3. 确认推送间隔小于在线检测阈值
4. 检查服务器时区设置

### Q: 推送失败

**A:**
1. 验证URL是否正确且可访问
2. 检查Token是否有效
3. 确认网络连接正常
4. 查看服务器错误日志

### Q: 数据不完整

**A:**
1. 检查客户端发送的JSON格式是否正确
2. 确认所有必需字段都已包含
3. 查看API响应中的错误信息

### Q: 性能问题

**A:**
1. 增加推送间隔
2. 减少进程列表数量限制
3. 考虑使用更高性能的服务器
4. 定期清理历史数据

## 安全建议

1. **保护Token**: 不要将Token提交到公开代码仓库
2. **使用HTTPS**: 生产环境必须使用HTTPS加密
3. **限制访问**: 配置防火墙只允许特定IP访问
4. **定期更换**: 定期重新生成设备Token
5. **监控异常**: 监控异常的推送频率和数据量

## 支持

如有问题，请参考：

- 服务器端 README.md
- API文档
- 管理后台的帮助信息

---

配置完成后，您就可以在任何地方通过Web界面查看设备的实时监控数据了！ 🎉

