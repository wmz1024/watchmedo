import { ModeToggle } from "@/components/mode-toggle";
import { Button } from "@/components/ui/button";
import { Menu } from "lucide-react";

interface NavbarProps {
  onMenuClick?: () => void;
}

export function Navbar({ onMenuClick }: NavbarProps) {
  return (
    <div className="flex items-center justify-between">
      <div className="flex items-center space-x-4">
        <Button
          variant="ghost"
          size="icon"
          className="lg:hidden"
          onClick={onMenuClick}
        >
          <Menu className="h-5 w-5" />
        </Button>
        <div className="space-y-1">
          <h2 className="text-2xl font-semibold tracking-tight">
            👁 Watch Me Do
          </h2>
          <p className="text-sm text-muted-foreground">
            实时视奸您的系统状态
          </p>
        </div>
      </div>
      <div className="ml-auto flex items-center space-x-4">
        <ModeToggle />
      </div>
    </div>
  );
}

