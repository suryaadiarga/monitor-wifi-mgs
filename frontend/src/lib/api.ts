import { demoDashboardData } from "./demo-data";
import type { DashboardData, LoginResult } from "./types";

const defaultApiBaseUrl = process.env.NODE_ENV === "development"
  ? "http://localhost:8000/api/v1"
  : "/api/v1";
const API_BASE_URL = (process.env.NEXT_PUBLIC_API_BASE_URL ?? process.env.NEXT_PUBLIC_API_URL ?? defaultApiBaseUrl).replace(/\/$/, "");
const TOKEN_KEY = "isp_access_token";
const USER_KEY = "isp_user";

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status = 0,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

type RequestOptions = RequestInit & { timeout?: number };

export interface ApiEnvelope<T> {
  success: boolean;
  message?: string;
  data: T;
  meta: Record<string, unknown>;
}

function getToken() {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(TOKEN_KEY);
}

function unwrap<T>(value: unknown): T {
  if (value && typeof value === "object" && "data" in value) {
    return (value as { data: T }).data;
  }
  return value as T;
}

async function requestJson(path: string, options: RequestOptions = {}): Promise<unknown> {
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), options.timeout ?? 6500);
  const token = getToken();
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body && !(options.body instanceof FormData)) headers.set("Content-Type", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);

  try {
    const response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      headers,
      signal: controller.signal,
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok) {
      const message = payload?.message ?? payload?.error ?? "Permintaan ke server gagal.";
      throw new ApiError(message, response.status);
    }
    return payload;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (error instanceof DOMException && error.name === "AbortError") {
      throw new ApiError("Server tidak merespons. Periksa koneksi Anda.");
    }
    throw new ApiError("Backend belum dapat dijangkau.");
  } finally {
    window.clearTimeout(timer);
  }
}

export async function apiFetch<T>(path: string, options: RequestOptions = {}): Promise<T> {
  return unwrap<T>(await requestJson(path, options));
}

export async function apiFetchEnvelope<T>(path: string, options: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  const payload = await requestJson(path, options);
  if (payload && typeof payload === "object" && "data" in payload) {
    const envelope = payload as Partial<ApiEnvelope<T>>;
    return {
      success: envelope.success !== false,
      message: envelope.message,
      data: envelope.data as T,
      meta: envelope.meta && typeof envelope.meta === "object" ? envelope.meta : {},
    };
  }

  return { success: true, data: payload as T, meta: {} };
}

export const authService = {
  async login(email: string, password: string): Promise<LoginResult> {
    try {
      const result = await apiFetch<LoginResult>("/auth/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });
      const rawUser = result.user as LoginResult["user"] & { roles?: string[] };
      const normalized: LoginResult = {
        token: result.token || (result as unknown as { access_token?: string }).access_token || "",
        user: { ...rawUser, role: rawUser.role ?? rawUser.roles?.[0] ?? "Pengguna" },
      };
      if (!normalized.token || !normalized.user) throw new ApiError("Respons login server tidak lengkap.");
      this.saveSession(normalized);
      return normalized;
    } catch (error) {
      const canUseDemo = error instanceof ApiError && error.status === 0;
      if (!canUseDemo) throw error;

      const demoResult: LoginResult = {
        token: "demo-session-token",
        demo: true,
        user: { id: "demo-admin", name: "Admin ISP", email, role: "Super Admin" },
      };
      this.saveSession(demoResult);
      return demoResult;
    }
  },

  saveSession(result: LoginResult) {
    localStorage.setItem(TOKEN_KEY, result.token);
    localStorage.setItem(USER_KEY, JSON.stringify(result.user));
    if (result.demo) localStorage.setItem("isp_demo_mode", "true");
    else localStorage.removeItem("isp_demo_mode");
  },

  logout() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    localStorage.removeItem("isp_demo_mode");
  },

  hasSession() {
    return Boolean(getToken());
  },
};

