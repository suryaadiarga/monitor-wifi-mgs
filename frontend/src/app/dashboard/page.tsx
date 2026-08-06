"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Icon, type IconName } from "@/components/icon";
import { ThemeToggle } from "@/components/theme-toggle";
import { authService, dashboardService } from "@/lib/api";
import { demoDashboardData } from "@/lib/demo-data";
import type { AlertItem, DashboardData, DashboardMetric, DeviceStatus, MetricTone } from "@/lib/types";

const menuItems: { label: string; icon: IconName; href: string; badge?: string }[] = [
  { label: "Ringkasan", icon: "dashboard", href: "#ringkasan" },
  { label: "Router MikroTik", icon: "router", href: "/dashboard/routers" },
  { label: "Pelanggan", icon: "users", href: "/dashboard/customers" },
  { label: "Paket internet", icon: "report", href: "/dashboard/packages" },
  { label: "PPPoE", icon: "wifi", href: "/dashboard/pppoe" },
  { label: "Hotspot", icon: "radio", href: "/dashboard/hotspot" },
  { label: "OLT & ONT", icon: "network", href: "/dashboard/olts" },
  { label: "FreeRADIUS", icon: "server", href: "/dashboard/radius" },
  { label: "GenieACS", icon: "radio", href: "/dashboard/genieacs" },
  { label: "VPN", icon: "shield", href: "/dashboard/vpn" },
  { label: "Alert", icon: "alert", href: "/dashboard/alerts" },
  { label: "Audit", icon: "report", href: "/dashboard/audit" },
];

const toneStyles: Record<MetricTone, { icon: string; accent: string }> = {
  emerald: { icon: "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400", accent: "bg-emerald-500" },
  blue: { icon: "bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400", accent: "bg-blue-500" },
  violet: { icon: "bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400", accent: "bg-violet-500" },
  amber: { icon: "bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400", accent: "bg-amber-500" },
  cyan: { icon: "bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-400", accent: "bg-cyan-500" },
  rose: { icon: "bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400", accent: "bg-rose-500" },
};

