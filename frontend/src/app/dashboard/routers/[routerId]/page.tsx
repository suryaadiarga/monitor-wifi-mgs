"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";
import { Icon } from "@/components/icon";
import { RouterDataTable } from "@/components/router-data-table";
import { ThemeToggle } from "@/components/theme-toggle";
import { apiFetch, authService } from "@/lib/api";
import { formatBytes, routerTabs, type RouterDetail, type RouterMetricSnapshot } from "@/lib/router-monitoring";

function dateTime(value: unknown): string {
  if (!value) return "—";
  const date = new Date(String(value));
  return Number.isNaN(date.valueOf()) ? String(value) : date.toLocaleString("id-ID");
}

function numberValue(...values: unknown[]): number | null {
  for (const value of values) {
    const number = Number(value);
    if (value !== null && value !== undefined && value !== "" && Number.isFinite(number)) return number;
  }
  return null;
}

function countValue(counts: Record<string, number> | undefined, ...keys: string[]): number {
  for (const key of keys) {
    const value = counts?.[key];
    if (typeof value === "number") return value;
  }
  return 0;
}

function percent(value: number | null): string {
  return value === null ? "—" : `${Math.max(0, Math.min(100, value)).toLocaleString("id-ID", { maximumFractionDigits: 1 })}%`;
}

