import type { DashboardData } from "./types";

export const demoDashboardData: DashboardData = {
  metrics: [
    { id: "routers", label: "Total Router", value: "12", detail: "10 online · 2 offline", change: "83% sehat", icon: "router", tone: "emerald" },
    { id: "olts", label: "Perangkat OLT", value: "5", detail: "4 online · 1 gangguan", change: "4 POP", icon: "network", tone: "blue" },
    { id: "customers", label: "Total Pelanggan", value: "2.486", detail: "2.318 pelanggan aktif", change: "+34 bulan ini", icon: "users", tone: "violet" },
    { id: "pppoe", label: "PPPoE Online", value: "1.942", detail: "198 sedang offline", change: "90,7% online", icon: "wifi", tone: "amber" },
    { id: "acs", label: "Perangkat ACS", value: "1.962", detail: "1.876 inform hari ini", change: "95,6% terhubung", icon: "radio", tone: "cyan" },
    { id: "vpn", label: "VPN Aktif", value: "67", detail: "54 WireGuard · 13 IKEv2", change: "Semua aman", icon: "shield", tone: "rose" },
  ],
  routers: [
    { id: "r1", name: "CCR-Core-JKT", location: "POP Jakarta", model: "CCR2116-12G-4S+", status: "online", cpu: 24, ram: 42, uptime: "84h 12m", clients: 842 },
    { id: "r2", name: "BNG-Bandung-01", location: "POP Bandung", model: "CCR2004-16G-2S+", status: "online", cpu: 38, ram: 57, uptime: "192h 4m", clients: 526 },
    { id: "r3", name: "RTR-Bogor-Edge", location: "POP Bogor", model: "RB5009UG+S+", status: "warning", cpu: 76, ram: 68, uptime: "26h 48m", clients: 318 },
    { id: "r4", name: "RTR-Depok-02", location: "POP Depok", model: "CCR1009-7G-1C", status: "offline", cpu: 0, ram: 0, uptime: "—", clients: 0 },
  ],
  olts: [
    { id: "o1", name: "OLT-HW-JKT-01", location: "POP Jakarta", model: "Huawei MA5800-X7", status: "online", clients: 684 },
    { id: "o2", name: "OLT-ZTE-BDG-01", location: "POP Bandung", model: "ZTE C320", status: "online", clients: 512 },
    { id: "o3", name: "OLT-FH-BGR-01", location: "POP Bogor", model: "FiberHome AN5516", status: "warning", clients: 398 },
    { id: "o4", name: "OLT-ZTE-DPK-02", location: "POP Depok", model: "ZTE C600", status: "offline", clients: 0 },
  ],
  alerts: [
    { id: "a1", title: "Router tidak dapat dijangkau", description: "RTR-Depok-02 tidak merespons selama 12 menit.", time: "2 menit lalu", severity: "critical" },
    { id: "a2", title: "CPU router tinggi", description: "RTR-Bogor-Edge mencapai 76% selama 10 menit.", time: "8 menit lalu", severity: "warning" },
    { id: "a3", title: "ONT LOS meningkat", description: "14 ONT baru terdeteksi LOS di OLT-FH-BGR-01.", time: "21 menit lalu", severity: "warning" },
    { id: "a4", title: "Backup konfigurasi selesai", description: "Backup otomatis untuk 10 router berhasil disimpan.", time: "1 jam lalu", severity: "info" },
  ],
  activities: [
    { id: "ac1", actor: "Dimas Pratama", action: "mengubah profil PPPoE", target: "Paket Bisnis 100M", time: "5 menit lalu", initials: "DP" },
    { id: "ac2", actor: "Rizky NOC", action: "menjalankan ping test", target: "CCR-Core-JKT", time: "18 menit lalu", initials: "RN" },
    { id: "ac3", actor: "Siti Finance", action: "mengonfirmasi pembayaran", target: "INV-2026-07134", time: "32 menit lalu", initials: "SF" },
    { id: "ac4", actor: "Ahmad Teknisi", action: "memperbarui status ONT", target: "ONT-BOGOR-0178", time: "54 menit lalu", initials: "AT" },
  ],
  traffic: {
    labels: ["00", "02", "04", "06", "08", "10", "12", "14", "16", "18", "20", "22"],
    download: [30, 24, 20, 35, 58, 68, 74, 66, 81, 94, 72, 54],
    upload: [12, 9, 8, 14, 28, 34, 42, 37, 46, 55, 41, 27],
    downloadTotal: "4,82 Gbps",
    uploadTotal: "1,24 Gbps",
  },
  ont: { total: 2184, online: 1876, offline: 222, los: 54, lowOptical: 32 },
  lastUpdated: "Baru saja",
};
