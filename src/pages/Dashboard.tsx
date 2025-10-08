import { useEffect, useState } from "react";
import { invoke } from "@tauri-apps/api/tauri";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";

interface SystemInfo {
  computer_name: string;
  uptime: string;
  cpu_usage: number;
  memory_usage: number;
  memory_total: number;
  memory_used: number;
}

interface ProcessInfo {
  name: string;
  window_title: string;
  pid: number;
  cpu_usage: number;
  memory_usage: number;
  is_focused: boolean;
}

interface DiskInfo {
  name: string;
  mount_point: string;
  total: number;
  used: number;
  usage_percent: number;
}

interface NetworkInterfaceInfo {
  name: string;
  received: number;
  transmitted: number;
  received_rate: number;
  transmitted_rate: number;
}

interface NetworkInfo {
  interfaces: NetworkInterfaceInfo[];
  total_received: number;
  total_transmitted: number;
}

export function Dashboard() {
  const [systemInfo, setSystemInfo] = useState<SystemInfo | null>(null);
  const [processes, setProcesses] = useState<ProcessInfo[]>([]);
  const [disks, setDisks] = useState<DiskInfo[]>([]);
  const [network, setNetwork] = useState<NetworkInfo | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const sysInfo = await invoke<SystemInfo>("get_system_info_dashboard");
        const procInfo = await invoke<ProcessInfo[]>("get_processes");
        const diskInfo = await invoke<DiskInfo[]>("get_disks");
        const netInfo = await invoke<NetworkInfo>("get_network_info_dashboard");
        
        setSystemInfo(sysInfo);
        setProcesses(procInfo);
        setDisks(diskInfo);
        setNetwork(netInfo);
      } catch (error) {
        console.error("Failed to fetch system data:", error);
      }
    };

    fetchData();
    const interval = setInterval(fetchData, 2000); // 每2秒更新一次

    return () => clearInterval(interval);
  }, []);

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
  };

  return (
    <div className="space-y-4">
      {/* 系统信息 */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">电脑名称</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{systemInfo?.computer_name || "加载中..."}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">运行时间</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{systemInfo?.uptime || "0h 0m"}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">CPU使用率</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{systemInfo?.cpu_usage.toFixed(1)}%</div>
            <Progress value={systemInfo?.cpu_usage || 0} className="mt-2" />
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">内存使用率</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{systemInfo?.memory_usage.toFixed(1)}%</div>
            <Progress value={systemInfo?.memory_usage || 0} className="mt-2" />
            <p className="text-xs text-muted-foreground mt-2">
              {formatBytes(systemInfo?.memory_used || 0)} / {formatBytes(systemInfo?.memory_total || 0)}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* 进程列表 */}
      <Card>
        <CardHeader>
          <CardTitle>应用资源占用</CardTitle>
          <CardDescription>正在运行的应用程序及其资源占用情况</CardDescription>
        </CardHeader>
        <CardContent>
          <ScrollArea className="h-[300px]">
            <div className="space-y-4">
              {processes.map((process) => (
                <div
                  key={process.pid}
                  className={`flex items-center justify-between p-3 rounded-lg border ${
                    process.is_focused ? "bg-accent border-accent-foreground" : ""
                  }`}
                >
                  <div className="space-y-1 flex-1 min-w-0">
                    <p className="text-sm font-medium leading-none truncate">
                      {process.is_focused && "🎯 "}
                      {process.window_title}
                    </p>
                    <p className="text-sm text-muted-foreground">
                      {process.name} • PID: {process.pid}
                    </p>
                  </div>
                  <div className="flex items-center gap-4">
                    <div className="text-right">
                      <p className="text-sm font-medium">CPU: {process.cpu_usage.toFixed(1)}%</p>
                      <p className="text-sm text-muted-foreground">内存: {formatBytes(process.memory_usage)}</p>
                    </div>
                    {process.is_focused && (
                      <Badge variant="default">聚焦</Badge>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </ScrollArea>
        </CardContent>
      </Card>

      {/* 硬盘和网络 */}
      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>硬盘使用情况</CardTitle>
          </CardHeader>
          <CardContent>
            <ScrollArea className="h-[250px]">
              <div className="space-y-4">
                {disks.map((disk) => (
                  <div key={disk.mount_point} className="space-y-2">
                    <div className="flex items-center justify-between">
                      <p className="text-sm font-medium">
                        {disk.mount_point} {disk.name && `(${disk.name})`}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        {formatBytes(disk.used)} / {formatBytes(disk.total)}
                      </p>
                    </div>
                    <Progress value={disk.usage_percent} />
                    <p className="text-xs text-muted-foreground">{disk.usage_percent.toFixed(1)}% 已使用</p>
                  </div>
                ))}
              </div>
            </ScrollArea>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>网络使用情况</CardTitle>
            <CardDescription>
              总计: ↓ {formatBytes(network?.total_received || 0)} / ↑ {formatBytes(network?.total_transmitted || 0)}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ScrollArea className="h-[250px]">
              <div className="space-y-4">
                {network?.interfaces.map((iface) => (
                  <div key={iface.name} className="space-y-2 p-3 rounded-lg border">
                    <p className="text-sm font-medium">{iface.name}</p>
                    <div className="grid grid-cols-2 gap-2 text-xs">
                      <div>
                        <p className="text-muted-foreground">下载</p>
                        <p className="font-medium">{formatBytes(iface.received)}</p>
                        <p className="text-green-600">↓ {formatBytes(iface.received_rate)}/s</p>
                      </div>
                      <div>
                        <p className="text-muted-foreground">上传</p>
                        <p className="font-medium">{formatBytes(iface.transmitted)}</p>
                        <p className="text-blue-600">↑ {formatBytes(iface.transmitted_rate)}/s</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </ScrollArea>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

