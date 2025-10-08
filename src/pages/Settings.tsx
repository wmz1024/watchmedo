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
      
      setHttpPort(httpSettings.port);
      setHttpRunning(httpSettings.is_running);
      setShareSettings(share);
      setAppSettings(app);
      setPreviewUrl(`http://localhost:${httpSettings.port}/api/system`);
    } catch (error) {
      console.error("Failed to load settings:", error);
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
        </CardContent>
      </Card>
    </div>
  );
}

