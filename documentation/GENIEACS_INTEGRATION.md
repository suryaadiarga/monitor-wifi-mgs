# Integrasi GenieACS / TR-069

## Status

Integrasi ini ditargetkan pada Tahap 4. Belum ada klaim koneksi NBI, task, parameter mapping, atau perangkat nyata telah diuji. Selama credential/ACS belum tersedia, gunakan mock adapter.

## 1. Boundary

Core application mengakses GenieACS melalui NBI API menggunakan `AcsAdapterInterface`. Core **tidak menulis langsung ke MongoDB GenieACS** dan tidak bergantung pada schema internalnya.

```text
Laravel job -> GenieAcsService -> GenieAcsNbiAdapter -> GenieACS NBI
                                     |
                               MockAcsAdapter
GenieACS CWMP/UI/NBI -> MongoDB khusus GenieACS
```

NBI bind ke localhost/private network dan hanya backend yang diberi akses. Jika memakai reverse proxy, gunakan TLS/mTLS atau autentikasi gateway yang sesuai, allowlist, serta rate/size limit.

## 2. Fungsi adapter

Kontrak dibagi read/task/mapping:

```text
listDevices(filter, page): DevicePage
getDevice(externalId): DeviceSnapshot
refreshObject(device, objectPath): TaskReceipt
setParameters(device, semanticValues): TaskReceipt
reboot(device): TaskReceipt
factoryReset(device, challenge): TaskReceipt
getTasks(device, filter): TaskPage
getFaults(device, filter): FaultPage
assignProvision(device, provision): TaskReceipt
setTags(device, tags): TaskReceipt
```

Adapter menangani encoding query/payload NBI, timeout, status code, polling task, dan redaction. DTO memakai semantic key internal, bukan menyebarkan path vendor ke UI.

## 3. Inventory dan status

Snapshot minimum: ACS device ID, serial number, manufacturer, product class, software version, WAN IP, SSID, uptime, last inform, status online/offline, optical RX/TX jika tersedia, PPPoE username, mapping yang dipilih, serta `observed_at`.

“Online” TR-069 bukan koneksi terus-menerus. Baseline dihitung dari `last_inform` terhadap interval inform/perangkat dengan grace window, misalnya `max(2 × periodic interval, threshold setting)`. API menyertakan `last_inform` dan alasan status; jangan menampilkan online palsu hanya karena device ada di ACS.

Sync inventory berjalan melalui queue 1-5 menit, ter-paginasi, menggunakan cursor/watermark bila didukung, dan tidak dipicu halaman. Device yang hilang tidak langsung dihapus; ditandai stale/retired melalui kebijakan.

## 4. Parameter mapping

Path TR-069 berbeda antar data model, vendor, product class, dan firmware. UI/service menggunakan semantic key, contoh:

```text
device.serial_number
device.software_version
wan.ip_address
wan.pppoe_username
wifi.2g.ssid
wifi.2g.enabled
optical.rx_dbm
optical.tx_dbm
```

Satu mapping memuat:

- vendor matcher;
- product class matcher;
- software version matcher/range;
- semantic key;
- path/template path;
- tipe data dan read/write flag;
- transform/scale/unit;
- priority dan version;
- contoh nilai serta status verified.

Urutan pemilihan: exact vendor + product class + firmware, lalu exact vendor/product class, lalu vendor default, terakhir generic verified. Ambiguitas menghasilkan warning/error, bukan memilih acak. Mapping dapat diuji terhadap snapshot fixture sebelum diaktifkan dan perubahan mapping diaudit.

## 5. Task lifecycle

1. Service memeriksa permission, scope pelanggan/perangkat, dan capability mapping.
2. Aksi set/reboot/refresh membuat preview nilai semantik, path yang dipilih (hanya operator berizin), dan risiko.
3. Job mengirim task ke NBI dengan correlation/idempotency reference bila tersedia.
4. `genieacs_tasks` menyimpan ACS task ID, action, parameter tersanitasi, status, timestamps, dan fault summary.
5. Worker memantau completion dengan backoff/expiry; refresh snapshot setelah sukses.
6. Success tanpa read-back dibedakan dari verified success.

Retry task mutasi tidak dilakukan secara buta. Jika response timeout setelah submit, lakukan lookup task/correlation sebelum mengirim ulang.

## 6. Aksi dan tingkat risiko

| Aksi | Kontrol minimum |
|---|---|
| refresh object | permission diagnostic, path allowlist, rate limit |
| set Wi-Fi/WAN parameter | preview old/new, writable mapping verified, audit, read-back |
| reboot | konfirmasi target, maintenance check, cooldown, audit |
| provision/tag | provision allowlist, preview assignment/diff |
| factory reset | permission khusus, recent re-auth, ketik ulang serial, preview berlapis, idempotency, audit, alert |

Factory reset default feature flag off sampai model/firmware lab lulus test. UI tidak menampilkan tombol jika backend capability/permission tidak memenuhi, tetapi backend tetap menolak request ilegal.

## 7. Secret dan data sensitif

- Credential NBI hanya environment/secret store, tidak dikirim ke browser atau log.
- PPPoE password dari parameter tree tidak ditarik/disimpan kecuali use case eksplisit dan policy aman; default hanya username/status.
- SSID boleh dibaca sesuai permission; Wi-Fi key diperlakukan write-only dan tidak disimpan pada task payload/audit.
- NBI raw payload dapat mengandung secret/vendor data; jangan log raw response. Parser memilih field allowlist dan sanitizer berjalan sebelum persistence.

## 8. Mock adapter

Mock menyediakan beberapa vendor/product class/firmware, device online/offline/stale, optical normal/low/unavailable, mapping match/ambiguity, task queued/success/fault/timeout, serta factory reset disabled. Fixture deterministic memungkinkan dashboard, detail, history, dan permission diuji tanpa ACS.

## 9. Test dan aktivasi

- contract test mock/NBI adapter;
- query encoding/pagination/large response/timeout/malformed value;
- mapping precedence/transform/unit/ambiguity dan firmware fallback;
- task idempotency, polling, fault, timeout-after-submit;
- redaction password/Wi-Fi key/NBI auth;
- permission/assignment dan factory-reset challenge;
- uji read-only pada GenieACS lab, kemudian set parameter/reboot/factory reset hanya pada ONT lab yang disetujui.

Daftar manufacturer/product class/software dan semantic mapping yang verified harus dicatat sebelum adapter diaktifkan pada production.

