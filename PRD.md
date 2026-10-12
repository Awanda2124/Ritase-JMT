# PRD — Aplikasi Absensi & Ritase Supir (IKT → JMT2)

Versi: 1.0 (draft untuk review)
Owner: Wanda

---

## 1. Latar Belakang

Saat ini pencatatan ritase supir dilakukan manual di Excel:

1. Koordinator kirim data supir ke admin.
2. Admin input nama + No. KTP supir ke Excel.
3. Supir membawa unit dari **IKT (pelabuhan)** ke **JMT (pool gudang)**. Tiap unit punya **karcis** dari IKT yang memuat nomor sasis dan datetime keluar.
4. Sampai di JMT, supir menyerahkan karcis ke admin dan menyebutkan nama. Admin mencentang 1 ritase di Excel. Kalau supir > 1 ritase, diulang.

Masalah: admin jadi bottleneck, rawan manipulasi ("baru 1 ritase bilang 3"), dan report dibuat manual.

## 2. Tujuan

1. Proses check-in/lapor ritase **tidak lagi manual lewat admin**. Supir lapor sendiri, hanya bisa diakses dari area JMT.
2. **Mengurangi manipulasi data.** Setiap ritase terikat ke karcis fisik (4 digit terakhir sasis) dan dicocokkan admin dengan karcis yang diterima.
3. **Report otomatis** dengan format seperti Excel yang ada sekarang.

## 3. Di Luar Scope (jangan dibuat)

- **Shuttle KWK** (angkot carteran antar supir JMT → IKT). Bukan bagian business logic aplikasi.
- Validasi otomatis berbasis waktu (jam keluar karcis, jeda antar ritase, flag otomatis). Cross-check dilakukan manual oleh admin.
- Barcode reader / OCR karcis (kemungkinan fase berikutnya).
- Penugasan unit ke supir (tidak ada assignment).
- Integrasi payroll / pembayaran.
- Cetak struk via Bluetooth (fase berikutnya).

## 4. Peran Pengguna

| Peran | Login | Tugas |
|---|---|---|
| **Admin** | Ya | Buat sesi kerja + kuota + link registrasi, monitoring, hapus nama dari roster, cross-check lapor vs karcis fisik, void/edit, cetak struk, tarik report |
| **Supir** | Tidak (tanpa akun) | Daftar via link, lapor ritase di JMT |

Catatan: **Koordinator tidak punya akses ke aplikasi** dan bukan role di sistem. Koordinator hanya menerima link dari admin (lewat WA) lalu meneruskannya ke grup supir.

## 5. Alur Utama

### 5.1 Registrasi supir lewat link (dengan kuota)

1. **Hanya Admin** yang membuat **Sesi Kerja**: tanggal, jam buka–tutup, kuota (contoh 50), rute (Langsiran IKT–JMT2). Kuota ditentukan admin sesuai kebutuhan (misal admin butuh 50 supir malam ini).
2. Sistem membuat **link unik** (token). Admin membagikan link ke **koordinator** (lewat WA), lalu koordinator meneruskannya ke grup WA supir.
3. Supir membuka link, mengisi **nama + No. KTP (16 digit)**. Jika kuota masih ada, supir masuk roster sesi. Siapa cepat dia dapat. Kuota habis → tampil "Penuh".
4. Supir lama yang KTP-nya sudah ada di master dikenali otomatis (tidak membuat duplikat).
5. Hanya Admin yang bisa menghapus nama dari roster sesi (tercatat di audit log).

Aturan:
- KTP wajib 16 digit angka, unik di master `drivers`.
- Satu supir hanya bisa terdaftar sekali per sesi.
- Pengecekan kuota **harus atomic** (DB transaction + `lockForUpdate`) agar tidak over-kuota saat rebutan.
- Rate limit pada endpoint publik registrasi.

### 5.2 Lapor ritase oleh supir (di JMT)

1. Supir membuka halaman lapor (mobile-first), browser meminta izin lokasi.
2. Server memvalidasi lokasi: harus dalam **radius 50 m** dari titik JMT (konfigurasi di settings; hitung Haversine di server, simpan lat/lng).
3. Supir **memilih nama** dari roster sesi yang sedang buka (autocomplete). Nama tidak ada di roster → ditolak ("belum terdaftar, hubungi admin").
4. Supir menginput **satu atau banyak 4 digit terakhir sasis** sekaligus (maksimal 7 per supir per sesi). Boleh dilakukan di akhir hari / saat berhenti menambah ritase.
5. Sistem menyimpan sebagai laporan berstatus `menunggu` dan menampilkan konfirmasi ke supir.
6. Supir menyerahkan **karcis fisik** ke admin.

Validasi sistem yang boleh ada (hanya ini):
- Format 4 digit angka.
- Sesi sedang buka (dalam jam buka–tutup).
- Supir ada di roster sesi.
- Lokasi dalam radius.
- Maksimal 7 sasis per supir per sesi.

Tidak ada validasi jam keluar karcis atau jeda antar ritase.

### 5.3 Cross-check oleh admin

