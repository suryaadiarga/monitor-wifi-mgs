# Frontend ISP Terpadu

Antarmuka Next.js 16 + TypeScript + Tailwind CSS untuk aplikasi ISP Terpadu. Tersedia `/login`, `/dashboard`, dan `/dashboard/[module]` untuk tabel Router, Customer, Paket, PPPoE, Hotspot, RADIUS, GenieACS, OLT, VPN, Alert, Audit, User, Telegram, serta health. UI mendukung mode gelap, layout responsif, pencarian, loading/error/empty state, dan export CSV.

## Menjalankan lokal

```bash
npm ci
cp .env.example .env.local
npm run dev
```

`NEXT_PUBLIC_API_BASE_URL` harus menunjuk ke backend Laravel `/api/v1`. Jika backend tidak dapat dijangkau, dashboard masuk ke mode demo dan menampilkannya secara eksplisit.

## Verifikasi

```bash
npm run lint
npm run build
npm audit --omit=dev
```

Node.js minimal 22.13. Dokumentasi lengkap berada di [`../README.md`](../README.md).
