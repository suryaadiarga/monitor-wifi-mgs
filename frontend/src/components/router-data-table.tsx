"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Icon } from "@/components/icon";
import { apiFetchEnvelope } from "@/lib/api";
import {
  formatRouterValue,
  readRouterValue,
  type RouterRow,
  type RouterTabDefinition,
  type RouterTableColumn,
} from "@/lib/router-monitoring";

interface PaginationMeta {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

const emptyMeta: PaginationMeta = { currentPage: 1, lastPage: 1, perPage: 15, total: 0 };

function numberMeta(meta: Record<string, unknown>, camelKey: string, snakeKey: string, fallback: number): number {
  const value = Number(meta[snakeKey] ?? meta[camelKey]);
  return Number.isFinite(value) && value >= 0 ? value : fallback;
}

function normalizeMeta(meta: Record<string, unknown>, rows: RouterRow[], requestedPage: number, perPage: number): PaginationMeta {
  const total = numberMeta(meta, "total", "total", rows.length);
  const currentPage = numberMeta(meta, "currentPage", "current_page", requestedPage);
  const inferredLastPage = Math.max(1, Math.ceil(total / perPage));
  return {
    currentPage,
    lastPage: numberMeta(meta, "lastPage", "last_page", inferredLastPage),
    perPage: numberMeta(meta, "perPage", "per_page", perPage),
    total,
  };
}

function normalizeRows(payload: unknown): RouterRow[] {
  if (Array.isArray(payload)) return payload as RouterRow[];
  if (payload && typeof payload === "object" && Array.isArray((payload as RouterRow).data)) {
    return (payload as { data: RouterRow[] }).data;
  }
  return [];
}

function statusClasses(value: string): string {
  const normalized = value.toLowerCase();
  if (["online", "success", "bound", "running", "active", "up"].includes(normalized)) {
    return "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300";
  }
  if (["failed", "offline", "expired", "down", "error"].includes(normalized)) {
    return "bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300";
  }
  return "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300";
}

function CellValue({ column, value }: { column: RouterTableColumn; value: unknown }) {
  if (column.kind === "boolean") {
    const active = value === true || value === 1 || value === "1" || value === "true" || value === "yes";
    return <span className={`inline-flex rounded-full px-2 py-1 text-[10px] font-bold ${active ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300" : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"}`}>{active ? "Ya" : "Tidak"}</span>;
  }
  const formatted = formatRouterValue(value, column.kind);
  if (column.kind === "status") {
    return <span className={`inline-flex rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${statusClasses(formatted)}`}>{formatted}</span>;
  }
  return <span title={formatted}>{formatted}</span>;
}

function TableSkeleton({ columns }: { columns: number }) {
  return (
    <div aria-label="Memuat data router" className="space-y-3 p-5" role="status">
      {Array.from({ length: 6 }, (_, row) => (
        <div className="grid animate-pulse gap-3" key={row} style={{ gridTemplateColumns: `repeat(${Math.min(columns, 6)}, minmax(90px, 1fr))` }}>
          {Array.from({ length: Math.min(columns, 6) }, (_, column) => <span className="h-8 rounded-lg bg-slate-100 dark:bg-slate-800" key={column} />)}
        </div>
      ))}
    </div>
  );
}

export function RouterDataTable({ routerId, tab }: { routerId: string; tab: RouterTabDefinition }) {
  const [rows, setRows] = useState<RouterRow[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>(emptyMeta);
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [sort, setSort] = useState(tab.defaultSort ?? "updated_at");
  const [direction, setDirection] = useState<"asc" | "desc">(tab.id === "sync-history" || tab.id === "audit-log" ? "desc" : "asc");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setPage(1);
      setSearch(searchInput.trim());
    }, 350);
    return () => window.clearTimeout(timer);
  }, [searchInput]);

  const query = useMemo(() => {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
      sort,
      direction,
    });
    if (search) params.set("search", search);
    if (filter && tab.filterParam) params.set(tab.filterParam, filter);
    return params.toString();
  }, [direction, filter, page, perPage, search, sort, tab.filterParam]);

  const load = useCallback(async () => {
    if (!tab.endpoint) return;
    setLoading(true);
    setError(null);
    try {
      const response = await apiFetchEnvelope<unknown>(`/routers/${encodeURIComponent(routerId)}/${tab.endpoint}?${query}`);
      const nextRows = normalizeRows(response.data);
      setRows(nextRows);
      setMeta(normalizeMeta(response.meta, nextRows, page, perPage));
    } catch (exception) {
      setRows([]);
      setError(exception instanceof Error ? exception.message : "Data router gagal dimuat.");
    } finally {
      setLoading(false);
    }
  }, [page, perPage, query, routerId, tab.endpoint]);

  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  function changeSort(column: RouterTableColumn) {
    if (!column.sortable) return;
    setPage(1);
    if (sort === column.key) setDirection((current) => current === "asc" ? "desc" : "asc");
    else {
      setSort(column.key);
      setDirection("asc");
    }
  }

  return (
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 lg:flex-row lg:items-center">
        <div className="relative min-w-0 flex-1">
          <Icon className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" name="search" />
          <input
            aria-label={`Cari pada ${tab.label}`}
            className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm outline-none transition focus:border-emerald-500 dark:border-slate-700 dark:bg-slate-800"
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder={`Cari ${tab.label.toLowerCase()}...`}
            value={searchInput}
          />
        </div>
        {tab.filters && (
          <select
            aria-label={tab.filterLabel}
            className="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-800"
            onChange={(event) => { setFilter(event.target.value); setPage(1); }}
            value={filter}
          >
            {tab.filters.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
          </select>
        )}
        <label className="flex items-center gap-2 text-xs font-semibold text-slate-500">
          Per halaman
          <select
            aria-label="Jumlah baris per halaman"
            className="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            onChange={(event) => { setPerPage(Number(event.target.value)); setPage(1); }}
            value={perPage}
          >
            {[15, 25, 50].map((size) => <option key={size} value={size}>{size}</option>)}
          </select>
        </label>
        <button className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60" disabled={loading} onClick={() => void load()} type="button">
          <Icon className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} name="refresh" /> Refresh
        </button>
      </div>

      {loading ? <TableSkeleton columns={tab.columns?.length ?? 5} /> : error ? (
        <div className="m-5 rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300" role="alert">
          <p className="font-bold">Data tidak dapat dimuat</p>
          <p className="mt-1">{error}</p>
          <button className="mt-3 rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white" onClick={() => void load()} type="button">Coba lagi</button>
        </div>
      ) : rows.length === 0 ? (
        <div className="p-12 text-center">
          <span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"><Icon name="search" /></span>
          <p className="mt-4 text-sm font-bold text-slate-700 dark:text-slate-200">Tidak ada data</p>
          <p className="mt-1 text-xs text-slate-500">Ubah pencarian/filter atau tunggu sinkronisasi router berikutnya.</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
              <tr>
                {tab.columns?.map((column) => (
                  <th className="whitespace-nowrap px-4 py-3" key={column.key}>
                    <button className={`inline-flex items-center gap-1 font-bold ${column.sortable ? "hover:text-emerald-600" : "cursor-default"}`} disabled={!column.sortable} onClick={() => changeSort(column)} type="button">
                      {column.label}
                      {column.sortable && sort === column.key && <span aria-label={direction === "asc" ? "Urut naik" : "Urut turun"}>{direction === "asc" ? "↑" : "↓"}</span>}
                    </button>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => (
                <tr className="border-t border-slate-100 transition hover:bg-slate-50/80 dark:border-slate-800 dark:hover:bg-slate-800/40" key={String(row.id ?? row.external_id ?? `${page}-${index}`)}>
                  {tab.columns?.map((column) => <td className="max-w-xs whitespace-nowrap px-4 py-3 text-xs text-slate-700 dark:text-slate-300" key={column.key}><CellValue column={column} value={readRouterValue(row, column.key)} /></td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <footer className="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
        <p>{meta.total.toLocaleString("id-ID")} data · halaman {meta.currentPage} dari {meta.lastPage}</p>
        <div className="flex gap-2">
          <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700" disabled={loading || page <= 1} onClick={() => setPage((current) => Math.max(1, current - 1))} type="button">Sebelumnya</button>
          <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700" disabled={loading || page >= meta.lastPage} onClick={() => setPage((current) => current + 1)} type="button">Berikutnya</button>
        </div>
      </footer>
    </section>
  );
}
