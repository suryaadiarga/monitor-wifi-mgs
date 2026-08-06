# Kontrak REST API v1

## Status

Dokumen ini memuat kontrak saat ini dan target lanjutan. Pada snapshot 2026-07-13 terdapat 48 route `/api/v1`; endpoint yang belum tercantum pada `php artisan route:list --path=api/v1` tetap berstatus target.

Base path: `/api/v1`. Media type JSON UTF-8. HTTPS wajib pada production.

## 1. Envelope response

Berhasil:

```json
{
  "success": true,
  "message": "Operasi berhasil",
  "data": {},
  "meta": {},
  "errors": null
}
```

Gagal validasi:

```json
{
  "success": false,
  "message": "Data tidak valid",
  "data": null,
  "meta": { "correlation_id": "01J..." },
  "errors": {
    "email": ["Alamat email tidak valid."]
  }
}
```

`message` ditujukan untuk pengguna dan tidak mengandung stack trace/detail jaringan. `errors` dapat berupa map validasi atau array error domain. Response selalu menyertakan header `X-Correlation-ID`; client boleh mengirim ID valid atau server membuatnya.

## 2. HTTP semantics

| Status | Penggunaan |
|---|---|
| 200 | read/update/action sinkron berhasil |
| 201 | resource dibuat |
| 202 | job/action perangkat diterima; `data.job_id` tersedia |
| 204 | delete/revoke berhasil tanpa body (pengecualian envelope) |
| 400 | request malformed atau precondition bisnis umum |
| 401 | belum/tidak lagi terautentikasi |
| 403 | terautentikasi tetapi permission/scope ditolak |
| 404 | resource tidak ditemukan atau disamarkan karena scope |
| 409 | conflict, duplicate, stale preview, idempotency mismatch |
| 422 | validation/domain rule gagal |
| 429 | rate limit |
| 503 | dependency/circuit unavailable tanpa membocorkan detail |

Tanggal menggunakan ISO-8601 UTC, contoh `2026-07-13T03:00:00Z`. Bandwidth berbentuk integer bps; byte counter integer; uang string desimal untuk menghindari error floating point.

## 3. Autentikasi

Implementasi Tahap 1 memakai token Bearer Sanctum. Karena credential tidak dikirim otomatis oleh browser, endpoint API ini tidak bergantung pada cookie/CSRF. Deployment first-party yang kelak memakai session cookie wajib mengaktifkan Sanctum stateful cookie, HttpOnly/Secure/SameSite, dan CSRF cookie. Alur saat ini:

```text
POST /api/v1/auth/login
GET  /api/v1/auth/me
POST /api/v1/auth/logout
```

Endpoint target:

| Method/path | Fungsi | Auth |
|---|---|---|
| `POST /auth/login` | membuat token Sanctum; rate limited; audit success/failure | publik |
| `POST /auth/logout` | revoke session saat ini | user |
| `POST /auth/logout-all` | revoke seluruh session user | user + re-auth |
| `GET /auth/me` | profil, role/permission efektif, feature flags aman | user |
| `PUT /auth/password` | ubah password dan revoke session lain | user + current password |
| `POST /auth/forgot-password` | kirim reset tanpa account enumeration | publik, throttled |
| `POST /auth/reset-password` | reset dengan token satu kali | publik, throttled |
| `GET /auth/sessions` | daftar session tersanitasi | user |
| `DELETE /auth/sessions/{id}` | revoke session | user/owner |

Token Sanctum sudah tersedia untuk API/mobile. Hardening berikutnya adalah expiry/scope token dan migrasi web production ke cookie HttpOnly agar token tidak perlu disimpan di localStorage.

## 4. Query koleksi

Konvensi:

```text
?page=1&per_page=25
?search=surya
?sort=-last_seen,name
?filter[status]=online
?filter[area_id]=01J...
?include=area,pop
```

- `per_page` default 25, maksimum 100 kecuali export async.
- Field sort/filter/include wajib allowlist per resource.
- `meta` berisi `current_page`, `per_page`, `from`, `to`, `total`, `last_page` dan opsional `filters`.
- Export besar dikirim sebagai job dan menghasilkan file privat dengan URL bertanda tangan dan expiry pendek.

## 5. Resource dan endpoint utama

