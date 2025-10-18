# Watch Me Do - 远程监控接收端

这是 Watch Me Do 应用的远程监控接收端，基于 PHP 开发，用于接收、存储和展示来自客户端的监控数据。

## 功能特性

### 核心功能
- ✅ 接收客户端推送的系统监控数据
- ✅ 支持多设备管理，每个设备独立Token鉴权
- ✅ 自动检测设备在线状态
- ✅ 数据可视化展示（ECharts图表）
- ✅ 历史数据查询和分析
- ✅ AI智能分析（支持OpenAI API标准）

### 数据展示
- 📊 应用使用时间饼图
- 📈 24小时使用时间柱状图
- 🔝 最常用应用排行
- ⏰ 最常用时间段分析
- 💻 实时系统资源监控（CPU、内存、磁盘、网络）
- 📱 进程详细信息（CPU和内存使用率）

### 管理功能
- 🔐 管理后台密码保护
- 🔧 设备管理（添加、删除、Token管理）
- ⚙️ 系统设置（数据库配置、在线阈值）
- 🤖 AI配置（模型、API地址、密钥）
- 🧹 数据清理和维护

## 系统要求

### 必需
- PHP >= 7.4
- PDO扩展
- PDO SQLite驱动（使用SQLite时）
- PDO MySQL驱动（使用MySQL时）
- JSON扩展
- cURL扩展

### 推荐
- Apache或Nginx服务器
- PHP-FPM（生产环境）
- 至少100MB磁盘空间

## 安装步骤

### 1. 上传文件

将 `client` 目录上传到您的Web服务器：

```bash
# 上传到服务器根目录
/var/www/html/watchmedo/

# 或使用FTP上传
```

### 2. 设置权限

确保以下目录可写：

```bash
chmod 755 client/
chmod 755 client/includes/
chmod 755 client/data/
```

### 3. 访问安装向导

在浏览器中访问：

```
http://your-domain.com/watchmedo/install/setup.php
```

按照向导完成安装：

1. **环境检测** - 自动检测PHP环境和必需扩展
2. **数据库配置** - 选择SQLite或MySQL
3. **管理员密码** - 设置管理后台登录密码
4. **完成安装** - 自动创建数据库表和配置文件

### 4. 删除安装目录（重要！）

安装完成后，请删除 `install` 目录以确保安全：

```bash
rm -rf client/install/
```

## 使用指南

### 添加设备

1. 访问管理后台：`http://your-domain.com/watchmedo/admin/`
2. 使用安装时设置的密码登录
3. 点击"添加设备"按钮
4. 输入设备名称，系统会自动生成Token
5. 复制Token并配置到客户端应用

### 配置客户端

在客户端应用的设置页面：

1. 启用"远程推送"
2. 填写远程URL：`http://your-domain.com/watchmedo/api/receive.php`
3. 粘贴从管理后台获取的Token
4. 设置推送间隔（建议60秒）
5. 保存并启动远程推送

### 查看数据

访问前台页面：`http://your-domain.com/watchmedo/public/`

无需登录即可查看所有设备的监控数据：

- 设备列表和在线状态
- 点击设备卡片查看详细信息
- 使用日期选择器查看历史数据
- 生成AI分析报告（需配置AI）

## API文档

### 接收数据端点

**URL:** `/api/receive.php`  
**方法:** POST  
**认证:** Token（Header: `X-Device-Token` 或 POST参数）

**请求示例:**

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
  "disks": [...],
  "network": [...]
}
```

**响应示例:**

```json
{
  "success": true,
  "message": "数据接收成功",
  "data": {
    "device_id": 1,
    "timestamp": "2024-01-01 12:00:00"
  }
}
```

### 其他API端点

- `/api/devices.php` - 设备管理
- `/api/stats.php` - 统计数据查询
- `/api/ai.php` - AI分析
- `/api/admin.php` - 管理后台操作

详细API文档请参考各文件的注释说明。

## 配置说明

### 数据库配置

编辑 `includes/config.php`：

```php
// SQLite（默认）
define('DB_TYPE', 'sqlite');
define('SQLITE_DB_PATH', __DIR__ . '/../data/watchmedo.db');

