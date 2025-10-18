import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Info, Github, Mail, Globe, Heart, Code, Cpu } from "lucide-react";

export function About() {
  const appInfo = {
    name: "Watch Me Do",
    description: "在线视奸喵喵喵 - 远程监控与系统信息查看工具",
    author: "MZCompute GmbH.",
    license: "MIT",
    repository: "https://github.com/wmz1024/watchmedo",
    email: "gmbh@mingze.de",
    website: "https://www.sk.ci"
  };

  const features = [
    { icon: Cpu, title: "系统监控", description: "实时监控CPU、内存、硬盘和网络使用情况" },
    { icon: Code, title: "进程管理", description: "查看正在运行的应用程序及其资源占用" },
    { icon: Globe, title: "远程推送", description: "将系统信息推送到远程服务器" },
    { icon: Info, title: "详细信息", description: "提供全面的系统信息和性能指标" }
  ];

  const techStack = [
    { name: "Tauri", description: "跨平台桌面应用框架", version: "1.6.0" },
    { name: "React", description: "用户界面库", version: "18.2.0" },
    { name: "TypeScript", description: "类型安全的JavaScript", version: "5.0.2" },
    { name: "Tailwind CSS", description: "实用优先的CSS框架", version: "3.4.1" },
    { name: "Rust", description: "系统后端语言", version: "Latest" }
  ];

  return (
    <div className="space-y-6">
      {/* 应用标题 */}
      <div className="text-center space-y-4">
        <div>
          <h1 className="text-4xl font-bold tracking-tight">{appInfo.name}</h1>
          <p className="text-muted-foreground mt-2">{appInfo.description}</p>
        </div>
      </div>

      <Separator />

      {/* 主要功能 */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Info className="h-5 w-5" />
            主要功能
          </CardTitle>
          <CardDescription>
            Watch Me Do 提供的核心功能和特性
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-2">
            {features.map((feature, index) => (
              <div key={index} className="flex gap-3 p-3 rounded-lg border">
                <div className="flex-shrink-0">
                  <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                    <feature.icon className="h-5 w-5 text-primary" />
                  </div>
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="font-semibold text-sm">{feature.title}</h3>
                  <p className="text-sm text-muted-foreground mt-1">{feature.description}</p>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* 技术栈 */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Code className="h-5 w-5" />
            技术栈
          </CardTitle>
          <CardDescription>
            本应用使用的核心技术和框架
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {techStack.map((tech, index) => (
              <div key={index} className="flex items-center justify-between p-3 rounded-lg border">
                <div className="flex-1 min-w-0">
                  <h3 className="font-semibold text-sm">{tech.name}</h3>
                  <p className="text-xs text-muted-foreground mt-1">{tech.description}</p>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* 联系信息 */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Mail className="h-5 w-5" />
            联系我们
          </CardTitle>
          <CardDescription>
            如有问题或建议，欢迎联系我们
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            <a
              href={appInfo.repository}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 p-3 rounded-lg border hover:bg-accent transition-colors cursor-pointer"
            >
              <Github className="h-5 w-5" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium">GitHub 仓库</p>
                <p className="text-xs text-muted-foreground truncate">{appInfo.repository}</p>
              </div>
            </a>
            <a
              href={`mailto:${appInfo.email}`}
              className="flex items-center gap-3 p-3 rounded-lg border hover:bg-accent transition-colors cursor-pointer"
            >
              <Mail className="h-5 w-5" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium">电子邮件</p>
                <p className="text-xs text-muted-foreground">{appInfo.email}</p>
              </div>
            </a>
            <a
              href={appInfo.website}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 p-3 rounded-lg border hover:bg-accent transition-colors cursor-pointer"
            >
              <Globe className="h-5 w-5" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium">官方网站</p>
                <p className="text-xs text-muted-foreground">{appInfo.website}</p>
              </div>
            </a>
          </div>
        </CardContent>
      </Card>

      {/* 许可证和版权 */}
      <Card>
        <CardContent className="pt-6">
          <div className="text-center space-y-2">
            <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
              <span>Made with</span>
              <Heart className="h-4 w-4 text-red-500 fill-red-500" />
              <span>by {appInfo.author}</span>
            </div>
            <p className="text-xs text-muted-foreground">
              © 2024 Watchmedo. All rights reserved.
            </p>
            <p className="text-xs text-muted-foreground">
              Licensed under {appInfo.license} License
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}


