import { useState, useEffect } from "react";
import { invoke } from "@tauri-apps/api/tauri";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { toast } from "sonner";

interface RemoteSettings {
  enabled: boolean;
  url: string;
  interval_seconds: number;
}

export function Remote() {
  const [settings, setSettings] = useState<RemoteSettings>({
    enabled: false,
    url: "",
    interval_seconds: 60,
  });
  const [lastPushTime, setLastPushTime] = useState<string>("");
  const [pushStatus, setPushStatus] = useState<string>("未推送");

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      const remoteSettings = await invoke<RemoteSettings>("get_remote_settings");
      setSettings(remoteSettings);
      
      // 获取上次推送时间
      try {
        const lastTime = await invoke<string>("get_last_push_time");
        setLastPushTime(lastTime);
      } catch (e) {
        console.log("No last push time available");
      }
    } catch (error) {
      console.error("Failed to load remote settings:", error);
    }
  };

  const handleSaveSettings = async () => {
    try {
      await invoke("set_remote_settings", { settings });
      toast.success("远程推送设置已保存");
    } catch (error) {
      toast.error("保存设置失败");
      console.error(error);
    }
  };

  const toggleRemotePush = async (enabled: boolean) => {
    const newSettings = { ...settings, enabled };
    setSettings(newSettings);
    
    try {
      await invoke("set_remote_settings", { settings: newSettings });
      
      if (enabled) {
        await invoke("start_remote_push");
        toast.success("远程推送已启动");
        setPushStatus("运行中");
      } else {
        await invoke("stop_remote_push");
        toast.success("远程推送已停止");
        setPushStatus("已停止");
      }
    } catch (error) {
      toast.error("切换远程推送状态失败");
      console.error(error);
    }
  };

  const handleTestPush = async () => {
    if (!settings.url) {
      toast.error("请先设置推送URL");
      return;
    }

    try {
      toast.info("正在测试推送...");
      await invoke("test_remote_push");
      toast.success("测试推送成功！");
      loadSettings(); // 刷新上次推送时间
    } catch (error: any) {
      toast.error(`测试推送失败: ${error}`);
      console.error(error);
    }
  };

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>远程推送设置</CardTitle>
          <CardDescription>配置自动推送系统监控数据到远程服务器</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="url">推送URL</Label>
            <Input
              id="url"
              type="url"
              value={settings.url}
              onChange={(e) => setSettings({ ...settings, url: e.target.value })}
              placeholder="https://example.com/api/monitor"
            />
            <p className="text-xs text-muted-foreground">
              系统监控数据将通过POST请求发送到此URL
            </p>
          </div>

          <Separator />

          <div className="space-y-2">
            <Label htmlFor="interval">推送间隔（秒）</Label>
            <Input
              id="interval"
              type="number"
              min="10"
              max="3600"
              value={settings.interval_seconds}
              onChange={(e) => setSettings({ ...settings, interval_seconds: parseInt(e.target.value) || 60 })}
            />
            <p className="text-xs text-muted-foreground">
              每隔多少秒自动推送一次数据（最小10秒，最大3600秒）
            </p>
          </div>

          <Separator />

          <div className="flex items-center justify-between">
            <Button onClick={handleSaveSettings}>
              保存设置
            </Button>
            <Button onClick={handleTestPush} variant="outline">
              测试推送
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>推送状态</CardTitle>
          <CardDescription>管理自动推送功能</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>自动推送</Label>
              <p className="text-sm text-muted-foreground">
                {settings.enabled ? "推送功能已启用" : "推送功能已禁用"}
              </p>
            </div>
            <div className="flex items-center space-x-2">
              <Badge variant={settings.enabled ? "default" : "secondary"}>
                {settings.enabled ? "运行中" : "已停止"}
              </Badge>
              <Switch
                checked={settings.enabled}
                onCheckedChange={toggleRemotePush}
              />
            </div>
          </div>

          <Separator />

          <div className="space-y-2">
            <Label>上次推送时间</Label>
            <p className="text-sm text-muted-foreground">
              {lastPushTime || "暂无推送记录"}
            </p>
          </div>

          <Separator />

          <div className="space-y-2">
            <Label>推送数据格式</Label>
            <div className="rounded-lg bg-muted p-4">
              <pre className="text-xs overflow-x-auto">
{`{
  "system_info": { ... },
  "processes": [ ... ],
  "disks": [ ... ],
  "network": { ... }
}`}
              </pre>
            </div>
            <p className="text-xs text-muted-foreground">
              数据格式与HTTP API返回的JSON格式相同
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

