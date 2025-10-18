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
      <div className="min-h-screen">
        <div className="border-t">
          <div className="bg-background">
            <div className="grid lg:grid-cols-5">
              {/* 移动端侧边栏 - 覆盖层 */}
              {sidebarOpen && (
                <div
                  className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                  onClick={() => setSidebarOpen(false)}
                />
              )}
              
              {/* 侧边栏 */}
              <div
                className={`fixed inset-y-0 left-0 z-50 w-64 transform bg-background border-r transition-transform duration-200 ease-in-out lg:relative lg:translate-x-0 lg:w-auto ${
                  sidebarOpen ? "translate-x-0" : "-translate-x-full"
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
              <div className="col-span-full lg:col-span-4 lg:border-l">
                <div className="h-full px-4 py-6 lg:px-8">
                  <Navbar onMenuClick={() => setSidebarOpen(!sidebarOpen)} />
                  <div className="pt-6">
                    {page === "dashboard" && <Dashboard />}
                    {page === "remote" && <Remote />}
                    {page === "settings" && <Settings />}
                    {page === "about" && <About />}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <Toaster />
      </div>
    </ThemeProvider>
  );
};