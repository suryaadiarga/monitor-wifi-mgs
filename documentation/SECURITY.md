# Baseline Keamanan

## Status dan prinsip

Dokumen ini memuat target kontrol keamanan dan bukti yang sudah diverifikasi. Fondasi production dan integrasi MikroTik read-only telah melalui pemeriksaan terarah pada 2026-07-13, tetapi ini bukan pengganti audit keamanan independen atau penetration test. Prinsip utama: deny by default, least privilege, defense in depth, secret write-only, fail closed, dan setiap mutasi dapat diaudit.

## 1. Aset dan ancaman utama

Aset paling sensitif adalah credential router/OLT/SNMP/RADIUS/ACS, private key/PSK VPN, token Telegram, data pelanggan, konfigurasi jaringan, session administrator, backup, serta kemampuan menjalankan command perangkat.

Ancaman prioritas:

- pencurian akun/session dan brute force;
- privilege escalation atau kebocoran lintas reseller/assignment;
- SSRF melalui host perangkat yang dimasukkan pengguna;
- command injection pada SSH/RouterOS/OS service;
- secret bocor melalui response, log, audit, queue payload, backup, atau frontend;
- perubahan massal/salah perangkat tanpa preview;
- supply-chain dependency dan file upload berbahaya;
- service database/RADIUS/ACS/SNMP terekspos Internet;
- replay/retry action yang menghasilkan mutasi ganda;
- kompromi worker/VPS yang membuka seluruh credential.

## 2. Authentication dan session

- Password memakai Argon2id atau hash adaptif yang didukung dan dikalibrasi; tidak pernah dapat dipulihkan.
- First-party web memakai Sanctum stateful cookie `HttpOnly`, `Secure` production, dan `SameSite=Lax/Strict` sesuai topologi; CSRF wajib.
- Session ID dirotasi saat login/peningkatan privilege. Logout/revoke menghapus server-side session.
- Login dan reset password memakai throttle berdasarkan akun + IP tanpa account enumeration.
- Audit login mencatat outcome, IP, user-agent, correlation ID; tidak mencatat password/token.
- Ubah password/recovery merevoke session lain. Aksi sangat sensitif memakai recent re-authentication.
- MFA TOTP/WebAuthn adalah fitur opsional yang diprioritaskan untuk Super Admin; recovery code disimpan hash dan ditampilkan sekali.

## 3. Authorization

Permission diperiksa di backend melalui policy/gate dan object scope. UI hanya membantu pengalaman pengguna.

| Area | Super Admin | Administrator | NOC | Teknisi | Finance | Reseller |
|---|---|---|---|---|---|---|
| Monitoring | semua | semua | read/diagnostic | assigned only | terbatas | own only |
| Credential | manage | sesuai delegasi | tidak | tidak | tidak | tidak |
| Customer | semua | manage | read | assigned | finance fields | own only |
| PPP/Hotspot | semua | manage | diagnostic/reconnect | read assigned | tidak | own delegated |
| OLT/ONT action | semua | manage | limited | limited assigned | tidak | tidak |
| RBAC/system | semua | terbatas | tidak | tidak | tidak | tidak |
| Audit | semua | sesuai permission | read terbatas | tidak | terbatas | own events bila perlu |

Matriks rinci diwujudkan sebagai kode permission stabil. Role tidak menggantikan scope query: setiap customer/resource yang dimiliki reseller atau ditugaskan teknisi harus difilter sebelum resolve/serialize.

## 4. Pengelolaan secret

- Secret dienkripsi di application layer sebelum MariaDB dan diakses hanya melalui service khusus.
- Ciphertext/key material tidak masuk mass assignment, serializer, cache biasa, queue payload, exception context, audit, export, atau telemetry.
- API read mengembalikan flag `has_*`, bukan nilai secret. Create/provision tertentu boleh memberi secret sekali melalui response yang tidak dicache, lalu segera tidak dapat dibaca lagi.
- Key enkripsi utama berada di secret file/environment dengan permission minimum, di luar Git; production perlu prosedur rotation/versioning dan escrow recovery.
- Secret berbeda untuk setiap service/perangkat; rotasi tidak menimpa history audit dengan nilai asli.
- Backup yang berisi `.env`, key, database, atau konfigurasi VPN wajib dienkripsi dengan key terpisah dan diuji restore.
- Shared secret FreeRADIUS yang harus tersedia bagi daemon dilindungi dengan database user khusus, host permission, network isolation, backup encryption, dan redaction ketat.

