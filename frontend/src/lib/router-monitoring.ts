export type RouterRow = Record<string, unknown>;

export interface RouterMetricSnapshot {
  cpu_percent?: number | null;
  memory_percent?: number | null;
  storage_percent?: number | null;
  temperature_celsius?: number | null;
  voltage?: number | null;
  uptime?: string | null;
  uptime_seconds?: number | null;
  free_memory?: number | null;
  total_memory?: number | null;
  free_storage?: number | null;
  total_storage?: number | null;
}

export interface RouterDetail {
  id: number | string;
  name: string;
  host: string;
  api_port?: number | null;
  use_ssl?: boolean;
  status?: string | null;
  identity?: string | null;
  routeros_version?: string | null;
  architecture?: string | null;
  model?: string | null;
  board_name?: string | null;
  serial_number?: string | null;
  uptime?: string | null;
  last_connected_at?: string | null;
  last_successful_sync_at?: string | null;
  last_sync_at?: string | null;
  last_seen_at?: string | null;
  last_error?: string | null;
  latency_ms?: number | null;
  response_latency_ms?: number | null;
  credential_configured?: boolean;
  enabled?: boolean;
  counts?: Record<string, number>;
  latest_resource?: RouterMetricSnapshot | null;
  latest_metric?: RouterMetricSnapshot | null;
  resources?: RouterMetricSnapshot | null;
}

export interface RouterTableColumn {
  key: string;
  label: string;
  sortable?: boolean;
  kind?: "text" | "status" | "boolean" | "bytes" | "date" | "duration";
}

export interface RouterTabDefinition {
  id: string;
  label: string;
  endpoint?: string;
  columns?: RouterTableColumn[];
  filterLabel?: string;
  filterParam?: string;
  filters?: Array<{ value: string; label: string }>;
  defaultSort?: string;
}

const statusFilter = [
  { value: "", label: "Semua status" },
  { value: "online", label: "Online" },
  { value: "offline", label: "Offline" },
  { value: "disabled", label: "Disabled" },
];

