"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";
import { Icon } from "@/components/icon";
import { ThemeToggle } from "@/components/theme-toggle";
import { apiFetch, authService } from "@/lib/api";

type Row = Record<string, unknown>;
type ModuleDefinition = { title: string; description: string; endpoint: string; columns: Array<[string, string]> };

const modules: Record<string, ModuleDefinition> = {
  routers: { title: "Router MikroTik", description: "Inventaris, status, dan endpoint koneksi router.", endpoint: "/routers", columns: [["name", "Nama"], ["host", "Host"], ["status", "Status"], ["model", "Model"], ["last_seen_at", "Terakhir terlihat"]] },
  customers: { title: "Pelanggan", description: "Pelanggan sesuai scope role dan reseller.", endpoint: "/customers", columns: [["customer_number", "Nomor"], ["name", "Nama"], ["status", "Status"], ["package.name", "Paket"], ["pppoe_username", "PPPoE"]] },
  packages: { title: "Paket internet", description: "Definisi bandwidth dan harga paket.", endpoint: "/packages", columns: [["name", "Nama"], ["download_kbps", "Download Kbps"], ["upload_kbps", "Upload Kbps"], ["price", "Harga"], ["enabled", "Aktif"]] },
  pppoe: { title: "Akun PPPoE", description: "Akun aplikasi dan status sinkronisasi.", endpoint: "/pppoe/accounts", columns: [["username", "Username"], ["profile", "Profile"], ["auth_source", "Sumber"], ["disabled", "Disabled"], ["last_synced_at", "Sinkron terakhir"]] },
  hotspot: { title: "User Hotspot", description: "User, voucher, dan masa aktif Hotspot.", endpoint: "/hotspot/users", columns: [["username", "Username"], ["mac_address", "MAC"], ["expires_at", "Kedaluwarsa"], ["disabled", "Disabled"], ["created_at", "Dibuat"]] },
  radius: { title: "Diagnostik FreeRADIUS", description: "Kesehatan service, database, dan NAS tanpa membuka secret.", endpoint: "/radius/health", columns: [["driver", "Driver"], ["database.message", "Database"], ["nas.configured", "NAS"], ["nas.enabled", "NAS aktif"], ["checked_at", "Diperiksa"]] },
  genieacs: { title: "Perangkat GenieACS", description: "Snapshot inventory perangkat TR-069.", endpoint: "/genieacs/devices", columns: [["serial_number", "Serial"], ["manufacturer", "Vendor"], ["product_class", "Product class"], ["status", "Status"], ["last_inform_at", "Last inform"]] },
  olts: { title: "OLT dan ONT", description: "OLT multi-vendor dengan adapter mock-first.", endpoint: "/olts", columns: [["name", "Nama"], ["vendor", "Vendor"], ["host", "Host"], ["protocol", "Protokol"], ["status", "Status"]] },
  vpn: { title: "Server VPN", description: "Server WireGuard/IKEv2 dan jumlah client.", endpoint: "/vpn/servers", columns: [["name", "Nama"], ["type", "Tipe"], ["endpoint", "Endpoint"], ["address_pool", "Pool"], ["clients_count", "Client"]] },
  alerts: { title: "Alert", description: "Alert aktif dan histori insiden jaringan.", endpoint: "/alerts", columns: [["severity", "Severity"], ["title", "Judul"], ["status", "Status"], ["source_type", "Sumber"], ["started_at", "Mulai"]] },
  audit: { title: "Audit log", description: "Perubahan administrator yang sudah disanitasi.", endpoint: "/audit-logs", columns: [["module", "Modul"], ["action", "Aksi"], ["status", "Status"], ["correlation_id", "Correlation ID"], ["created_at", "Waktu"]] },
  users: { title: "Manajemen user", description: "User dan role yang diberikan.", endpoint: "/users", columns: [["name", "Nama"], ["email", "Email"], ["roles", "Role"], ["created_at", "Dibuat"]] },
  telegram: { title: "Telegram", description: "Konfigurasi notifikasi tanpa menampilkan bot token.", endpoint: "/telegram-settings", columns: [["chat_id", "Chat ID"], ["enabled", "Aktif"], ["bot_token_configured", "Token tersedia"], ["alert_types", "Jenis alert"], ["updated_at", "Diperbarui"]] },
  health: { title: "Kesehatan sistem", description: "Status database, cache, dan queue aplikasi.", endpoint: "/system/health", columns: [["healthy", "Sehat"], ["checks.database.status", "Database"], ["checks.cache.status", "Cache"], ["checks.queue.driver", "Queue"], ["time", "Waktu"]] },
};

function readPath(row: Row, path: string): unknown {
  return path.split(".").reduce<unknown>((value, key) => value && typeof value === "object" ? (value as Row)[key] : undefined, row);
}

