# SISTEM INFORMASI AKADEMIK MAS AISYIYAH MEDAN

Sistem akademik sekolah dengan Laravel REST API dan React. Modul: pengguna, guru, murid/alumni, kelas, mata pelajaran, nilai fleksibel, absensi, pengumuman, profil sekolah, tahun ajaran, dan PPDB.

## Struktur proyek

- backend/app/Http/Controllers: validasi permintaan dan respons API.
- backend/app/Services: aturan bisnis, transaksi, penilaian, PPDB, dan penempatan murid.
- backend/app/Models dan Http/Resources: relasi database dan bentuk respons.
- backend/app/Http/Middleware: autentikasi, status akun, izin, dan validasi pagination.
- backend/routes/api: endpoint dipisah berdasarkan modul/peran.
- backend/database/migrations: riwayat skema yang dipakai untuk instalasi dan pembaruan.
- backend/database/seeders: inisialisasi hak akses, tanpa akun demo.
- backend/storage/app/private: dokumen pribadi PPDB.
- backend/storage/app/public: foto sekolah/profil, gambar pengumuman, dan brosur.
- frontend/src/roles: halaman menurut peran pengguna.
- frontend/src/shared: komponen, layanan API, hooks, dan fungsi bersama.
- frontend/src/config dan routes: navigasi, izin, dan pemetaan halaman.
- frontend/dist: hasil build untuk dipasang pada web server.
- docs: panduan operasional serta catatan audit.

## Menjalankan secara lokal

Syarat: PHP 8.2 atau lebih baru, Composer, Node.js 22.12 atau lebih baru, npm, dan MySQL/MariaDB. PHP memerlukan PDO MySQL, mbstring, XML, fileinfo, ZIP, GD, intl, dan bcmath. SQLite hanya diperlukan bila melakukan pemeriksaan terisolasi.

Backend, dari folder backend:

~~~powershell
composer install --no-dev --optimize-autoloader
Copy-Item .env.example .env
~~~

Sesuaikan koneksi database pada .env dan buat database kosong. Jangan menimpa .env yang sudah berisi konfigurasi sekolah. Kemudian:

~~~powershell
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan siakad:create-admin admin --name="Administrator Sekolah"
php artisan serve --host=127.0.0.1 --port=8000
~~~

Pembuatan admin meminta password secara tersembunyi; tidak ada password bawaan. Jangan menjalankan key:generate ulang pada instalasi yang sudah dipakai.

Frontend, dari folder frontend:

~~~powershell
npm ci
Copy-Item .env.example .env
npm run dev
~~~

Gunakan VITE_API_BASE_URL=http://127.0.0.1:8000/api, lalu buka http://localhost:5173. Frontend dan API berjalan sebagai dua proses terpisah.

## Pemakaian dan pemasangan

Baca [panduan operasional](docs/OPERASIONAL.md) untuk urutan pengisian data, pembaruan server, backup, dan konfigurasi produksi. Lihat [hasil audit](docs/HASIL_AUDIT.md) untuk perbaikan dan batas verifikasi.

Perintah pemeriksaan sumber: npm run check di frontend; composer validate --strict dan composer audit di backend. Berkas pengujian sementara sudah dibersihkan sesuai permintaan; ringkasan hasilnya disimpan di catatan audit.

## Pengembang awal

Proyek Tugas Akhir Teknik Informatika Universitas Mikroskil, Medan, 2026: Chairul Fitra Ramadhan, Rehuel Mirror Pertiwi Br. Saragih, dan Maudi Natalsyah Br Simatupang.