Semua endpoint di bawah berada di `/api/v1`. CRUD berarti `GET collection`, `POST collection`, `GET item`, `PUT/PATCH item`, dan `DELETE item` bila delete diizinkan.

### Platform dan dashboard

| Endpoint | Fungsi |
|---|---|
| `GET /dashboard` | agregat cached beserta waktu snapshot |
| `GET /dashboard/traffic` | seri trafik teragregasi berdasarkan filter/rentang |
| `GET /system/health` | health aman untuk operator |
| `GET /system/health/live` | liveness minimal |
| `GET /system/health/ready` | readiness tanpa secret |
| CRUD `/users`, `/roles`, `/permissions` | user dan RBAC dengan permission khusus |
| CRUD `/areas`, `/pops`, `/odps` | topologi lokasi |
| CRUD `/system-settings` | setting bertipe; secret memakai endpoint rotate |

### Router dan access service

| Endpoint | Fungsi |
|---|---|
| CRUD `/routers` | metadata router; response credential write-only |
| `POST /routers/{router}/connection-tests` | enqueue test koneksi tersanitasi |
| `GET /routers/{router}/metrics` | current/historical metric |
| `GET /routers/{router}/interfaces` | interface dan snapshot trafik |
| `POST /routers/{router}/polls` | manual poll terbatas/throttled |
| `POST /routers/{router}/backups` | enqueue backup/export |
| `GET /routers/{router}/commands` | histori command tersanitasi |
| CRUD `/pppoe/accounts` | account aplikasi/local/RADIUS |
| `GET /pppoe/sessions` | active/history dengan filter |
| `POST /pppoe/sessions/{session}/disconnect` | enqueue disconnect idempotent |
| `POST /pppoe/syncs` | preview sinkronisasi |
| `POST /pppoe/syncs/{preview}/apply` | apply pilihan resolusi |
| `GET /pppoe/reconciliations` | run/item drift tiga sumber |
| CRUD `/hotspot/servers`, `/hotspot/profiles`, `/hotspot/users` | konfigurasi Hotspot |
| `GET /hotspot/sessions` | active/history |
| `POST /hotspot/sessions/{session}/disconnect` | disconnect ter-audit |
| `POST /hotspot/voucher-batches` | generate batch async |
| `POST /hotspot/voucher-batches/{id}/pdf` | render PDF privat |

### Customer dan package

| Endpoint | Fungsi |
|---|---|
| CRUD `/customers` | pelanggan sesuai scope reseller/assignment |
| `GET /customers/{customer}/history` | perubahan/koneksi/paket/perangkat |
| `POST /customers/imports` | upload dan validasi CSV ke preview |
| `POST /customers/imports/{preview}/apply` | apply baris valid terpilih |
| `POST /customers/exports` | export async sesuai filter/scope |
| `POST /customers/bulk-previews` | diff/count/warning tanpa mutasi |
| `POST /customers/bulk-actions` | apply menggunakan preview token + idempotency key |
| CRUD `/packages` | paket dan mapping akses |
| `POST /packages/{package}/apply-previews` | pelanggan terdampak dan diff |
| `POST /packages/{package}/apply-actions` | apply target terpilih/all hasil preview |

### RADIUS, GenieACS, dan OLT

| Endpoint | Fungsi |
|---|---|
| CRUD `/radius/nas`, `/radius/users`, `/radius/groups` | pengelolaan SQL melalui service |
| `GET /radius/accounting` | sesi/usage terfilter |
| `GET /radius/authentication-logs` | accept/reject tersanitasi |
| `GET /radius/health` | service/port/DB/NAS summary |
| `POST /radius/authentication-tests` | test dengan secret input write-only |
| `POST /radius/sessions/{id}/disconnect` | CoA/Disconnect bila capability ada |
| `GET /genieacs/devices` | inventory snapshot |
| `GET /genieacs/devices/{device}` | detail semantic parameter |
| `GET /genieacs/devices/{device}/tasks` | task/fault history |
| `POST /genieacs/devices/{device}/tasks` | allowlisted refresh/set/reboot |
| `POST /genieacs/devices/{device}/factory-reset-previews` | challenge dan dampak |
| `POST /genieacs/devices/{device}/factory-resets` | serial challenge + permission + idempotency |
| CRUD `/genieacs/parameter-mappings` | mapping versioned dan test mapping |
| CRUD `/olts` | OLT metadata/secret write-only |
| `POST /olts/{olt}/connection-tests` | connection/capability test |
| `GET /olts/{olt}/boards`, `/ports`, `/onts` | inventory hierarchy |
| `GET /onts/{ont}`, `/onts/{ont}/optical-history` | status dan optical |
| `POST /onts/{ont}/action-previews` | preview description/reboot/enable/disable/provision |
| `POST /onts/{ont}/actions` | enqueue command terverifikasi |