### Bukti secret handling MikroTik production

- Credential RouterOS disimpan terenkripsi dan hanya didekripsi sesaat pada boundary koneksi.
- Pemeriksaan source, application log, audit record, response API, dan database plaintext tidak menemukan password, token, atau credential router.
- Dataset PPP secret (119 record) dan Hotspot user (3 record) secara eksplisit tidak mengambil atau menyimpan field password.
- Backup sebelum perubahan `20260713-010656`, backup deployment `20260713-014703`, dan backup pascaintegrasi `20260713-015516` dibuat terenkripsi; snapshot pascaintegrasi telah diverifikasi.
- Tidak ada nilai secret yang didokumentasikan. Credential harus dirotasi melalui secret path operasional, bukan source, CLI argument, log, atau tiket.

## 5. Input, output, dan integrasi perangkat

- FormRequest/schema validation mengatur tipe, panjang, enum, format IP/CIDR, ukuran file, dan allowed fields.
- Query sort/filter/include memakai allowlist; Eloquent binding/prepared statement wajib.
- Host perangkat divalidasi dan diperiksa terhadap policy jaringan untuk mencegah SSRF ke metadata/cloud/localhost/service internal yang tidak diizinkan. DNS resolution/rebinding harus diperhatikan.
- Perintah perangkat dibangun dari parameter terstruktur dan allowlist, bukan konkatenasi shell.
- SSH host key/TLS certificate diverifikasi; opsi insecure hanya untuk lab dengan warning/audit dan default off.
- Telnet OLT default nonaktif. RouterOS script hanya ID script yang di-allowlist, tanpa input command bebas.
- Output vendor diperlakukan sebagai input tidak tepercaya, dibatasi ukuran/waktu, diparse, dan disanitasi sebelum log/tampilan.
- Upload CSV/template memakai batas ukuran/MIME, nama file acak, penyimpanan non-public, dan formula-injection mitigation saat export CSV.

## 6. Aksi berbahaya

- Bulk, package rollout, sync, dan provisioning: preview -> review diff/count/warning -> apply dengan token expiry dan idempotency key.
- Factory reset ONT: permission khusus, recent re-auth, challenge serial number, preview dampak, audit, queue, dan read-back bila tersedia.
- Disconnect/reboot/disable: konfirmasi target yang jelas, rate limit, audit, correlation ID, dan hasil terverifikasi.
- Tidak ada command OLT nyata sebelum adapter/model/firmware dinyatakan verified. Mock adapter menjadi fallback.
- Tidak ada terminal bebas browser dan tidak ada perubahan perangkat production dari test suite.

## 7. HTTP dan browser

Nginx/aplikasi production minimal mengaktifkan:

```text
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: sesuai asset/API yang benar-benar digunakan
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: menonaktifkan fitur browser yang tidak diperlukan
frame-ancestors 'none' (melalui CSP)
```

HSTS diaktifkan setelah HTTPS/subdomain siap. CORS menggunakan origin eksplisit, bukan `*` dengan credential. Response secret/provisioning memakai `Cache-Control: no-store`. Production mematikan debug dan stack trace.

## 8. Jaringan dan firewall

| Service/port | Paparan |
|---|---|
| 80/443 TCP | publik; 80 redirect/ACME seperlunya |
| 22 TCP | allowlist/VPN bila memungkinkan, key-only, fail2ban opsional |
| WireGuard UDP | hanya jika diaktifkan |
| IKEv2 UDP 500/4500 | hanya jika diaktifkan |
| RADIUS UDP 1812/1813 | hanya IP NAS/router terdaftar/private network |
| MariaDB, Redis, MongoDB | localhost/private network, tidak publik |
| GenieACS internal/NBI | localhost/private; NBI hanya backend |
| MikroTik API 8728 saat ini | koneksi keluar dari VPS ke router; router mengizinkan hanya IP VPS; non-TLS sementara, migrasi API-SSL pending/manual |
| MikroTik API-SSL, SNMP, OLT SSH/API/Telnet | jalur private/VPN/allowlist |