const statusStyles = {
  online: { label: "Online", dot: "bg-emerald-500", badge: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400" },
  offline: { label: "Offline", dot: "bg-rose-500", badge: "bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400" },
  warning: { label: "Peringatan", dot: "bg-amber-500", badge: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400" },
};

function Logo() {
  return (
    <div className="flex items-center gap-3">
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/25"><Icon name="wifi" className="h-6 w-6" /></span>
      <div><p className="font-bold tracking-tight text-slate-950 dark:text-white">ISP Terpadu</p><p className="text-[9px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">Network Operations</p></div>
    </div>
  );
}

function Sidebar({ mobile = false, onClose, onLogout }: { mobile?: boolean; onClose?: () => void; onLogout: () => void }) {
  return (
    <aside className={`flex h-full flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 ${mobile ? "w-[285px]" : "fixed inset-y-0 left-0 z-30 hidden w-[250px] lg:flex"}`}>
      <div className="flex h-[76px] items-center justify-between border-b border-slate-100 px-5 dark:border-slate-800">
        <Logo />
        {mobile && <button aria-label="Tutup menu" className="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" onClick={onClose} type="button"><Icon name="x" /></button>}
      </div>
      <nav className="flex-1 overflow-y-auto px-3 py-5">
        <p className="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Menu utama</p>
        <div className="space-y-1">
          {menuItems.map((item, index) => (
            <Link className={`group flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition ${index === 0 ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400" : "text-slate-600 hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"}`} href={item.href} key={item.label} onClick={onClose}>
              <Icon name={item.icon} className="h-5 w-5 shrink-0" />
              <span className="flex-1">{item.label}</span>
              {item.badge && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{item.badge}</span>}
            </Link>
          ))}
        </div>
        <p className="px-3 pb-2 pt-7 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Sistem</p>
        <Link className="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" href="/dashboard/users"><Icon name="users" className="h-5 w-5" />User & role</Link>
        <Link className="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" href="/dashboard/telegram"><Icon name="bell" className="h-5 w-5" />Telegram</Link>
        <Link className="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" href="/dashboard/health"><Icon name="settings" className="h-5 w-5" />Kesehatan sistem</Link>
      </nav>
      <div className="border-t border-slate-100 p-3 dark:border-slate-800">
        <div className="mb-2 flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70">
          <span className="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-cyan-500 text-xs font-bold text-white">AI</span>
          <div className="min-w-0 flex-1"><p className="truncate text-xs font-bold text-slate-900 dark:text-white">Admin ISP</p><p className="truncate text-[10px] text-slate-500 dark:text-slate-400">Super Admin</p></div>
          <button aria-label="Keluar" className="text-slate-400 transition hover:text-rose-500" onClick={onLogout} type="button"><Icon name="logout" className="h-4 w-4" /></button>
        </div>
        <div className="flex items-center gap-2 px-2 text-[10px] text-slate-400"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500 status-pulse" />Semua layanan inti aktif</div>
      </div>
    </aside>
  );
}

function MetricCard({ metric }: { metric: DashboardMetric }) {
  const tone = toneStyles[metric.tone];
  return (
    <article className="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:hover:shadow-black/20">
      <span className={`absolute left-0 top-5 h-8 w-1 rounded-r-full ${tone.accent}`} />
      <div className="flex items-start justify-between gap-4">
        <div><p className="text-xs font-semibold text-slate-500 dark:text-slate-400">{metric.label}</p><p className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">{metric.value}</p></div>
        <span className={`grid h-11 w-11 place-items-center rounded-xl transition group-hover:scale-105 ${tone.icon}`}><Icon name={metric.icon} className="h-5 w-5" /></span>
      </div>
      <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">{metric.detail}</p>
      {metric.change && <p className="mt-2 inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />{metric.change}</p>}
    </article>
  );
}

function SectionHeader({ title, subtitle, action }: { title: string; subtitle: string; action?: string }) {
  return (
    <div className="flex items-start justify-between gap-4">
      <div><h2 className="text-base font-bold tracking-tight text-slate-950 dark:text-white">{title}</h2><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{subtitle}</p></div>
      {action && <button className="shrink-0 text-xs font-bold text-emerald-600 transition hover:text-emerald-700 dark:text-emerald-400" type="button">{action}</button>}
    </div>
  );
}

function TrafficChart({ data }: { data: DashboardData["traffic"] }) {
  return (
    <div className="mt-6">
      <div className="flex items-center justify-between gap-5">
        <div className="flex gap-6">
          <div><p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><span className="h-2 w-2 rounded-full bg-emerald-500" />Download</p><p className="mt-1 text-lg font-bold text-slate-950 dark:text-white">{data.downloadTotal}</p></div>
          <div><p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><span className="h-2 w-2 rounded-full bg-cyan-400" />Upload</p><p className="mt-1 text-lg font-bold text-slate-950 dark:text-white">{data.uploadTotal}</p></div>
        </div>
        <select aria-label="Rentang trafik" className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"><option>24 jam</option><option>7 hari</option><option>30 hari</option></select>
      </div>
      <div className="relative mt-5 h-[190px] border-b border-slate-200 dark:border-slate-700">
        <div className="pointer-events-none absolute inset-0 flex flex-col justify-between"><i className="border-t border-dashed border-slate-200 dark:border-slate-800" /><i className="border-t border-dashed border-slate-200 dark:border-slate-800" /><i className="border-t border-dashed border-slate-200 dark:border-slate-800" /><i /></div>
        <div className="absolute inset-x-1 bottom-0 top-2 flex items-end justify-between gap-1 sm:gap-2">
          {data.download.map((value, index) => (
            <div className="group relative flex h-full flex-1 items-end justify-center gap-[2px]" key={`${data.labels[index]}-${value}`}>
              <span className="absolute -top-7 z-10 hidden rounded bg-slate-950 px-2 py-1 text-[9px] whitespace-nowrap text-white shadow group-hover:block dark:bg-slate-700">↓ {value}% · ↑ {data.upload[index]}%</span>
              <span className="w-[42%] min-w-[3px] rounded-t-sm bg-emerald-500/90 transition hover:bg-emerald-400" style={{ height: `${value}%` }} />
              <span className="w-[42%] min-w-[3px] rounded-t-sm bg-cyan-400/75 transition hover:bg-cyan-300" style={{ height: `${data.upload[index]}%` }} />
            </div>
          ))}
        </div>
      </div>
      <div className="mt-2 flex justify-between px-1 text-[9px] font-medium text-slate-400">{data.labels.map((label) => <span key={label}>{label}:00</span>)}</div>
    </div>
  );
}

function Progress({ value, warning = false }: { value: number; warning?: boolean }) {
  return <span className="block h-1.5 w-14 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700"><span className={`block h-full rounded-full ${warning ? "bg-amber-500" : "bg-emerald-500"}`} style={{ width: `${Math.min(value, 100)}%` }} /></span>;
}

function StatusBadge({ status }: { status: DeviceStatus["status"] }) {
  const style = statusStyles[status];
  return <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold ${style.badge}`}><span className={`h-1.5 w-1.5 rounded-full ${style.dot} ${status === "online" ? "status-pulse" : ""}`} />{style.label}</span>;
}

function RouterTable({ routers }: { routers: DeviceStatus[] }) {
  return (
    <div className="mt-5 overflow-x-auto">
      <div className="min-w-[680px]">
        <div className="grid grid-cols-[1.7fr_1fr_.7fr_.7fr_.75fr] gap-4 border-b border-slate-100 px-2 pb-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:border-slate-800"><span>Router</span><span>Status</span><span>CPU</span><span>RAM</span><span className="text-right">Klien</span></div>
        {routers.map((router) => (
          <div className="grid grid-cols-[1.7fr_1fr_.7fr_.7fr_.75fr] items-center gap-4 border-b border-slate-100 px-2 py-3.5 last:border-0 dark:border-slate-800" key={router.id}>
            <div className="flex min-w-0 items-center gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><Icon name="router" className="h-4 w-4" /></span><div className="min-w-0"><p className="truncate text-xs font-bold text-slate-900 dark:text-white">{router.name}</p><p className="mt-0.5 truncate text-[10px] text-slate-400">{router.location} · {router.uptime}</p></div></div>
            <StatusBadge status={router.status} />
            <div><span className="text-[11px] font-semibold text-slate-600 dark:text-slate-300">{router.cpu}%</span><Progress value={router.cpu ?? 0} warning={(router.cpu ?? 0) > 70} /></div>
            <div><span className="text-[11px] font-semibold text-slate-600 dark:text-slate-300">{router.ram}%</span><Progress value={router.ram ?? 0} warning={(router.ram ?? 0) > 70} /></div>
            <p className="text-right text-xs font-bold text-slate-800 dark:text-slate-200">{router.clients?.toLocaleString("id-ID")}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

function OltList({ olts }: { olts: DeviceStatus[] }) {
  return (
    <div className="mt-5 space-y-2">
      {olts.map((olt) => (
        <div className="flex items-center gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-slate-200 hover:bg-slate-50/60 dark:border-slate-800 dark:hover:border-slate-700 dark:hover:bg-slate-800/40" key={olt.id}>
          <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${olt.status === "online" ? "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400" : olt.status === "warning" ? "bg-amber-50 text-amber-600 dark:bg-amber-500/10" : "bg-rose-50 text-rose-600 dark:bg-rose-500/10"}`}><Icon name="network" className="h-4 w-4" /></span>
          <div className="min-w-0 flex-1"><p className="truncate text-xs font-bold text-slate-900 dark:text-white">{olt.name}</p><p className="mt-0.5 truncate text-[10px] text-slate-400">{olt.model} · {olt.location}</p></div>
          <div className="text-right"><StatusBadge status={olt.status} /><p className="mt-1 text-[9px] font-medium text-slate-400">{olt.clients?.toLocaleString("id-ID")} ONT</p></div>
        </div>
      ))}
    </div>
  );
}

