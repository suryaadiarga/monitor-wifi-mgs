# Monitor WiFi MGS

Aplikasi pemantauan jaringan WiFi berbasis web yang dikembangkan untuk **PT Multi Guna Sinergi**.

## Struktur Project

* **backend/** — Laravel API & Database
* **frontend/** — Next.js Web Interface
* **monitoring/** — Docker Monitoring Services

---

## Menjalankan Project

### 1. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### 2. Frontend

```bash
cd frontend
npm install
npm run dev
```

### 3. Monitoring

```bash
cd monitoring
docker compose up -d
```

---

## Informasi Project

* **Instansi:** PT Multi Guna Sinergi
* **Kegiatan:** Kerja Praktik
* **Project:** Sistem Monitoring Jaringan Internet Service Provider Berbasis Web Terintegrasi MikroTik

---

## 👥 Team & Role

Proyek **Sistem Monitoring Jaringan Internet Service Provider Berbasis Web Terintegrasi MikroTik** dikerjakan secara kolaboratif dengan pembagian tanggung jawab berdasarkan bidang pekerjaan masing-masing anggota.

### Galang Ubaidillah

**NIM:** 1203230056
**Role:** Network Administrator (NA) & Quality Assurance (QA)

**Responsibilities:**

* Melakukan observasi dan pemahaman terhadap infrastruktur jaringan ISP.
* Mengidentifikasi perangkat dan informasi jaringan yang relevan untuk kebutuhan monitoring.
* Membantu memahami kebutuhan sistem dari sisi operasional jaringan.
* Menyiapkan lingkungan pengujian sistem.
* Melakukan functional testing terhadap fitur sistem.
* Melakukan pengujian antarmuka dan navigasi aplikasi.
* Memeriksa komunikasi antara frontend dan backend dari sisi pengujian.
* Mengidentifikasi dan mendokumentasikan bug atau ketidaksesuaian sistem.
* Melakukan retesting setelah perbaikan sistem.
* Melakukan validasi hasil pengujian dan mendokumentasikan hasil testing.

### Surya Adi Arga Widana

**NIM:** 1203230101
**Role:** Software Developer & System Developer

**Responsibilities:**

* Melakukan analisis kebutuhan sistem berdasarkan kebutuhan monitoring jaringan.
* Merancang alur dan struktur sistem monitoring berbasis web.
* Menyiapkan struktur project dan environment pengembangan.
* Mengembangkan antarmuka dashboard monitoring.
* Mengembangkan dan menyesuaikan fungsi sistem.
* Mengelola pengembangan bagian frontend dan backend sesuai pembagian tugas.
* Menangani komunikasi antara komponen frontend dan backend.
* Mengimplementasikan penggunaan data dummy untuk kebutuhan pengembangan dan pengujian.
* Melakukan perbaikan fitur berdasarkan hasil pengujian dan temuan QA.
* Melakukan penyempurnaan serta pembaruan project selama proses pengembangan.

---

## 🤝 Collaboration

Kedua anggota bekerja pada satu proyek yang sama dengan pembagian tanggung jawab yang saling melengkapi.

```text
                    SISTEM MONITORING JARINGAN ISP
                                  │
                ┌─────────────────┴─────────────────┐
                │                                   │
             GALANG                               SURYA
            NA & QA                         SOFTWARE DEVELOPER
                │                                   │
        ┌───────┴───────┐                   ┌───────┴────────┐
        │               │                   │                │
     Network          Testing          Development       Implementation
     Observation        & QA             Frontend/Backend   & Integration
        │               │                   │                │
        └───────┬───────┘                   └───────┬────────┘
                │                                   │
                └───────────────┬───────────────────┘
                                │
                       Testing & Improvement
                                │
                                ▼
                    Final Monitoring System
```

Pembagian peran tersebut memungkinkan proses pengembangan dan pengujian dilakukan secara terstruktur, dengan fokus pekerjaan yang berbeda tetapi tetap terintegrasi dalam satu sistem.