Firewall deny-by-default. Verifikasi pascaintegrasi menunjukkan UFW VPS tetap hanya membuka TCP 22, 80, dan 443; tidak ada inbound 1812/1813/7547/8728. Jangan mematikan firewall untuk diagnosis dan jangan mengandalkan port nonstandar sebagai kontrol keamanan.

Port RouterOS API 8728 belum menyediakan TLS. Allowlist IP VPS dan akun read-only memperkecil paparan, tetapi tidak menggantikan enkripsi transport. Sampai API-SSL selesai dikonfigurasi dan trust sertifikat diverifikasi, controlled writes harus tetap disabled dan koneksi tidak boleh diperluas ke sumber lain.

## 9. Logging, audit, dan privasi

- Sanitizer terpusat meredaksi key bernama password/token/secret/community/private_key/psk/auth serta pola credential dalam teks.
- Raw HTTP body untuk endpoint credential tidak dicatat. Queue/job serializer tidak menerima object model yang membawa ciphertext jika tidak perlu.
- Audit bersifat append-oriented dan hanya dapat dibaca role berizin; perubahan/retensi administratif ikut diaudit.
- Log memiliki retention/rotation, access permission, UTC timestamp, correlation ID, dan tidak dapat diakses dari web root.
- Data pelanggan diekspor hanya sesuai scope, memakai file privat ber-expiry, dan aktivitas export diaudit.

## 10. Dependency, host, dan deployment

- Composer/npm lockfile wajib; audit dependency dijalankan di CI dan sebelum release.
- Deployment memakai user non-root, service user terpisah, umask/permission minimum; tidak ada `chmod 777`.
- `.env`, private storage, backup, log, dan socket tidak berada di document root.
- Preflight mendeteksi service/data existing; backup dilakukan sebelum migration/config change. Tidak ada database drop otomatis.
- systemd unit memakai hardening yang kompatibel (`NoNewPrivileges`, private temp, capability minimum, filesystem protection) setelah diuji.
- HTTPS certificate renewal, time synchronization, disk alert, dan backup verification dimonitor.

Deployment MikroTik read-only mempertahankan satu queue worker dan Redis `noeviction` untuk membatasi tekanan resource. Sebelum integrasi RAM terpakai 505 MiB dengan 456 MiB available; setelah integrasi 590 MiB terpakai dengan 371 MiB available. Swap berjumlah 2,2 GiB dan 667 MiB terpakai pada verifikasi akhir. Seluruh service wajib sehat dan `systemctl --failed` kosong.

## 11. Security testing gate

Sebelum production:

- feature test seluruh permission dan negative scope reseller/teknisi;
- test CSRF, CORS, rate limit, session revoke, reset password, dan account enumeration;
- test encryption/decryption/rotation serta memastikan secret tidak muncul di JSON/log/audit/queue;
- SSRF dan command-injection test pada semua adapter;
- test stale preview, replay idempotency, race/Redis lock, dan bulk partial failure;
- SAST/dependency audit, secret scan, lint/test/build;
- firewall/port scan dari luar dan dari jaringan NAS;
- backup restore drill pada lingkungan terisolasi;
- review header/TLS dan penetration test sesuai risiko.

Gate khusus MikroTik read-only yang telah lulus: dua full sync dengan count identik, detail + 10 endpoint nested, 28 backend test/195 assertion, frontend lint/build, audit dependency Composer/npm tanpa advisory, secret scan terarah, UFW tidak berubah, dan backup terenkripsi pascaintegrasi terverifikasi. Gate ini tidak mengizinkan controlled writes.

## 12. Respons insiden

Jika secret diduga bocor: hentikan akses tanpa menghapus bukti, revoke session/token, nonaktifkan command queue terkait, rotasi credential/key dari sistem sumber, identifikasi cakupan melalui correlation/audit, beri tahu pemilik, pulihkan dari state bersih, dan dokumentasikan timeline. Jangan menaruh secret yang bocor ke tiket/chat/log selama investigasi.