### VPN, alert, audit, backup

| Endpoint | Fungsi |
|---|---|
| CRUD `/vpn/servers` | metadata/service configuration tersanitasi |
| CRUD `/vpn/clients` | client lifecycle; secret hanya response create satu kali |
| `POST /vpn/clients/{id}/configuration` | one-time provisioning artifact/QR, re-auth |
| `POST /vpn/clients/{id}/rotate` | rotate keys dan revoke artifact lama |
| CRUD `/alert-rules` | rule dan scope |
| `GET /alerts` | list/filter |
| `POST /alerts/{id}/acknowledge`, `/resolve` | transisi state audited |
| CRUD `/telegram-settings` | bot token write-only |
| `POST /telegram-settings/{id}/tests` | enqueue test message |
| `GET /audit-logs` | read-only, permission ketat, field secret sudah redacted |
| `GET /jobs/{id}` | status job milik/yang dapat diakses actor |
| `GET /backups` | metadata backup |
| `POST /backups` | enqueue backup sesuai type |
| `POST /backups/{id}/verifications` | checksum/readability test |

## 6. Job dan action asynchronous

Response `202`:

```json
{
  "success": true,
  "message": "Perintah dijadwalkan",
  "data": {
    "job_id": "01J...",
    "command_id": "01J...",
    "status": "queued"
  },
  "meta": { "correlation_id": "01J..." },
  "errors": null
}
```

Client memeriksa `GET /jobs/{job}` atau memakai event channel kelak. Status standar: `queued`, `running`, `succeeded`, `failed`, `cancelled`, `expired`. `succeeded` berarti adapter mengembalikan hasil yang tervalidasi, bukan jaminan state perangkat tanpa read-back; response harus menyatakan `verification_status`.

## 7. Preview, idempotency, dan concurrency

- Preview menghasilkan ID/token acak, hash input, actor/scope, diff, warning, count, dan `expires_at`. Token sekali pakai atau ditandai applied.
- Apply wajib mengirim `preview_id` dan header `Idempotency-Key`. Key terikat method/path/actor/request hash selama minimal 24 jam untuk mutasi perangkat/bulk.
- Reuse key dengan payload berbeda menghasilkan `409`.
- Update resource membawa `If-Match`/version atau `updated_at`; stale update menghasilkan `409`.
- Retry HTTP hanya aman pada GET atau mutasi dengan idempotency key.

## 8. Secret contract

Contoh create/update router menerima:

```json
{
  "name": "POP Timur",
  "host": "10.20.0.1",
  "username": "api-isp",
  "password": "nilai-sementara-dari-pengguna"
}
```

Read selalu mengembalikan bentuk berikut, bukan password/ciphertext:

```json
{
  "name": "POP Timur",
  "host": "10.20.0.1",
  "has_password": true,
  "credential_updated_at": "2026-07-13T03:00:00Z"
}
```

Empty/missing secret pada update berarti tidak berubah; rotate/clear harus eksplisit agar form kosong tidak menghapus credential.

## 9. Authorization dan audit

Setiap route memiliki middleware authentication, permission, dan policy scope. Job mengulang pemeriksaan actor/intent yang relevan sebelum eksekusi, terutama jika menunggu lama. Audit mencatat aksi berhasil/gagal, tetapi tidak mencatat raw request. Field redaction dilakukan sebelum log/audit/persistence payload teknis.

## 10. OpenAPI

Spesifikasi machine-readable tersedia di [`documentation/openapi.yaml`](openapi.yaml). File tersebut mencakup kontrak Tahap 1 utama dan akan diperluas bersama endpoint berikutnya. CI production kelak wajib memvalidasi schema, contoh envelope, security scheme, status code, dan breaking changes.
