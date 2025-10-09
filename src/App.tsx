import { useState } from "react";
import { ThemeProvider } from "@/components/theme-provider";
import { Sidebar } from "./components/layout/Sidebar";
import { Navbar } from "./components/layout/Navbar";
import { Dashboard } from "./pages/Dashboard";
import { Settings } from "./pages/Settings";
import { Remote } from "./pages/Remote";
import { About } from "./pages/About";
import { Toaster } from "@/components/ui/sonner";

export const App = () => {
  const [page, setPage] = useState<"dashboard" | "settings" | "remote" | "about">("dashboard");
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <ThemeProvider defaultTheme="dark" storageKey="vite-ui-theme">
      <div className="flex h-screen">
        {/* 移动端侧边栏 - 覆盖层 */}
        {sidebarOpen && (
          <div
            className="fixed inset-0 z-40 bg-black/50 lg:hidden"
            onClick={() => setSidebarOpen(false)}
          />
        )}
        
        {/* 固定侧边栏 */}
        <div
          className={`fixed lg:static left-0 top-0 z-50 w-64 h-full bg-background border-r transition-transform duration-200 ease-in-out ${
            sidebarOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0"
          }`}
        >
          <Sidebar
            onPageChange={(newPage) => {
              setPage(newPage);
              setSidebarOpen(false);
            }}
          />
        </div>

        {/* 主内容区域 */}
        <div className="flex-1 min-w-0 overflow-auto scrollbar-custom">
          <div className="h-full">
            <Navbar onMenuClick={() => setSidebarOpen(!sidebarOpen)} />
            <div className="p-6">
              {page === "dashboard" && <Dashboard />}
              {page === "remote" && <Remote />}
              {page === "settings" && <Settings />}
              {page === "about" && <About />}
            </div>
          </div>
        </div>
      </div>
      <Toaster />
    </ThemeProvider>
  );
};