1. Admin membuka dashboard sesi. Tiap supir tampil dengan daftar sasis yang dilaporkan.
2. Sambil memegang karcis fisik, admin menandai tiap sasis: **cocok** / **tidak cocok**.
3. Sasis kembar (4 digit sama dilaporkan lebih dari sekali pada tanggal yang sama) ditampilkan sebagai **penanda informasi** (tidak memblokir).
4. Admin bisa void/edit laporan; semua perubahan masuk audit log (siapa, kapan, sebelum, sesudah).
5. Admin bisa menambah **keterangan** per supir (contoh: "bawa unit bermasalah").

### 5.4 Struk (fitur tambahan)

Setelah admin menandai cocok, admin dapat mencetak struk kecil **2 rangkap** (1 untuk supir, 1 untuk arsip admin yang dijepit bersama karcis).

Isi struk:
- No. struk unik
- Tanggal
- Nama supir
- Daftar 4 digit sasis yang cocok + total ritase
- Nominal diterima (total ritase cocok × tarif)
- Garis tanda tangan supir dan admin

Teknis: halaman cetak dengan CSS lebar 58/80 mm via `window.print()`, satu halaman berisi dua struk dengan garis potong. Printer target: thermal USB di PC admin (opsi `--kiosk-printing` di Chrome).

### 5.5 Report

Format mengikuti Excel yang ada sekarang:

- Judul: tanggal sesi + "LANGSIRAN: IKT - JMT2"
- Kolom: NO, NAMA, NO. KTP, RITASE 1–7, BIAYA, KETERANGAN
- Baris ringkasan: TOTAL RIT dan TOTAL BIAYA DRIVER
- Export **Excel (.xlsx)**, PDF opsional.

Aturan hitung (default): hanya ritase berstatus **cocok** yang dihitung. Ritase `menunggu` ditampilkan dengan penanda tapi tidak masuk biaya.
Biaya = jumlah ritase cocok × tarif per ritase.

## 6. Aturan Bisnis

- Tarif per ritase: **Rp60.000**, disimpan di tabel `settings` (jangan hardcode).
- 1 ritase = 1 karcis = 1 kali jalan IKT → JMT2.
- Titik JMT (lat/lng) dan radius (default 50 m) disimpan di `settings`.
- Pemakaian karcis orang lain adalah urusan SDM/operasional, di luar tanggung jawab sistem.

## 7. Data Model (gambaran awal)

- `users` — admin saja (role `admin` via spatie/laravel-permission, siap kalau nanti ada role lain)
- `drivers` — id, name, ktp (unik, 16 digit), timestamps
- `work_sessions` — id, date, route, opens_at, closes_at, quota, token (unik), created_by
- `session_drivers` — id, work_session_id, driver_id (unik per sesi), registered_at
- `trip_reports` — id, work_session_id, driver_id, chassis_last4, status (`menunggu`/`cocok`/`tidak_cocok`/`void`), lat, lng, reported_at, verified_by, verified_at, note
- `receipts` — id, receipt_no (unik), work_session_id, driver_id, total_trips, amount, printed_by, printed_at
- `settings` — key/value (rate_per_trip, jmt_lat, jmt_lng, radius_m)
- `activity_log` — audit log (spatie/laravel-activitylog)

Index penting: `trip_reports(work_session_id, driver_id)`, `trip_reports(work_session_id, chassis_last4)`, `drivers(ktp)`, `work_sessions(token)`.

## 8. Kebutuhan Non-Fungsional

- **HTTPS wajib** (Geolocation API butuh secure context).
- Mobile-first untuk halaman supir; desktop untuk admin/koordinator.
- Keamanan: CSRF, throttling endpoint publik, validasi server-side, token link tidak mudah ditebak.
- **Privasi:** KTP disimpan di database, tampil termasking di UI (kecuali di report untuk admin yang berwenang). Patuhi prinsip UU PDP (akses dibatasi, tidak dibagikan ke publik).
- Performa: halaman supir ringan; query dashboard memakai eager loading dan index.
- Semua aksi admin tercatat di audit log.

## 9. Stack

- Laravel (versi stabil terbaru) + PHP sesuai requirement Laravel
- MySQL 8
- Blade + Livewire + Alpine.js + Tailwind CSS
- maatwebsite/excel (export), spatie/laravel-permission, spatie/laravel-activitylog
- Test: Pest (atau PHPUnit)
- Deploy: VPS, Nginx + PHP-FPM, SSL

## 10. Milestone

1. **M1 — Fondasi:** project setup, auth, role, migrations, seeder, settings.
2. **M2 — Sesi & registrasi:** CRUD sesi kerja, link registrasi, kuota atomic, roster.
3. **M3 — Lapor ritase:** halaman supir, geofence server-side, input batch sasis.
4. **M4 — Cross-check admin:** dashboard sesi, centang cocok/tidak cocok, void/edit, keterangan, audit log.
5. **M5 — Report & struk:** export Excel sesuai format, halaman cetak struk 2 rangkap.
6. **M6 — Hardening:** test, rate limit, masking KTP, deploy.

Setiap milestone **dihentikan untuk review** sebelum lanjut ke berikutnya.

## 11. Keputusan Terbuka

1. Report menghitung hanya ritase `cocok` (default di PRD ini) atau semua yang dilapor?
2. Sasis kembar: cukup penanda informasi (default) atau perlu diblokir?
3. Jenis printer final di pos admin (thermal USB vs Bluetooth).
4. Pelaporan lintas hari: apakah sesi yang sudah ditutup bisa dilaporkan susulan oleh admin?