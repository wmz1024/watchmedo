import { useState, useEffect } from "react";
import { invoke } from "@tauri-apps/api/tauri";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Separator } from "@/components/ui/separator";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";

interface HttpSettings {
  port: number;
  is_running: boolean;
}

interface ShareSettings {
  share_computer_name: boolean;
  share_uptime: boolean;
  share_cpu_usage: boolean;
  share_memory_usage: boolean;
  share_processes: boolean;
  share_disks: boolean;
  share_network: boolean;
}

interface AppSettings {
  auto_start_http: boolean;
  auto_launch: boolean;
  silent_launch: boolean;
  process_limit: number;
}

interface MediaSettings {
  enabled: boolean;
  send_thumbnail: boolean;
  compress_thumbnail: boolean;
  thumbnail_max_size_kb: number;
}

export function Settings() {
  const [httpPort, setHttpPort] = useState<number>(21536);
  const [httpRunning, setHttpRunning] = useState<boolean>(false);
  const [shareSettings, setShareSettings] = useState<ShareSettings>({
    share_computer_name: true,
    share_uptime: true,
    share_cpu_usage: true,
    share_memory_usage: true,
    share_processes: true,
    share_disks: true,
    share_network: true,
  });
  const [appSettings, setAppSettings] = useState<AppSettings>({
    auto_start_http: true,
    auto_launch: false,
    silent_launch: false,
    process_limit: 20,
  });
  const [mediaSettings, setMediaSettings] = useState<MediaSettings>({
    enabled: true,
    send_thumbnail: false,
    compress_thumbnail: true,
    thumbnail_max_size_kb: 16,
  });
  const [previewUrl, setPreviewUrl] = useState<string>("");

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      const httpSettings = await invoke<HttpSettings>("get_http_settings");
      const share = await invoke<ShareSettings>("get_share_settings");
      const app = await invoke<AppSettings>("get_app_settings");
      const media = await invoke<MediaSettings>("get_media_settings");
      
      setHttpPort(httpSettings.port);
      setHttpRunning(httpSettings.is_running);
      setShareSettings(share);
      setAppSettings(app);
      setMediaSettings(media);
      setPreviewUrl(`http://localhost:${httpSettings.port}/api/system`);
    } catch (error) {
      console.error("Failed to load settings:", error);
    }
  };

  const handleMediaSettingChange = async (key: keyof MediaSettings, value: boolean | number) => {
    const newSettings = { ...mediaSettings, [key]: value };
    setMediaSettings(newSettings);
    
    try {
      await invoke("set_media_settings", { settings: newSettings });
      toast.success("媒体监控设置已更新");
    } catch (error) {
      toast.error("更新媒体监控设置失败");
      console.error(error);
    }
  };

  const handlePortChange = async () => {
    try {
      await invoke("set_http_port", { port: httpPort });
      toast.success("端口设置已保存");
      setPreviewUrl(`http://localhost:${httpPort}/api/system`);
    } catch (error) {
      toast.error("设置端口失败");
      console.error(error);
    }
  };

  const toggleHttpServer = async () => {
    try {
      if (httpRunning) {
        await invoke("stop_http_server");
        setHttpRunning(false);
        toast.success("HTTP服务器已停止");
      } else {
        await invoke("start_http_server");
        setHttpRunning(true);
        toast.success("HTTP服务器已启动");
      }
    } catch (error) {
      toast.error("切换HTTP服务器状态失败");
      console.error(error);
    }
  };

  const handleShareSettingChange = async (key: keyof ShareSettings, value: boolean) => {
    const newSettings = { ...shareSettings, [key]: value };
    setShareSettings(newSettings);
    
    try {
      await invoke("set_share_settings", { settings: newSettings });
      toast.success("共享设置已更新");
    } catch (error) {
      toast.error("更新共享设置失败");
      console.error(error);
    }
  };

  const openPreview = () => {
    window.open(previewUrl, '_blank');
  };

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>HTTP服务器设置</CardTitle>
          <CardDescription>配置HTTP服务器端口并管理服务状态</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center space-x-4">
            <div className="flex-1 space-y-2">
              <Label htmlFor="port">服务端口</Label>
              <Input
                id="port"
                type="number"
                value={httpPort}
                onChange={(e) => setHttpPort(parseInt(e.target.value))}
                placeholder="8080"
              />
            </div>
            <Button onClick={handlePortChange} className="mt-8">
              保存端口
            </Button>
          </div>
          
          <Separator />
          
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>服务器状态</Label>
              <p className="text-sm text-muted-foreground">
                {httpRunning ? "服务器正在运行" : "服务器已停止"}
              </p>
            </div>
            <div className="flex items-center space-x-2">
              <Badge variant={httpRunning ? "default" : "secondary"}>
                {httpRunning ? "运行中" : "已停止"}
              </Badge>
              <Button onClick={toggleHttpServer} variant={httpRunning ? "destructive" : "default"}>
                {httpRunning ? "停止" : "启动"}
              </Button>
            </div>
          </div>

          <Separator />

          <div className="space-y-2">
            <Label>预览地址</Label>
            <div className="flex space-x-2">
              <Input value={previewUrl} readOnly />
              <Button onClick={openPreview} disabled={!httpRunning}>
                预览
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>数据共享设置</CardTitle>
          <CardDescription>选择要通过HTTP共享的数据类型</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>电脑名称</Label>
              <p className="text-sm text-muted-foreground">共享设备名称</p>
            </div>
            <Switch
              checked={shareSettings.share_computer_name}
              onCheckedChange={(checked) => handleShareSettingChange("share_computer_name", checked)}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>运行时间</Label>
              <p className="text-sm text-muted-foreground">系统运行时长</p>
            </div>
            <Switch
              checked={shareSettings.share_uptime}
              onCheckedChange={(checked) => handleShareSettingChange("share_uptime", checked)}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>CPU使用率</Label>
              <p className="text-sm text-muted-foreground">处理器占用情况</p>
            </div>
            <Switch
              checked={shareSettings.share_cpu_usage}
              onCheckedChange={(checked) => handleShareSettingChange("share_cpu_usage", checked)}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>内存使用率</Label>
              <p className="text-sm text-muted-foreground">运行内存占用情况</p>
            </div>
            <Switch
              checked={shareSettings.share_memory_usage}
              onCheckedChange={(checked) => handleShareSettingChange("share_memory_usage", checked)}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>应用进程信息</Label>
              <p className="text-sm text-muted-foreground">正在运行的应用及其资源占用</p>
            </div>
            <Switch
              checked={shareSettings.share_processes}
              onCheckedChange={(checked) => handleShareSettingChange("share_processes", checked)}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>硬盘信息</Label>
              <p className="text-sm text-muted-foreground">硬盘使用情况</p>
            </div>
            <Switch
              checked={shareSettings.share_disks}
              onCheckedChange={(checked) => handleShareSettingChange("share_disks", checked)}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>网络信息</Label>
              <p className="text-sm text-muted-foreground">网络使用情况</p>
            </div>
            <Switch
              checked={shareSettings.share_network}
              onCheckedChange={(checked) => handleShareSettingChange("share_network", checked)}
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>应用设置</CardTitle>
          <CardDescription>配置应用启动选项</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>应用启动后自动开启HTTP服务</Label>
              <p className="text-sm text-muted-foreground">启动应用时自动启动HTTP服务器</p>
            </div>
            <Switch
              checked={appSettings.auto_start_http}
              onCheckedChange={async (checked) => {
                const newSettings = { ...appSettings, auto_start_http: checked };
                setAppSettings(newSettings);
                try {
                  await invoke("set_app_settings", { settings: newSettings });
                  toast.success("应用设置已更新");
                } catch (error) {
                  toast.error("更新应用设置失败");
                  console.error(error);
                }
              }}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>开机自启动</Label>
              <p className="text-sm text-muted-foreground">电脑启动时自动运行此应用</p>
            </div>
            <Switch
              checked={appSettings.auto_launch}
              onCheckedChange={async (checked) => {
                try {
                  await invoke("set_auto_launch", { enable: checked });
                  setAppSettings({ ...appSettings, auto_launch: checked });
                  toast.success(checked ? "已启用开机自启动" : "已禁用开机自启动");
                } catch (error) {
                  toast.error("设置开机自启动失败");
                  console.error(error);
                }
              }}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>静默启动</Label>
              <p className="text-sm text-muted-foreground">开机自启动时隐藏主窗口，仅显示托盘图标</p>
            </div>
            <Switch
              disabled={!appSettings.auto_launch}
              checked={appSettings.silent_launch}
              onCheckedChange={async (checked) => {
                const newSettings = { ...appSettings, silent_launch: checked };
                setAppSettings(newSettings);
                try {
                  await invoke("set_app_settings", { settings: newSettings });
                  // Update auto launch with new silent launch setting
                  if (appSettings.auto_launch) {
                    await invoke("set_auto_launch", { enable: true });
                  }
                  toast.success(checked ? "已启用静默启动" : "已禁用静默启动");
                } catch (error) {
                  toast.error("更新应用设置失败");
                  console.error(error);
                }
              }}
            />
          </div>

          <Separator />

          <div className="space-y-2">
            <Label htmlFor="process-limit">API进程列表数量限制</Label>
            <p className="text-sm text-muted-foreground">
              HTTP API 和远程推送的进程列表将包含 CPU 占用最高的前 N 个进程以及正在聚焦的进程
            </p>
            <div className="flex items-center space-x-4">
              <Input
                id="process-limit"
                type="number"
                min="1"
                max="100"
                value={appSettings.process_limit}
                onChange={(e) => {
                  const value = parseInt(e.target.value) || 20;
                  setAppSettings({ ...appSettings, process_limit: value });
                }}
                className="w-32"
              />
              <Button
                onClick={async () => {
                  try {
                    await invoke("set_app_settings", { settings: appSettings });
                    toast.success("进程数量限制已更新");
                  } catch (error) {
                    toast.error("更新设置失败");
                    console.error(error);
                  }
                }}
              >
                保存
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>媒体播放监控设置</CardTitle>
          <CardDescription>配置媒体播放状态监控功能（仅Windows）</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>启用媒体监控</Label>
              <p className="text-sm text-muted-foreground">监控当前播放的音乐/视频信息</p>
            </div>
            <Switch
              checked={mediaSettings.enabled}
              onCheckedChange={(checked) => handleMediaSettingChange("enabled", checked)}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>发送封面缩略图</Label>
              <p className="text-sm text-muted-foreground">
                上传媒体封面到服务器（会增加网络流量和存储）
              </p>
            </div>
            <Switch
              disabled={!mediaSettings.enabled}
              checked={mediaSettings.send_thumbnail}
              onCheckedChange={(checked) => handleMediaSettingChange("send_thumbnail", checked)}
            />
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>压缩缩略图</Label>
              <p className="text-sm text-muted-foreground">
                压缩图片以减少存储空间和网络流量
              </p>
            </div>
            <Switch
              disabled={!mediaSettings.enabled || !mediaSettings.send_thumbnail}
              checked={mediaSettings.compress_thumbnail}
              onCheckedChange={(checked) => handleMediaSettingChange("compress_thumbnail", checked)}
            />
          </div>

          <Separator />

          <div className="space-y-2">
            <Label htmlFor="thumbnail-size">缩略图最大大小 (KB)</Label>
            <p className="text-sm text-muted-foreground">
              压缩后的图片最大大小，较小的值会降低图片质量但节省空间
            </p>
            <div className="flex items-center space-x-4">
              <Input
                id="thumbnail-size"
                type="number"
                min="4"
                max="64"
                disabled={!mediaSettings.enabled || !mediaSettings.send_thumbnail || !mediaSettings.compress_thumbnail}
                value={mediaSettings.thumbnail_max_size_kb}
                onChange={(e) => {
                  const value = parseInt(e.target.value) || 16;
                  setMediaSettings({ ...mediaSettings, thumbnail_max_size_kb: value });
                }}
                className="w-32"
              />
              <Button
                disabled={!mediaSettings.enabled || !mediaSettings.send_thumbnail || !mediaSettings.compress_thumbnail}
                onClick={async () => {
                  try {
                    await invoke("set_media_settings", { settings: mediaSettings });
                    toast.success("缩略图大小设置已更新");
                  } catch (error) {
                    toast.error("更新设置失败");
                    console.error(error);
                  }
                }}
              >
                保存
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              推荐值：8KB（低质量）、16KB（平衡）、32KB（高质量）
            </p>
          </div>

          <Separator />

          <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950">
            <div className="flex">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">注意事项</h3>
                <div className="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                  <ul className="list-disc list-inside space-y-1">
                    <li>此功能仅在Windows 10/11上可用</li>
                    <li>需要媒体播放器支持Windows Media Control API</li>
                    <li>支持的播放器：Spotify、Chrome、VLC等</li>
                    <li>发送封面会增加网络流量（每次约16KB）</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