function OntOverview({ ont }: { ont: DashboardData["ont"] }) {
  const onlinePercentage = Math.round((ont.online / ont.total) * 100);
  return (
    <div className="mt-6 flex flex-col items-center gap-6 sm:flex-row">
      <div className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full" style={{ background: `conic-gradient(#10b981 0 ${onlinePercentage}%, #fb7185 ${onlinePercentage}% ${Math.min(onlinePercentage + 10, 100)}%, #f59e0b ${Math.min(onlinePercentage + 10, 100)}% 100%)` }}>
        <div className="grid h-[106px] w-[106px] place-items-center rounded-full bg-white text-center shadow-inner dark:bg-slate-900"><div><p className="text-2xl font-bold text-slate-950 dark:text-white">{onlinePercentage}%</p><p className="text-[9px] font-bold uppercase tracking-wider text-slate-400">Online</p></div></div>
      </div>
      <div className="grid w-full grid-cols-2 gap-3">
        {[
          ["Online", ont.online, "bg-emerald-500"],
          ["Offline", ont.offline, "bg-slate-400"],
          ["LOS", ont.los, "bg-rose-500"],
          ["Low optical", ont.lowOptical, "bg-amber-500"],
        ].map(([label, value, color]) => (
          <div className="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60" key={String(label)}><p className="flex items-center gap-1.5 text-[10px] text-slate-500 dark:text-slate-400"><span className={`h-1.5 w-1.5 rounded-full ${color}`} />{label}</p><p className="mt-1 text-lg font-bold text-slate-900 dark:text-white">{Number(value).toLocaleString("id-ID")}</p></div>
        ))}
      </div>
    </div>
  );
}

