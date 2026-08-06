# Integrasi FreeRADIUS

## Status

Integrasi FreeRADIUS direncanakan pada Tahap 3. Dokumen ini bukan bukti bahwa daemon, schema SQL, NAS, autentikasi, accounting, CoA, atau firewall sudah dikonfigurasi.

## 1. Topologi

```text
Router/NAS -- UDP 1812 auth -----> FreeRADIUS
           -- UDP 1813 acct ----> FreeRADIUS
                                      |
                                      v
                              MariaDB database `radius`
                                      ^
                                      |
Laravel RadiusService -- SQL connection khusus + diagnostic/CoA adapter
```

FreeRADIUS dan core application boleh memakai server MariaDB yang sama, tetapi database user/hak dipisah. Port 1812/1813 hanya menerima IP NAS yang terdaftar melalui private network/VPN/allowlist; tidak dibuka ke seluruh Internet.

## 2. Schema SQL

Gunakan schema resmi versi FreeRADIUS yang dipasang:

- `radcheck`, `radreply` untuk atribut user;
- `radgroupcheck`, `radgroupreply` untuk policy/group reply;
- `radusergroup` untuk membership dan priority;
- `radacct` untuk accounting;
- `radpostauth` untuk hasil autentikasi;
- `nas` untuk client/NAS.

Migration core tidak menebak atau mengganti schema upstream. Upgrade paket harus menyertakan review perubahan schema/index dan backup.

## 3. Ownership dan sinkronisasi

Setiap account memiliki `auth_source` yang eksplisit. Untuk account RADIUS-managed, aplikasi menjadi control plane dan menulis desired state melalui `RadiusService`; router hanya menjadi NAS. Untuk local-managed, aplikasi tidak membuat record RADIUS secara otomatis tanpa pilihan pengguna.

Sinkronisasi menggunakan transaction database RADIUS dan log intent/result pada core. Password PAP yang perlu diverifikasi RADIUS mungkin memerlukan bentuk yang dapat dipulihkan; core menyimpannya encrypted dan menulis sesuai skema/policy FreeRADIUS hanya saat dibutuhkan. Password/ciphertext tidak masuk diff, log, audit, atau reconciliation hash.

Mapping paket awal:

| Semantik aplikasi | Atribut umum | Catatan |
|---|---|---|
| Group paket | `radusergroup.groupname` | nama stabil, priority eksplisit |
| Rate limit MikroTik | `Mikrotik-Rate-Limit` di `radgroupreply` | format dibuat converter dan diuji |
| Expiration | `Expiration` di `radcheck` | format/timezone sesuai modul aktif |
| Simultaneous use | `Simultaneous-Use` | dukungan SQL/check policy diverifikasi |
| Session timeout | `Session-Timeout` | integer detik |

Atribut vendor tidak boleh dianggap aktif hanya karena ada di tabel; dictionary, authorization query, dan policy FreeRADIUS harus diuji end-to-end.

## 4. NAS management

- NAS menyimpan name, IP, type, shortname, secret, active, dan metadata router core.
- Shared secret dibuat random, ditampilkan sekali bila perlu, dan tidak pernah dikembalikan API.
- Schema upstream dapat mewajibkan secret tersedia untuk daemon; lindungi database dengan localhost/private bind, user minimum, host permission, backup encrypted, dan redaction.
- Perubahan NAS/secret memerlukan preview, audit, reload/restart aman sesuai konfigurasi, lalu auth test dari jaringan yang benar.
- Registrasi aplikasi dan firewall harus konsisten; record NAS saja tidak berarti paket firewall menerima trafik.

## 5. Accounting dan usage

- Session aktif umumnya `acctstoptime IS NULL`, tetapi query final harus mengikuti schema/version yang dipasang.
- Identitas session memakai kombinasi NAS/session identifier yang sesuai untuk mencegah collision.
- Interim update router dikonfigurasi agar usage cukup segar tanpa membebani DB.
- Daily/monthly usage dijumlah dari octet gigawords/bytes sesuai atribut schema; tangani counter rollover dan session melintasi batas hari/bulan.
- Query dashboard memakai agregasi/cache, bukan scan seluruh `radacct` saat halaman dibuka.
- Retensi/arsip accounting ditetapkan berdasarkan kebutuhan bisnis/legal dan diuji terhadap index.

## 6. Diagnostik

Halaman diagnostik menampilkan secara aman:

1. status systemd FreeRADIUS;
2. bind/listen UDP 1812/1813 dari host;
3. koneksi database dan query ringan;
4. daftar NAS aktif serta last seen/test state;
5. authentication test terkontrol;
6. accept/reject terbaru dari `radpostauth` tanpa secret;
7. accounting freshness dan backlog.

Authentication test menerima password write-only, tidak menyimpannya, membatasi rate, dan mengaudit actor/username/NAS/outcome. Gunakan tool/protokol lokal yang aman; raw command dan process arguments yang dapat terlihat user lain harus dihindari.

## 7. CoA dan Disconnect

CoA/Disconnect hanya diaktifkan jika NAS, RouterOS, firewall, shared secret, dan policy telah diuji. Adapter membentuk atribut terstruktur, menerapkan timeout/retry terbatas, dan mencatat hasil tersanitasi. Capability ditampilkan per NAS; endpoint mengembalikan `unsupported` jika tidak ada dukungan, bukan berpura-pura berhasil.

Mutasi memerlukan permission, konfirmasi target/session, idempotency key, audit, dan verifikasi melalui accounting/active session bila memungkinkan.

## 8. Security dan firewall

- UFW/nftables allow UDP 1812/1813 hanya dari IP router/NAS; egress/return path diperiksa.
- Management/status port tidak dipublikasi. MariaDB bind private/localhost.
- Secret file/module config dimiliki service account dan tidak berada di web root.
- SQL user daemon hanya memiliki hak terhadap tabel RADIUS yang diperlukan; user diagnostic core lebih terbatas bila memungkinkan.
- Reject message publik tidak membocorkan apakah user ada atau detail policy.
- `radpostauth` dan debug log dapat berisi data sensitif; debug production dimatikan dan log disanitasi/berpermission minimum.

## 9. Mock dan test

Mock `RadiusAdapter`/repository menyediakan accept, reject, timeout, accounting active/closed, duplicate session, dan CoA unsupported. Test minimum:

- converter paket ke atribut dan round-trip yang dapat diverifikasi;
- transaction rollback ketika sync sebagian gagal;
- reconciliation application/router/RADIUS tanpa membandingkan password plaintext;
- auth test redaction dan throttle;
- accounting usage/boundary/counter rollover;
- permission/tenant scope;
- firewall check serta auth/accounting end-to-end pada NAS lab.

Acceptance “RADIUS berhasil” membutuhkan bukti `Access-Accept` **dan** session PPPoE nyata/terkontrol beserta accounting, bukan hanya record SQL atau status daemon.

