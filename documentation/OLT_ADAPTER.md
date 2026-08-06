# Arsitektur Adapter OLT

## Status dan batas keselamatan

Modul OLT direncanakan untuk Tahap 4. `HuaweiOltAdapter`, `ZteOltAdapter`, dan `FiberHomeOltAdapter` adalah target kelas, **bukan klaim bahwa command vendor sudah benar atau diuji**. Sampai ada perangkat lab, model/firmware, OID, dan bukti command, gunakan `MockOltAdapter`; mutasi adapter nyata default disabled.

## 1. Mengapa adapter

Vendor, model, board, dan firmware OLT berbeda dalam OID, CLI, format output, pagination, privilege, dan command konfigurasi. Modul domain hanya mengenal DTO normal dan capability; seluruh detail vendor berada di adapter.

```text
OltService -> OltAdapterInterface
                 |-- MockOltAdapter
                 |-- HuaweiOltAdapter
                 |-- ZteOltAdapter
                 `-- FiberHomeOltAdapter
                        |-- SNMP transport
                        |-- SSH transport
                        `-- Vendor API transport (jika verified)
```

Telnet adalah opsi legacy, default off, membutuhkan feature flag/per-device warning, jaringan terisolasi, dan persetujuan keamanan.

## 2. Kontrak dan capability

Interface tidak boleh menjadi kumpulan raw command. Operasi semantik:

```text
testConnection(): OltIdentity
capabilities(): CapabilitySet
listBoards(): list<BoardSnapshot>
listPorts(board): list<PortSnapshot>
listOnts(port): list<OntSnapshot>
getOntOptical(ont): OpticalSnapshot
preview(action): OltActionPreview
execute(action): OltActionResult
```

Capability contoh: `inventory.boards`, `inventory.onts`, `optical.rx`, `optical.tx`, `ont.description.write`, `ont.reboot`, `ont.admin_state`, `ont.provision`. API/UI hanya menawarkan aksi yang `supported && verified && enabled`.

## 3. DTO normal

Adapter memetakan nilai vendor ke:

- OLT identity: vendor/model/serial/software/chassis/status.
- Board: external ID, frame/slot, type, serial, admin/oper state.
- Port/PON: frame/slot/port, technology, admin/oper state, capacity.
- ONT: stable locator, ONU ID, serial, description, state/reason, distance meter, uptime second, VLAN/service/line profile.
- Optical: RX/TX dBm nullable, bias/voltage/temperature nullable, quality flag, observed time.

Nilai tidak tersedia tetap `null`; adapter tidak mengubah sentinel vendor menjadi 0. Unit/scale dan threshold ditentukan mapping per model/firmware dan diuji fixture.

Stable locator internal memuat OLT ID + frame/slot/port/ONU ID. Serial tidak selalu unik/tersedia saat unprovisioned, sehingga constraint dan reconciliation harus mempertimbangkan vendor.

## 4. Transport

### SNMP

- SNMPv3 authPriv diprioritaskan; v2c didukung untuk perangkat legacy di management network.
- Community/auth/privacy secret encrypted dan write-only.
- OID/MIB mapping versioned per vendor/model/firmware; walk dibatasi subtree/size/time.
- Trap dapat ditambahkan kemudian, tetapi polling tetap diperlukan dan source event diberi dedupe.

### SSH/API

- SSH host key pinning/verification wajib production. Credential/enable secret tidak masuk command history.
- Gunakan parser state-machine/structured output jika tersedia; jangan mengeksekusi input pengguna sebagai CLI.
- Prompt/pagination/encoding/locale dan privilege mode diperlakukan eksplisit.
- Vendor API memakai TLS verification, timeout, schema validation, dan rate limit.

## 5. Registry command terverifikasi

Setiap implementasi nyata memiliki registry:

| Field | Makna |
|---|---|
| vendor/model/firmware range | perangkat yang diuji |
| action semantic | mis. update description/reboot |
| transport/template/parser version | implementasi terkunci |
| fixture/golden output | bukti parser |
| verified read-back | cara memastikan hasil |
| tested_at/tested_by/lab reference | provenance |
| enabled | feature gate production |

Command yang belum memiliki registry aktif mengembalikan `unsupported_unverified`. Dilarang mencoba variasi command ke production.

## 6. Polling dan skala

- OLT status 1-5 menit, inventory lebih jarang/on demand, optical 5-15 menit.
- Poll per OLT/PON dibatasi concurrency dan memakai jitter/Redis lock.
- Bulk SNMP lebih disukai bila aman; SSH polling banyak ONT harus dibatch/rate-limited.
- Current snapshot dan history disimpan dengan timestamp/quality. Dashboard membaca cache/DB.
- Maintenance mode menahan alert dan command sesuai kebijakan, tetapi menyimpan alasan/waktu/actor.

## 7. Mutasi ONT

Mutasi awal: refresh, update description, reboot, enable/disable, dan provisioning melalui job. Semua mengikuti:

1. permission dan assignment scope;
2. capability verified;
3. preview target/diff/dependency/service impact;
4. confirmation dan idempotency key;
5. command lock per OLT/ONT;
6. request/result tersanitasi;
7. read-back atau status `unverified`;
8. audit success/failure dengan correlation ID.

Provisioning adalah plan terstruktur (serial, locator, line/service profile, VLAN, description), bukan textarea CLI. Bulk provisioning wajib preview per item dan tidak melanjutkan diam-diam setelah partial failure.

## 8. Error model

Kode normal: `connection_timeout`, `authentication_failed`, `host_key_failed`, `unsupported_transport`, `unsupported_unverified`, `parse_failed`, `device_busy`, `command_rejected`, `verification_mismatch`, `partial_result`. Raw CLI/SNMP response disimpan hanya bila benar-benar diperlukan, terenkripsi/terbatas, dan tidak masuk audit/UI default.

## 9. Mock adapter

Mock menyediakan minimal 2 OLT, board/port/PON, ONT online/offline/LOS/dying-gasp/low optical, serta action sukses/fail/timeout/verification mismatch. State mock deterministic dan mendukung preview/apply sehingga UI/alur audit/job dapat diuji. Setiap resource mock diberi label jelas.

## 10. Proses mengaktifkan vendor nyata

1. Dapatkan dokumentasi vendor/MIB dan model/firmware perangkat lab secara sah.
2. Rekam fixture output yang sudah menghapus serial/credential/IP sensitif.
3. Implementasi transport/parser dan contract test.
4. Uji read-only inventory/optical pada lab.
5. Uji satu mutasi rendah risiko dan read-back dengan persetujuan.
6. Catat registry verified dan security review.
7. Aktifkan feature flag hanya untuk perangkat/model yang lulus; monitor dan siapkan rollback manual terdokumentasi.

