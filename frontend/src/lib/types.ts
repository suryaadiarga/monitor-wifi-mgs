export type MetricTone = "emerald" | "blue" | "violet" | "amber" | "cyan" | "rose";

export interface DashboardMetric {
  id: string;
  label: string;
  value: string;
  detail: string;
  change?: string;
  icon: "router" | "network" | "users" | "wifi" | "radio" | "shield";
  tone: MetricTone;
}

export interface DeviceStatus {
  id: string;
  name: string;
  location: string;
  model: string;
  status: "online" | "offline" | "warning";
  cpu?: number;
  ram?: number;
  uptime?: string;
  clients?: number;
}

export interface AlertItem {
  id: string;
  title: string;
  description: string;
  time: string;
  severity: "critical" | "warning" | "info";
}

export interface ActivityItem {
  id: string;
  actor: string;
  action: string;
  target: string;
  time: string;
  initials: string;
}

export interface DashboardData {
  metrics: DashboardMetric[];
  routers: DeviceStatus[];
  olts: DeviceStatus[];
  alerts: AlertItem[];
  activities: ActivityItem[];
  traffic: {
    labels: string[];
    download: number[];
    upload: number[];
    downloadTotal: string;
    uploadTotal: string;
  };
  ont: {
    total: number;
    online: number;
    offline: number;
    los: number;
    lowOptical: number;
  };
  lastUpdated: string;
}

export interface AuthUser {
  id: number | string;
  name: string;
  email: string;
  role: string;
}

export interface LoginResult {
  token: string;
  user: AuthUser;
  demo?: boolean;
}