function AlertList({ alerts }: { alerts: AlertItem[] }) {
  const alertStyle = {
    critical: "bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400",
    warning: "bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400",
    info: "bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400",
  };
  return <div className="mt-5 space-y-1">{alerts.map((alert) => <div className="flex gap-3 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/50" key={alert.id}><span className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${alertStyle[alert.severity]}`}><Icon name={alert.severity === "info" ? "shield" : "alert"} className="h-4 w-4" /></span><div className="min-w-0 flex-1"><div className="flex items-start justify-between gap-3"><p className="text-xs font-bold text-slate-900 dark:text-white">{alert.title}</p><span className="whitespace-nowrap text-[9px] text-slate-400">{alert.time}</span></div><p className="mt-1 text-[10px] leading-4 text-slate-500 dark:text-slate-400">{alert.description}</p></div></div>)}</div>;
}

export default function DashboardPage() {
  const router = useRouter();
  const [data, setData] = useState<DashboardData>(demoDashboardData);
  const [mobileMenu, setMobileMenu] = useState(false);
  const [isDemo, setIsDemo] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    if (!authService.hasSession()) {
      router.replace("/login");
      return;
    }
    let active = true;
    dashboardService.getOverview().then((result) => {
      if (!active) return;
      setData(result.data);
      setIsDemo(result.isDemo);
    });
    return () => { active = false; };
  }, [router]);

  function logout() {
    authService.logout();
    router.replace("/login");
  }

  async function refresh() {
    setRefreshing(true);
    const result = await dashboardService.getOverview();
    setData(result.data);
    setIsDemo(result.isDemo);
    setRefreshing(false);
  }

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
      <Sidebar onLogout={logout} />
      {mobileMenu && <div className="fixed inset-0 z-50 flex lg:hidden"><button aria-label="Tutup menu" className="absolute inset-0 bg-slate-950/55 backdrop-blur-sm" onClick={() => setMobileMenu(false)} type="button" /><div className="relative h-full"><Sidebar mobile onClose={() => setMobileMenu(false)} onLogout={logout} /></div></div>}

      <div className="lg:pl-[250px]">
        <header className="sticky top-0 z-20 flex h-[76px] items-center border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90 sm:px-6 lg:px-8">
          <button aria-label="Buka menu" className="mr-3 grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 lg:hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" onClick={() => setMobileMenu(true)} type="button"><Icon name="menu" /></button>
          <div className="hidden max-w-sm flex-1 sm:block"><div className="relative"><Icon name="search" className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input aria-label="Cari perangkat atau pelanggan" className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-xs text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-emerald-500" placeholder="Cari router, pelanggan, ONT..." /></div></div>
          <div className="ml-auto flex items-center gap-2 sm:gap-3">
            <div className="hidden items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-[10px] font-bold text-emerald-700 md:flex dark:bg-emerald-500/10 dark:text-emerald-400"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500 status-pulse" />Sistem Normal</div>
            <ThemeToggle compact />
            <button aria-label="Notifikasi" className="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" type="button"><Icon name="bell" className="h-5 w-5" /><span className="absolute right-2 top-2 h-2 w-2 rounded-full border-2 border-white bg-rose-500 dark:border-slate-900" /></button>
            <div className="ml-1 hidden items-center gap-2 border-l border-slate-200 pl-4 sm:flex dark:border-slate-700"><span className="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-cyan-500 text-xs font-bold text-white">AI</span><div className="hidden xl:block"><p className="text-xs font-bold text-slate-900 dark:text-white">Admin ISP</p><p className="text-[9px] text-slate-400">Super Admin</p></div></div>
          </div>
        </header>

        <main id="ringkasan" className="dashboard-grid px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <div className="mx-auto max-w-[1600px]">
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
              <div><div className="flex items-center gap-2"><h1 className="text-2xl font-bold tracking-tight text-slate-950 dark:text-white sm:text-3xl">Ringkasan Jaringan</h1>{isDemo && <span className="rounded-md bg-amber-100 px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Data demo</span>}</div><p className="mt-2 text-sm text-slate-500 dark:text-slate-400">Pantau kondisi seluruh infrastruktur dan layanan ISP Anda.</p></div>
              <div className="flex flex-wrap items-center gap-2">
                <select aria-label="Filter area" className="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"><option>Semua Area</option><option>Jakarta</option><option>Bandung</option><option>Bogor</option></select>
                <select aria-label="Filter router" className="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"><option>Semua Router</option><option>Router Online</option><option>Router Offline</option></select>
                <button aria-label="Perbarui data" className="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-emerald-300 hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" onClick={refresh} type="button"><Icon name="refresh" className={`h-4 w-4 ${refreshing ? "animate-spin" : ""}`} /></button>
              </div>
            </div>

            <section aria-label="Metrik utama" className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
              {data.metrics.map((metric) => <MetricCard key={metric.id} metric={metric} />)}
            </section>

            <section className="mt-5 grid gap-5 xl:grid-cols-[1.5fr_1fr]">
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"><SectionHeader title="Trafik Agregat" subtitle="Total trafik seluruh interface WAN" /><TrafficChart data={data.traffic} /></article>
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"><SectionHeader title="Status ONT" subtitle={`${data.ont.total.toLocaleString("id-ID")} perangkat terdaftar`} action="Lihat perangkat" /><OntOverview ont={data.ont} /></article>
            </section>

            <section id="router" className="mt-5 grid gap-5 xl:grid-cols-[1.5fr_1fr]">
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"><SectionHeader title="Status Router" subtitle="Resource dan sesi aktif router utama" action="Kelola router" /><RouterTable routers={data.routers} /></article>
              <article id="olt" className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"><SectionHeader title="Status OLT" subtitle="Konektivitas perangkat akses optik" action="Kelola OLT" /><OltList olts={data.olts} /></article>
            </section>

            <section className="mt-5 grid gap-5 xl:grid-cols-2">
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"><SectionHeader title="Alert Terbaru" subtitle="Insiden yang perlu perhatian operasional" action="Lihat semua" /><AlertList alerts={data.alerts} /></article>
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"><SectionHeader title="Aktivitas Administrator" subtitle="Audit perubahan terbaru di sistem" action="Buka audit log" /><div className="mt-5 space-y-1">{data.activities.map((activity) => <div className="flex gap-3 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/50" key={activity.id}><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{activity.initials}</span><div className="min-w-0 flex-1"><div className="flex items-start justify-between gap-3"><p className="text-xs leading-5 text-slate-600 dark:text-slate-300"><span className="font-bold text-slate-900 dark:text-white">{activity.actor}</span> {activity.action}</p><span className="whitespace-nowrap text-[9px] text-slate-400">{activity.time}</span></div><p className="mt-0.5 truncate text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">{activity.target}</p></div></div>)}</div></article>
            </section>

            <footer className="mt-8 flex flex-col items-center justify-between gap-2 border-t border-slate-200 py-5 text-[10px] text-slate-400 dark:border-slate-800 sm:flex-row"><p>© 2026 ISP Terpadu · Network Operations Center</p><p className="flex items-center gap-2"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500 status-pulse" />Terakhir diperbarui: {data.lastUpdated}</p></footer>
          </div>
        </main>
      </div>
    </div>
  );
}