function statusStyle(status?: string | null) {
  const normalized = status?.toLowerCase();
  if (normalized === "online" || normalized === "success") return { label: "Online", dot: "bg-emerald-500", badge: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300" };
  if (normalized === "connecting" || normalized === "warning") return { label: "Peringatan", dot: "bg-amber-500", badge: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300" };
  return { label: "Offline", dot: "bg-rose-500", badge: "bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300" };
}

function MetricGauge({ label, value, detail, tone }: { label: string; value: number | null; detail?: string; tone: "emerald" | "blue" | "amber" }) {
  const width = value === null ? 0 : Math.max(0, Math.min(100, value));
  const bar = tone === "emerald" ? "bg-emerald-500" : tone === "blue" ? "bg-blue-500" : "bg-amber-500";
  return (
    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="flex items-center justify-between gap-4"><p className="text-xs font-semibold text-slate-500">{label}</p><p className="text-xl font-bold">{percent(value)}</p></div>
      <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div className={`h-full rounded-full transition-all ${bar}`} style={{ width: `${width}%` }} /></div>
      <p className="mt-3 truncate text-[10px] text-slate-500" title={detail}>{detail ?? "Data belum tersedia"}</p>
    </article>
  );
}

function CountCard({ label, value, icon }: { label: string; value: number; icon: "network" | "wifi" | "radio" | "server" }) {
  return (
    <article className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="flex items-center gap-3">
        <span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"><Icon className="h-5 w-5" name={icon} /></span>
        <div><p className="text-2xl font-bold">{value.toLocaleString("id-ID")}</p><p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{label}</p></div>
      </div>
    </article>
  );
}

function Overview({ router }: { router: RouterDetail }) {
  const resources: RouterMetricSnapshot = router.latest_resource ?? router.latest_metric ?? router.resources ?? {};
  const cpu = numberValue(resources.cpu_percent);
  const memory = numberValue(resources.memory_percent);
  const storage = numberValue(resources.storage_percent);
  const memoryDetail = resources.total_memory ? `${formatBytes(resources.free_memory)} bebas dari ${formatBytes(resources.total_memory)}` : undefined;
  const storageDetail = resources.total_storage ? `${formatBytes(resources.free_storage)} bebas dari ${formatBytes(resources.total_storage)}` : undefined;
  const identity = router.identity ?? router.name;
  const uptime = router.uptime ?? resources.uptime ?? (resources.uptime_seconds ? `${resources.uptime_seconds.toLocaleString("id-ID")} detik` : "—");
  const latency = numberValue(router.latency_ms, router.response_latency_ms);

  const facts: Array<[string, string]> = [
    ["Identity", identity || "—"],
    ["RouterOS", router.routeros_version ?? "—"],
    ["Model / board", router.model ?? router.board_name ?? "—"],
    ["Serial number", router.serial_number ?? "—"],
    ["Arsitektur", router.architecture ?? "—"],
    ["Uptime", String(uptime)],
    ["Last connected", dateTime(router.last_connected_at ?? router.last_seen_at)],
    ["Last sync", dateTime(router.last_successful_sync_at ?? router.last_sync_at)],
    ["Latency", latency === null ? "—" : `${latency.toLocaleString("id-ID")} ms`],
    ["Credential", router.credential_configured === false ? "Belum dikonfigurasi" : "Terkonfigurasi (terenkripsi)"],
  ];

  return (
    <div className="space-y-5">
      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <CountCard icon="network" label="Interface aktif" value={countValue(router.counts, "interfaces_active", "active_interfaces", "interfaces_running")} />
        <CountCard icon="wifi" label="PPPoE aktif" value={countValue(router.counts, "pppoe_active", "active_pppoe")} />
        <CountCard icon="radio" label="Hotspot aktif" value={countValue(router.counts, "hotspot_active", "active_hotspot")} />
        <CountCard icon="server" label="DHCP bound" value={countValue(router.counts, "dhcp_bound", "bound_dhcp_leases")} />
      </section>

      <section className="grid gap-4 md:grid-cols-3">
        <MetricGauge label="CPU load" tone="emerald" value={cpu} detail={resources.temperature_celsius != null ? `Suhu ${resources.temperature_celsius} °C` : undefined} />
        <MetricGauge label="Pemakaian memory" tone="blue" value={memory} detail={memoryDetail} />
        <MetricGauge label="Pemakaian storage" tone="amber" value={storage} detail={storageDetail} />
      </section>

      <section className="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 className="font-bold">Informasi router</h2><p className="mt-1 text-xs text-slate-500">Snapshot terakhir dari sinkronisasi read-only.</p></div>
          <dl className="grid sm:grid-cols-2">
            {facts.map(([label, value]) => <div className="border-b border-slate-100 px-5 py-4 last:border-b-0 dark:border-slate-800 sm:odd:border-r" key={label}><dt className="text-[10px] font-bold uppercase tracking-wide text-slate-400">{label}</dt><dd className="mt-1 break-words text-sm font-semibold text-slate-800 dark:text-slate-200">{value}</dd></div>)}
          </dl>
        </article>

        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <h2 className="font-bold">Kondisi koneksi</h2>
          <div className="mt-4 space-y-4 text-sm">
            <div><p className="text-[10px] font-bold uppercase tracking-wide text-slate-400">Endpoint</p><p className="mt-1 font-mono text-xs">{router.host}:{router.api_port ?? (router.use_ssl ? 8729 : 8728)}</p></div>
            <div><p className="text-[10px] font-bold uppercase tracking-wide text-slate-400">Mode</p><p className="mt-1 font-semibold">{router.use_ssl ? "API-SSL dengan validasi TLS" : "RouterOS API tanpa TLS"}</p></div>
            <div><p className="text-[10px] font-bold uppercase tracking-wide text-slate-400">Error terakhir</p><p className={`mt-1 break-words text-xs ${router.last_error ? "text-rose-600 dark:text-rose-300" : "text-emerald-600 dark:text-emerald-300"}`}>{router.last_error || "Tidak ada error tersanitasi."}</p></div>
          </div>
        </article>
      </section>
    </div>
  );
}

function LoadingPage() {
  return <main className="grid min-h-screen place-items-center bg-slate-50 text-slate-500 dark:bg-slate-950"><div className="flex items-center gap-3 text-sm"><Icon className="h-5 w-5 animate-spin" name="refresh" /> Memuat detail router...</div></main>;
}

export default function RouterDetailPage() {
  const params = useParams<{ routerId: string }>();
  const navigation = useRouter();
  const routerId = params.routerId;
  const [activeTab, setActiveTab] = useState(() => {
    if (typeof window === "undefined") return "overview";
    const hash = window.location.hash.slice(1);
    return routerTabs.some((candidate) => candidate.id === hash) ? hash : "overview";
  });
  const [router, setRouter] = useState<RouterDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const tab = useMemo(() => routerTabs.find((candidate) => candidate.id === activeTab) ?? routerTabs[0], [activeTab]);

  const loadRouter = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setRouter(await apiFetch<RouterDetail>(`/routers/${encodeURIComponent(routerId)}`));
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : "Detail router gagal dimuat.");
    } finally {
      setLoading(false);
    }
  }, [routerId]);

  useEffect(() => {
    if (!authService.hasSession()) {
      navigation.replace("/login");
      return;
    }
    const timer = window.setTimeout(() => void loadRouter(), 0);
    return () => window.clearTimeout(timer);
  }, [loadRouter, navigation]);

  function selectTab(id: string) {
    setActiveTab(id);
    window.history.replaceState(null, "", `${window.location.pathname}#${id}`);
  }

  if (loading && !router) return <LoadingPage />;
  if (error && !router) {
    return <main className="grid min-h-screen place-items-center bg-slate-50 p-6 dark:bg-slate-950"><div className="max-w-md rounded-2xl border border-rose-200 bg-white p-6 text-center shadow-sm dark:border-rose-500/20 dark:bg-slate-900"><h1 className="text-lg font-bold text-rose-700 dark:text-rose-300">Detail router tidak tersedia</h1><p className="mt-2 text-sm text-slate-500">{error}</p><div className="mt-5 flex justify-center gap-3"><Link className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold dark:border-slate-700" href="/dashboard/routers">Kembali</Link><button className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white" onClick={() => void loadRouter()} type="button">Coba lagi</button></div></div></main>;
  }
  if (!router) return null;

  const state = statusStyle(router.status);
  const insecureApi = !router.use_ssl || Number(router.api_port) === 8728;

  return (
    <main className="min-h-screen bg-slate-50 p-4 text-slate-900 dark:bg-slate-950 dark:text-slate-100 sm:p-8">
      <div className="mx-auto max-w-[1500px]">
        <header className="mb-5 flex flex-wrap items-center gap-3">
          <Link aria-label="Kembali ke daftar router" className="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-400 hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" href="/dashboard/routers"><span className="rotate-180"><Icon className="h-4 w-4" name="chevron" /></span></Link>
          <span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20"><Icon name="router" /></span>
          <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h1 className="truncate text-2xl font-bold tracking-tight">{router.name}</h1><span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${state.badge}`}><span className={`h-1.5 w-1.5 rounded-full ${state.dot}`} />{state.label}</span></div><p className="mt-1 font-mono text-xs text-slate-500">{router.host}:{router.api_port ?? 8728} · {router.identity ?? "Identity belum tersinkron"}</p></div>
          <ThemeToggle compact />
          <button className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold transition hover:border-emerald-400 dark:border-slate-700 dark:bg-slate-900" disabled={loading} onClick={() => void loadRouter()} type="button"><Icon className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} name="refresh" /> Refresh overview</button>
          <button className="h-10 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold dark:border-slate-700 dark:bg-slate-900" onClick={() => { authService.logout(); navigation.replace("/login"); }} type="button">Keluar</button>
        </header>

        {insecureApi && (
          <div className="mb-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200" role="alert">
            <Icon className="mt-0.5 h-5 w-5 shrink-0" name="alert" />
            <div><p className="text-sm font-bold">Koneksi API belum menggunakan TLS</p><p className="mt-1 text-xs leading-5">Batasi sumber IP dan pindahkan ke API-SSL sebelum digunakan untuk operasi sensitif.</p></div>
          </div>
        )}

        {error && <div className="mb-5 rounded-xl bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">Refresh overview gagal: {error}</div>}

        <nav aria-label="Data router" className="mb-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <div className="flex min-w-max gap-1">
            {routerTabs.map((candidate) => <button aria-current={candidate.id === activeTab ? "page" : undefined} className={`rounded-xl px-3 py-2.5 text-xs font-semibold transition ${candidate.id === activeTab ? "bg-emerald-600 text-white shadow-sm" : "text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"}`} key={candidate.id} onClick={() => selectTab(candidate.id)} type="button">{candidate.label}</button>)}
          </div>
        </nav>

        {tab.id === "overview" ? <Overview router={router} /> : <RouterDataTable key={tab.id} routerId={routerId} tab={tab} />}

        <footer className="mt-8 flex flex-col gap-2 border-t border-slate-200 py-5 text-[10px] text-slate-400 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between"><p>Integrasi RouterOS tahap pertama bersifat read-only. Credential tidak pernah dimuat ke halaman ini.</p><p>Last sync: {dateTime(router.last_successful_sync_at ?? router.last_sync_at)}</p></footer>
      </div>
    </main>
  );
}