export const dashboardService = {
  async getOverview(): Promise<{ data: DashboardData; isDemo: boolean }> {
    try {
      const remote = await apiFetch<Partial<DashboardData> & {
        counts?: Record<string, number>;
        traffic?: DashboardData["traffic"] | { download_bps?: number; upload_bps?: number; avg_cpu_percent?: number; avg_memory_percent?: number };
      }>("/dashboard");
      const counts = remote.counts;
      const backendTraffic = remote.traffic && "download_bps" in remote.traffic ? remote.traffic : null;
      const remoteAlerts = (remote.alerts as unknown as Array<Record<string, unknown>> | undefined)?.map((alert, index) => ({
        id: String(alert.id ?? `remote-alert-${index}`),
        title: String(alert.title ?? "Alert jaringan"),
        description: String(alert.description ?? alert.message ?? "Perlu pemeriksaan operator."),
        time: String(alert.time ?? alert.started_at ?? "baru saja"),
        severity: (["critical", "warning", "info"].includes(String(alert.severity)) ? alert.severity : "warning") as "critical" | "warning" | "info",
      }));
      const remoteActivities = (remote.activities as unknown as Array<Record<string, unknown>> | undefined)?.map((activity, index) => ({
        id: String(activity.id ?? `remote-activity-${index}`),
        actor: String(activity.actor ?? (activity.user_id ? `User #${activity.user_id}` : "Sistem")),
        action: String(activity.action ?? "memperbarui"),
        target: String(activity.target ?? activity.module ?? "sistem"),
        time: String(activity.time ?? activity.created_at ?? "baru saja"),
        initials: String(activity.initials ?? (activity.user_id ? `U${activity.user_id}` : "SY")),
      }));
      const metrics = counts ? demoDashboardData.metrics.map((metric) => {
        const values: Record<string, number | undefined> = {
          routers: counts.routers_total,
          olts: counts.olts_total,
          customers: counts.customers_total,
          pppoe: counts.pppoe_online,
          genieacs: counts.genieacs_devices,
          vpn: counts.vpn_active,
        };
        const value = values[metric.id];
        return value === undefined ? metric : { ...metric, value: String(value) };
      }) : remote.metrics;
      const data: DashboardData = {
        ...demoDashboardData,
        ...remote,
        metrics: metrics?.length ? metrics : demoDashboardData.metrics,
        routers: remote.routers?.length ? remote.routers : demoDashboardData.routers,
        olts: remote.olts?.length ? remote.olts : demoDashboardData.olts,
        alerts: remoteAlerts?.length ? remoteAlerts : demoDashboardData.alerts,
        activities: remoteActivities?.length ? remoteActivities : demoDashboardData.activities,
        traffic: backendTraffic ? {
          ...demoDashboardData.traffic,
          downloadTotal: formatBits(backendTraffic.download_bps ?? 0),
          uploadTotal: formatBits(backendTraffic.upload_bps ?? 0),
        } : (remote.traffic as DashboardData["traffic"] | undefined) ?? demoDashboardData.traffic,
        ont: counts ? {
          total: (counts.ont_online ?? 0) + (counts.ont_offline ?? 0) + (counts.ont_los ?? 0),
          online: counts.ont_online ?? 0,
          offline: counts.ont_offline ?? 0,
          los: counts.ont_los ?? 0,
          lowOptical: counts.ont_low_optical ?? 0,
        } : remote.ont ?? demoDashboardData.ont,
      };
      return { data, isDemo: false };
    } catch {
      return { data: demoDashboardData, isDemo: true };
    }
  },
};

function formatBits(value: number): string {
  if (value >= 1_000_000_000) return `${(value / 1_000_000_000).toFixed(1)} Gbps`;
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} Mbps`;
  if (value >= 1_000) return `${(value / 1_000).toFixed(1)} Kbps`;
  return `${value} bps`;
}