// 或使用MySQL
define('DB_TYPE', 'mysql');
define('MYSQL_HOST', 'localhost');
define('MYSQL_DATABASE', 'watchmedo');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', 'your_password');
```

### 在线检测阈值

设备超过此时间未上报将被标记为离线：

```php
define('DEVICE_ONLINE_THRESHOLD', 300); // 300秒 = 5分钟
```

### AI配置

启用AI分析功能：

```php
define('AI_ENABLED', true);
define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('AI_MODEL', 'gpt-3.5-turbo');
define('AI_API_KEY', 'your_api_key');
```

也可以在管理后台的"AI配置"页面进行设置。

## 目录结构

```
client/
├── api/                    # API接口
│   ├── receive.php        # 数据接收端点
│   ├── devices.php        # 设备管理API
│   ├── stats.php          # 统计数据API
│   ├── ai.php             # AI分析API
│   └── admin.php          # 管理后台API
├── admin/                  # 管理后台
│   └── index.php          # 管理界面
├── public/                 # 公开访问前端
│   ├── index.php          # 设备列表页
│   ├── device.php         # 设备详情页
│   └── assets/            # 静态资源
├── includes/               # 核心代码
│   ├── config.php         # 配置文件
│   ├── database.php       # 数据库类
│   ├── auth.php           # 认证类
│   └── functions.php      # 工具函数
├── data/                   # 数据目录（SQLite）
├── install/                # 安装向导
│   └── setup.php
└── README.md              # 本文档
```

## 数据库表结构

### devices - 设备表
- id, name, token, computer_name
- created_at, last_seen_at, is_online, online_threshold

### device_stats - 设备统计
- id, device_id, timestamp
- computer_name, uptime, cpu_usage_avg
- memory_total, memory_used, memory_percent

### process_records - 进程记录
- id, device_id, timestamp
- executable_name, window_title
- cpu_usage, memory_usage, is_focused

### disk_stats - 磁盘统计
- id, device_id, timestamp
- name, mount_point, total_space, available_space

### network_stats - 网络统计
- id, device_id, timestamp
- interface_name, received, transmitted

### settings - 系统设置
- key, value

## 维护和优化

### 数据清理

在管理后台的"维护"页面可以清理旧数据：

1. 设置保留天数（例如30天）
2. 点击"执行清理"
3. 系统会删除指定天数之前的历史数据

### 性能优化

**对于大量数据：**

1. 定期清理历史数据
2. 为数据库添加索引（已自动创建）
3. 使用MySQL而不是SQLite
4. 启用PHP OPcache
5. 使用CDN加载静态资源

**推送间隔建议：**

- 测试环境：30-60秒
- 生产环境：60-300秒
- 低配服务器：300-600秒

## 安全建议

1. **更改管理员密码**  
   安装后立即在管理后台更改密码

2. **保护Token**  
   不要将设备Token泄露给他人

3. **HTTPS**  
   生产环境建议使用HTTPS加密传输

4. **限制访问**  
   可通过 .htaccess 限制管理后台IP访问

5. **定期备份**  
   定期备份数据库和配置文件

## 故障排除

### 无法接收数据

1. 检查Token是否正确
2. 检查URL是否可访问
3. 查看PHP错误日志
4. 确认数据库连接正常

### 设备显示离线

1. 检查客户端是否正常推送
2. 调整在线检测阈值
3. 确认时区设置正确

### AI功能不工作

1. 确认AI已启用
2. 检查API密钥是否正确
3. 确认网络可访问API地址
4. 查看错误日志

### 性能问题

1. 清理旧数据
2. 优化数据库（MySQL）
3. 增加推送间隔
4. 升级服务器配置

## 技术栈

- **后端:** PHP 7.4+
- **数据库:** SQLite 3 / MySQL 5.7+
- **前端:** HTML5, TailwindCSS, JavaScript
- **图表:** ECharts 5.4
- **AI:** OpenAI API标准

## 许可证

本项目遵循 MIT 许可证。

## 支持

如有问题或建议，请联系开发者或提交Issue。

## 更新日志

### v1.0.0 (2024-10-18)
- 初始版本发布
- 完整的设备监控功能
- 数据可视化展示
- AI智能分析
- 管理后台

---

**感谢使用 Watch Me Do！** 🎉

