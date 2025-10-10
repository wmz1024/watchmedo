# Watch Me Do

在线视奸👁我的电脑 服务端(on your PC)&客户端(Web)

Rust编写 探针

## 客户端详情

```
client 客户端
├── api API目录
│   ├── proxy.php 通过Curl获取资源(proxy)(API地址其中之一)
│   ├── pull.php 搭配push.php使用 可设置中转数据超时时间(API地址其中之一)
│   └── push.php 可接受post请求 搭配Watchmedo的服务端的远程功能使用
└── index.html 前端使用（API可设置为watchmego服务端http或者上述API地址）
```