export const routerTabs: RouterTabDefinition[] = [
  { id: "overview", label: "Overview" },
  {
    id: "interfaces",
    label: "Interfaces",
    endpoint: "interfaces",
    defaultSort: "name",
    filterLabel: "Status interface",
    filterParam: "status",
    filters: statusFilter,
    columns: [
      { key: "name", label: "Nama", sortable: true },
      { key: "type", label: "Tipe", sortable: true },
      { key: "running", label: "Running", sortable: true, kind: "boolean" },
      { key: "dynamic", label: "Dynamic", sortable: true, kind: "boolean" },
      { key: "disabled", label: "Disabled", sortable: true, kind: "boolean" },
      { key: "actual_mtu", label: "Actual MTU", sortable: true },
      { key: "mac_address", label: "MAC address" },
      { key: "rx_bytes", label: "RX", sortable: true, kind: "bytes" },
      { key: "tx_bytes", label: "TX", sortable: true, kind: "bytes" },
      { key: "link_downs", label: "Link down", sortable: true },
      { key: "last_seen_at", label: "Terakhir terlihat", sortable: true, kind: "date" },
    ],
  },
  {
    id: "pppoe-active",
    label: "PPPoE Active",
    endpoint: "pppoe-active",
    defaultSort: "name",
    columns: [
      { key: "name", label: "User", sortable: true },
      { key: "service", label: "Service", sortable: true },
      { key: "caller_id", label: "Caller ID", sortable: true },
      { key: "address", label: "Address", sortable: true },
      { key: "uptime", label: "Uptime", sortable: true, kind: "duration" },
      { key: "encoding", label: "Encoding" },
      { key: "interface", label: "Interface", sortable: true },
      { key: "profile", label: "Profile", sortable: true },
      { key: "last_seen_at", label: "Terakhir terlihat", sortable: true, kind: "date" },
    ],
  },
  {
    id: "ppp-secrets",
    label: "PPP Secrets",
    endpoint: "ppp-secrets",
    defaultSort: "name",
    filterLabel: "Status akun",
    filterParam: "status",
    filters: statusFilter,
    columns: [
      { key: "name", label: "User", sortable: true },
      { key: "service", label: "Service", sortable: true },
      { key: "profile", label: "Profile", sortable: true },
      { key: "local_address", label: "Local address", sortable: true },
      { key: "remote_address", label: "Remote address", sortable: true },
      { key: "disabled", label: "Disabled", sortable: true, kind: "boolean" },
      { key: "comment", label: "Komentar" },
      { key: "last_seen_at", label: "Terakhir terlihat", sortable: true, kind: "date" },
    ],
  },
  {
    id: "ppp-profiles",
    label: "PPP Profiles",
    endpoint: "ppp-profiles",
    defaultSort: "name",
    columns: [
      { key: "name", label: "Nama", sortable: true },
      { key: "local_address", label: "Local address", sortable: true },
      { key: "remote_address_pool", label: "Remote pool", sortable: true },
      { key: "rate_limit", label: "Rate limit", sortable: true },
      { key: "session_timeout", label: "Session timeout", kind: "duration" },
      { key: "only_one", label: "Only one", kind: "boolean" },
      { key: "last_seen_at", label: "Terakhir terlihat", sortable: true, kind: "date" },
    ],
  },
  {
    id: "hotspot-active",
    label: "Hotspot Active",
    endpoint: "hotspot-active",
    defaultSort: "user",
    columns: [
      { key: "user", label: "User", sortable: true },
      { key: "address", label: "Address", sortable: true },
      { key: "mac_address", label: "MAC address", sortable: true },
      { key: "server", label: "Server", sortable: true },
      { key: "login_by", label: "Login by", sortable: true },
      { key: "uptime", label: "Uptime", sortable: true, kind: "duration" },
      { key: "idle_time", label: "Idle", sortable: true, kind: "duration" },
      { key: "last_seen_at", label: "Terakhir terlihat", sortable: true, kind: "date" },
    ],
  },
  {
    id: "hotspot-users",
    label: "Hotspot Users",
    endpoint: "hotspot-users",
    defaultSort: "name",
    filterLabel: "Status user",
    filterParam: "status",
    filters: statusFilter,
    columns: [
      { key: "name", label: "User", sortable: true },
      { key: "profile", label: "Profile", sortable: true },
      { key: "server", label: "Server", sortable: true },
      { key: "mac_address", label: "MAC address", sortable: true },
      { key: "disabled", label: "Disabled", sortable: true, kind: "boolean" },
      { key: "limit_uptime", label: "Limit uptime", sortable: true, kind: "duration" },
      { key: "comment", label: "Komentar" },
      { key: "last_seen_at", label: "Terakhir terlihat", sortable: true, kind: "date" },
    ],
  },
  {
    id: "dhcp-leases",
    label: "DHCP Leases",
    endpoint: "dhcp-leases",
    defaultSort: "address",
    filterLabel: "Status lease",
    filterParam: "status",
    filters: [
      { value: "", label: "Semua status" },
      { value: "bound", label: "Bound" },
      { value: "waiting", label: "Waiting" },
      { value: "offered", label: "Offered" },
      { value: "expired", label: "Expired" },
    ],
    columns: [
      { key: "address", label: "Address", sortable: true },
      { key: "mac_address", label: "MAC address", sortable: true },
      { key: "host_name", label: "Hostname", sortable: true },
      { key: "status", label: "Status", sortable: true, kind: "status" },
      { key: "server", label: "Server", sortable: true },
      { key: "dynamic", label: "Dynamic", sortable: true, kind: "boolean" },
      { key: "expires_after", label: "Kedaluwarsa", sortable: true, kind: "duration" },
      { key: "last_seen_at", label: "Terakhir terlihat", sortable: true, kind: "date" },
    ],
  },
  {
    id: "queues",
    label: "Queues",
    endpoint: "queues",
    defaultSort: "name",
    filterLabel: "Tipe queue",
    filterParam: "type",
    filters: [
      { value: "", label: "Semua tipe" },
      { value: "simple", label: "Simple" },
      { value: "tree", label: "Tree" },
      { value: "type", label: "Queue type" },
    ],
    columns: [
      { key: "name", label: "Nama", sortable: true },
      { key: "type", label: "Tipe", sortable: true },
      { key: "target", label: "Target", sortable: true },
      { key: "parent", label: "Parent", sortable: true },
      { key: "max_limit", label: "Max limit", sortable: true },
      { key: "rate", label: "Rate", sortable: true },
      { key: "disabled", label: "Disabled", sortable: true, kind: "boolean" },
      { key: "last_seen_at", label: "Terakhir terlihat", sortable: true, kind: "date" },
    ],
  },
  {
    id: "sync-history",
    label: "Sync History",
    endpoint: "sync-history",
    defaultSort: "started_at",
    filterLabel: "Status sinkronisasi",
    filterParam: "status",
    filters: [
      { value: "", label: "Semua status" },
      { value: "success", label: "Berhasil" },
      { value: "running", label: "Berjalan" },
      { value: "failed", label: "Gagal" },
      { value: "skipped", label: "Dilewati" },
    ],
    columns: [
      { key: "correlation_id", label: "Correlation ID", sortable: true },
      { key: "scope", label: "Scope", sortable: true },
      { key: "status", label: "Status", sortable: true, kind: "status" },
      { key: "started_at", label: "Mulai", sortable: true, kind: "date" },
      { key: "finished_at", label: "Selesai", sortable: true, kind: "date" },
      { key: "duration_ms", label: "Durasi", sortable: true },
      { key: "items_synced", label: "Item" },
      { key: "error_summary", label: "Error terakhir" },
    ],
  },
  {
    id: "audit-log",
    label: "Audit Log",
    endpoint: "audit-logs",
    defaultSort: "created_at",
    filterLabel: "Status audit",
    filterParam: "status",
    filters: [
      { value: "", label: "Semua status" },
      { value: "success", label: "Berhasil" },
      { value: "failed", label: "Gagal" },
    ],
    columns: [
      { key: "action", label: "Aksi", sortable: true },
      { key: "status", label: "Status", sortable: true, kind: "status" },
      { key: "actor.name", label: "Aktor" },
      { key: "correlation_id", label: "Correlation ID", sortable: true },
      { key: "ip_address", label: "IP" },
      { key: "created_at", label: "Waktu", sortable: true, kind: "date" },
    ],
  },
];

