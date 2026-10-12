# CURSOR_SETUP.md — Instruksi untuk Cursor

Baca `PRD.md` terlebih dahulu. Dokumen ini berisi langkah setup stack dan aturan kerja. **Jangan membuat fitur di luar scope PRD.** Shuttle KWK sengaja tidak masuk.

## Aturan Kerja

- Kerjakan **per milestone** (M1–M6 di PRD). **Berhenti setelah tiap milestone** dan minta review sebelum lanjut.
- Jangan menjalankan perintah destruktif (`migrate:fresh` di database non-lokal, `rm -rf`, dll) tanpa konfirmasi.
- Jangan hardcode tarif, titik JMT, atau radius. Ambil dari tabel `settings`.
- Sebelum menginstall package di luar daftar di bawah, tanyakan dulu.

## 1. Cek Prasyarat

Pastikan tersedia, dan laporkan versinya:

```bash
php -v
composer -V
node -v && npm -v
mysql --version
git --version
```

Versi PHP harus memenuhi requirement Laravel versi terbaru. Kalau tidak memenuhi, berhenti dan laporkan.

## 2. Buat Project

Folder kerja (Windows): `C:\Users\L490\Desktop\wanda\Ritase-JMT`. `PRD.md` dan `CURSOR_SETUP.md` ada di root folder ini. Karena root tidak kosong, buat project Laravel di **subfolder `app`** (jangan di root, `composer create-project` gagal di folder yang tidak kosong):

```bash
composer create-project laravel/laravel app
cd app
```

Semua perintah berikutnya dijalankan dari dalam folder `app`. `PRD.md` tetap di root sebagai acuan.

Gunakan struktur dan frontend tooling bawaan skeleton Laravel terbaru (Vite + Tailwind sesuai bawaan). Jangan mengganti ke tooling lain.

## 3. Konfigurasi Database (MySQL)

Buat database lokal `absensi_ritase` (collation `utf8mb4_unicode_ci`), lalu atur `.env`:

```env
APP_NAME="Absensi Ritase"
APP_URL=http://localhost
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi_ritase
DB_USERNAME=<isi lokal>
DB_PASSWORD=<isi lokal>
```

Pastikan `config/app.php` memakai timezone `Asia/Jakarta` dan locale `id`. Jangan commit `.env`.

## 4. Install Package

```bash
composer require livewire/livewire
composer require maatwebsite/excel
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require pestphp/pest pestphp/pest-plugin-laravel --dev
```

Lalu:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
npm install
npm install alpinejs
```

Catatan: Livewire versi terbaru sudah menyertakan Alpine.js. Kalau begitu, **jangan** install Alpine terpisah, cukup pakai bawaan Livewire. Periksa dokumentasi versi yang terpasang.

## 5. Verifikasi Setup

```bash
php artisan migrate
npm run build
php artisan test
```

Semua harus berhasil. Laporkan hasilnya, termasuk versi Laravel, Livewire, dan package yang terpasang.

## 6. Konvensi Kode

- PHP rapi dan mudah di-maintain: **Form Request** untuk validasi, **Service class** untuk business logic (registrasi kuota, verifikasi ritase, perhitungan biaya), controller/Livewire component tetap tipis.
- Semua perubahan skema lewat **migration**. Seeder untuk role (admin, koordinator), user admin awal, dan `settings` default (rate_per_trip=60000, radius_m=50).
- Pengecekan kuota registrasi wajib memakai `DB::transaction` + `lockForUpdate()`.
- Unique constraint di database (bukan hanya di validasi): `drivers.ktp`, `session_drivers(work_session_id, driver_id)`, `work_sessions.token`.
- Tambahkan index sesuai bagian Data Model di PRD.
- Geofence dihitung **di server** (Haversine). Jangan percaya hasil cek di client.
- Endpoint publik (registrasi, lapor) diberi **rate limit** (`throttle`).
- Gunakan eager loading untuk menghindari N+1 di dashboard.
- No. KTP ditampilkan termasking di UI (contoh `3172••••••••0010`).
- Tulis test (Pest) untuk: kuota atomic, geofence, validasi lapor, perhitungan biaya cocok × tarif.
- Bahasa UI: Bahasa Indonesia. Istilah teknis di kode tetap Bahasa Inggris.

## 7. Mulai Dari Sini

Kerjakan **M1 — Fondasi** (project setup, auth, role, migrations, seeder, settings). Setelah selesai, berhenti, ringkas apa yang dibuat, dan tunggu review.