function display(value: unknown): string {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "boolean") return value ? "Ya" : "Tidak";
  if (Array.isArray(value)) return value.map(display).join(", ");
  if (typeof value === "object") return JSON.stringify(value);
  if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}T/.test(value)) return new Date(value).toLocaleString("id-ID");
  return String(value);
}

function normalizeRows(payload: unknown): Row[] {
  if (Array.isArray(payload)) return payload as Row[];
  if (payload && typeof payload === "object" && Array.isArray((payload as Row).data)) return (payload as { data: Row[] }).data;
  return payload && typeof payload === "object" ? [payload as Row] : [];
}

export default function ModulePage() {
  const params = useParams<{ module: string }>();
  const router = useRouter();
  const definition = modules[params.module];
  const [rows, setRows] = useState<Row[]>([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!definition) return;
    setLoading(true);
    setError(null);
    try {
      const query = search.trim() ? `?search=${encodeURIComponent(search.trim())}` : "";
      setRows(normalizeRows(await apiFetch<unknown>(definition.endpoint + query)));
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : "Data gagal dimuat.");
    } finally {
      setLoading(false);
    }
  }, [definition, search]);

  useEffect(() => {
    if (!authService.hasSession()) {
      router.replace("/login");
      return;
    }
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load, router]);

  const csv = useMemo(() => definition ? [definition.columns.map(([, label]) => label), ...rows.map((row) => definition.columns.map(([key]) => display(readPath(row, key))))] : [], [definition, rows]);

  function exportCsv() {
    const content = csv.map((line) => line.map((cell) => `"${cell.replaceAll('"', '""')}"`).join(",")).join("\n");
    const url = URL.createObjectURL(new Blob([content], { type: "text/csv;charset=utf-8" }));
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = `${params.module}.csv`;
    anchor.click();
    URL.revokeObjectURL(url);
  }

  if (!definition) return <main className="grid min-h-screen place-items-center bg-slate-50 p-6 dark:bg-slate-950"><div className="text-center"><h1 className="text-xl font-bold dark:text-white">Modul tidak ditemukan</h1><Link className="mt-4 inline-block text-emerald-600" href="/dashboard">Kembali ke dashboard</Link></div></main>;

  return (
    <main className="min-h-screen bg-slate-50 p-4 text-slate-900 dark:bg-slate-950 dark:text-slate-100 sm:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="mb-6 flex flex-wrap items-center gap-3">
          <Link className="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" href="/dashboard"><Icon name="dashboard" className="h-4 w-4" /></Link>
          <div className="min-w-0 flex-1"><h1 className="text-2xl font-bold tracking-tight">{definition.title}</h1><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{definition.description}</p></div>
          <ThemeToggle compact />
          <button className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold dark:border-slate-700 dark:bg-slate-900" onClick={() => { authService.logout(); router.replace("/login"); }} type="button">Keluar</button>
        </header>

        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <div className="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row">
            <div className="relative flex-1"><Icon name="search" className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm outline-none focus:border-emerald-500 dark:border-slate-700 dark:bg-slate-800" onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => event.key === "Enter" && void load()} placeholder="Cari data..." value={search} /></div>
            <button className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white" onClick={() => void load()} type="button">Cari / refresh</button>
            <button className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold dark:border-slate-700" disabled={!rows.length} onClick={exportCsv} type="button">Export CSV</button>
          </div>

          {loading ? <div className="p-12 text-center text-sm text-slate-500">Memuat data...</div> : error ? <div className="m-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{error}</div> : rows.length === 0 ? <div className="p-12 text-center text-sm text-slate-500">Belum ada data untuk ditampilkan.</div> : (
            <div className="overflow-x-auto"><table className="min-w-full text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400"><tr>{definition.columns.map(([key, label]) => <th className="whitespace-nowrap px-4 py-3" key={key}>{label}</th>)}</tr></thead><tbody>{rows.map((row, index) => <tr className="border-t border-slate-100 dark:border-slate-800" key={String(row.id ?? index)}>{definition.columns.map(([key]) => <td className="max-w-xs truncate px-4 py-3" key={key} title={display(readPath(row, key))}>{params.module === "routers" && key === "name" && row.id ? <Link className="font-bold text-emerald-600 hover:underline dark:text-emerald-400" href={`/dashboard/routers/${encodeURIComponent(String(row.id))}`}>{display(readPath(row, key))}</Link> : display(readPath(row, key))}</td>)}</tr>)}</tbody></table></div>
          )}
          <footer className="border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">{rows.length} baris ditampilkan. Secret tidak pernah dimuat ke tabel.</footer>
        </section>
      </div>
    </main>
  );
}