export function readRouterValue(row: RouterRow, path: string): unknown {
  return path.split(".").reduce<unknown>((value, key) => value && typeof value === "object" ? (value as RouterRow)[key] : undefined, row);
}

export function formatBytes(value: unknown): string {
  const bytes = Number(value);
  if (!Number.isFinite(bytes) || bytes < 0) return "—";
  if (bytes < 1024) return `${bytes} B`;
  const units = ["KB", "MB", "GB", "TB"];
  let amount = bytes / 1024;
  let unit = units[0];
  for (let index = 1; index < units.length && amount >= 1024; index += 1) {
    amount /= 1024;
    unit = units[index];
  }
  return `${amount.toLocaleString("id-ID", { maximumFractionDigits: 1 })} ${unit}`;
}

export function formatRouterValue(value: unknown, kind: RouterTableColumn["kind"] = "text"): string {
  if (value === null || value === undefined || value === "") return "—";
  if (kind === "bytes") return formatBytes(value);
  if (kind === "date") {
    const date = new Date(String(value));
    return Number.isNaN(date.valueOf()) ? String(value) : date.toLocaleString("id-ID");
  }
  if (typeof value === "boolean") return value ? "Ya" : "Tidak";
  if (Array.isArray(value)) return value.map((item) => String(item)).join(", ");
  if (typeof value === "object") return "—";
  return String(value);
}
