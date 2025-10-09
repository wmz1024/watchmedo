import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Mail } from "lucide-react";

export function About() {
  return (
    <div className="space-y-4">
      <h2 className="text-3xl font-bold tracking-tight">关于我们</h2>
      <div className="grid gap-4">
        <Card>
          <CardHeader>
            <CardTitle>公司信息</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <p className="text-muted-foreground">
              Watch Me Do 是由 MZCompute GmbH 开发的一款监控与自动化工具。
            </p>
            <div className="flex items-center space-x-2 text-muted-foreground">
              <Mail className="h-4 w-4" />
              <a
                href="mailto:gmbh@mingze.de"
                className="hover:text-primary transition-colors"
              >
                gmbh@mingze.de
              </a>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}