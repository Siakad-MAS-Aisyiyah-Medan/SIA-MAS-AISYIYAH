# SISTEM INFORMASI AKADEMIK MAS Aisyiyah Medan

Dokumentasi ini ditujukan untuk memandu pengembang (developer), penguji, atau akademisi yang ingin memahami, menjalankan, dan memodifikasi Sistem Informasi Akademik (SIAKAD) MAS Aisyiyah Medan. 

Proyek ini adalah sistem komprehensif yang dirancang untuk mengotomatisasi pengelolaaan data akademik sekolah, mencakup Penerimaan Peserta Didik Baru (PPDB), manajemen kelas, jadwal pelajaran, penilaian, hingga rekapitulasi absensi.

---

## Informasi Proyek Akademik (Skripsi)

Proyek ini dikembangkan sebagai bagian dari Tugas Akhir (Skripsi) pada Program Studi S-1 Teknik Informatika, Fakultas Informatika, Universitas Mikroskil (Medan, 2026).

Tim Pengembang:
1. Chairul Fitra Ramadhan (NIM: 221112302)
2. Rehuel Mirror Pertiwi Br. Saragih (NIM: 221111489)
3. Maudi Natalsyah Br Simatupang (NIM: 221112432)

---

## Arsitektur Sistem

Sistem ini mengadopsi arsitektur Decoupled (pemisahan sistem penuh antara antarmuka pengguna dan logika server). Pemisahan ini membagi proyek menjadi dua aplikasi utama yang berjalan secara mandiri:

1. Frontend (React.js + Vite)
   Bertanggung jawab sepenuhnya atas antarmuka pengguna (UI) dan interaksi klien (Single Page Application). Berjalan pada sisi klien dan mengkonsumsi data dari Backend melalui HTTP Request.
   
2. Backend (Laravel REST API)
   Bertanggung jawab atas manipulasi database, autentikasi, otorisasi, dan validasi logika bisnis. Backend ini hanya menerima permintaan dan mengembalikan data dalam format JSON. Backend tidak memproduksi halaman HTML (tanpa Blade).

Sistem basis data yang digunakan adalah MySQL atau MariaDB.

---

## Fitur dan Hak Akses (Role-Based Access Control)

Setiap entitas dalam sistem dibatasi oleh hak akses spesifik (Role) untuk menjaga keamanan data:

- Administrator: Akses tak terbatas. Bertugas mengelola data master (Tahun Ajaran, Mata Pelajaran, Kelas, Akun Pengguna, dan Konfigurasi PPDB).
- Kepala Sekolah: Akses pengawasan (eksekutif). Bertugas melihat laporan statistik akademik, memantau absensi, dan memantau kinerja guru.
- Guru: Akses operasional harian. Bertugas mengelola data nilai murid, memasukkan absensi murid harian, dan melihat jadwal mengajar pribadi.
- Siswa: Akses personal. Bertugas melihat daftar kehadiran pribadi, mencetak transkrip nilai, dan membaca papan pengumuman sekolah.
- Calon Siswa: Akses pendaftaran. Bertugas membuat akun pendaftaran sementara, mengisi formulir PPDB, dan mengunggah berkas persyaratan.

---

## Struktur Direktori Utama

Repositori ini memuat kedua bagian aplikasi dalam dua folder besar. Memahami pembagian ini akan mempercepat proses navigasi Anda.

```text
siakad-full/
├── backend/               Pusat pengolahan data (REST API).
│   ├── app/
│   │   ├── Http/Controllers/  File pengendali rute. Menangani request dan mengembalikan response JSON.
│   │   ├── Http/Requests/     Menyimpan aturan validasi input dari pengguna.
│   │   └── Models/            Model Eloquent untuk interaksi dengan database MySQL dan mengatur relasi.
│   ├── database/
│   │   ├── migrations/        Cetak biru (blueprint) tabel database.
│   │   └── seeders/           Penyedia data master awal (Role, Permission, Akun Default).
│   └── routes/api.php         File utama pendaftaran seluruh endpoint REST API.
│
└── frontend/              Pusat antarmuka visual (React UI).
    └── src/
        ├── config/            Pengaturan dasar, seperti URL Backend (api.config.js) dan konfigurasi navigasi.
        ├── roles/             Halaman-halaman UI yang dipisah per hak akses (admin, guru, kepsek, murid, calon-murid).
        └── shared/            Komponen UI dan fungsi universal yang dipakai secara silang.
            ├── components/    Komponen UI modular (Tabel, Header, Input).
            └── akademik/      Logika pemanggilan API (fetcher/services) khusus entitas akademik.
```

---

## Panduan Instalasi dan Konfigurasi Lokal

Untuk menjalankan sistem ini pada komputer lokal Anda, pastikan telah terpasang PHP (>= 8.1), Composer, Node.js (>= 18), npm, dan server MySQL.

### 1. Persiapan Database MySQL
Buatlah sebuah database kosong di MySQL Anda (misalnya dengan nama `siakad`).

### 2. Instalasi Backend (Laravel)
Buka terminal dan arahkan ke dalam folder `backend/`:

```bash
cd backend
composer install
copy .env.example .env
```
Buka file `.env` menggunakan teks editor, lalu cari konfigurasi database. Sesuaikan `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dengan konfigurasi MySQL Anda. Setelah itu jalankan perintah berikut:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```
Perintah di atas akan membangun struktur tabel, mengisi master data, dan menghidupkan server API pada `http://127.0.0.1:8000`. Biarkan terminal ini tetap menyala.

### 3. Instalasi Frontend (React)
Buka terminal baru (jangan menutup terminal backend) dan arahkan ke dalam folder `frontend/`:

```bash
cd frontend
npm install
copy .env.example .env
```
Pastikan isi dari file `.env` di dalam folder frontend ini memiliki variabel `VITE_API_URL=http://127.0.0.1:8000/api` agar bisa berkomunikasi dengan backend. Kemudian jalankan:

```bash
npm run dev
```
Setelah proses selesai, buka browser web Anda dan ketikkan alamat `http://localhost:5173` untuk melihat sistem beroperasi.

---

## Standar Pengujian Kode (Linting & Testing)

Sistem ini mematuhi standar penulisan kode modern. Sebelum melakukan pengiriman perubahan kode, jalankan pengujian berikut:

Backend:
```bash
cd backend
php artisan test
vendor/bin/pint --test
```

Frontend:
```bash
cd frontend
npm run lint
npm run build
```

---

## Panduan Pencarian Kendala (Troubleshooting Flow)

Jika Anda ingin memperbaiki bug atau memodifikasi alur kerja, gunakan rute pelacakan (tracing) berikut secara runut:

1. Frontend UI: Cari halaman yang bermasalah pada direktori `frontend/src/roles/`. Temukan nama komponen berektensi `.jsx` yang sedang di-render.
2. Frontend API Service: Pada komponen tersebut, cari fungsi yang melakukan pertukaran data. Fungsi ini biasanya memanggil endpoint API dari `frontend/src/shared/`.
3. Backend Route: Salin path API (misal `/api/guru`) dan cari di file `backend/routes/api.php`. Di sana Anda akan menemukan nama Controller yang bertugas.
4. Backend Controller: Buka Controller terkait di `backend/app/Http/Controllers/`. Periksa alur validasi dan bagaimana ia memanggil Model atau Service untuk menyelesaikan proses bisnis database.
