import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { LayoutDashboard, Settings, Send } from "lucide-react";

interface SidebarProps extends React.HTMLAttributes<HTMLDivElement> {
  onPageChange: (page: "dashboard" | "settings" | "remote") => void;
}

export function Sidebar({ className, onPageChange }: SidebarProps) {
  return (
    <div className={cn("pb-12", className)}>
      <div className="space-y-4 py-4">
        <div className="px-3 py-2">
          <h2 className="mb-2 px-4 text-lg font-semibold tracking-tight">
            Watch Me Do
          </h2>
          <div className="space-y-1">
            <Button
              variant="ghost"
              className="w-full justify-start"
              onClick={() => onPageChange("dashboard")}
            >
              <LayoutDashboard className="mr-2 h-4 w-4" />
              仪表盘
            </Button>
            <Button
              variant="ghost"
              className="w-full justify-start"
              onClick={() => onPageChange("remote")}
            >
              <Send className="mr-2 h-4 w-4" />
              远程推送
            </Button>
            <Button
              variant="ghost"
              className="w-full justify-start"
              onClick={() => onPageChange("settings")}
            >
              <Settings className="mr-2 h-4 w-4" />
              设置
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}

