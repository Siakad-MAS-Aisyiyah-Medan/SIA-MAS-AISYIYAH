# Frontend SIAKAD

React dan Vite. npm ci menginstal dependensi sesuai lockfile. npm run dev menjalankan pengembangan; npm run check memeriksa lint dan build; npm run build menyiapkan dist untuk web server.

Konfigurasi API: VITE_API_BASE_URL di .env untuk lokal dan .env.production untuk build produksi. Perubahan nilai produksi memerlukan build ulang.

src/roles menyimpan halaman berdasarkan peran, src/shared menyimpan kode bersama, src/config menyimpan konfigurasi menu/izin, dan src/routes memetakan URL. apiClient menangani token serta respons otorisasi; daftar master mengambil semua halaman API agar pilihan kelas/guru/mapel tidak terpotong.

Gaya dasar ada di index.css dan App.css. mobile-responsive.css dimuat sesudah gaya dasar agar aturan layar kecil berlaku. Pertahankan tabel di dalam table-container untuk gulir horizontal pada ponsel.

Web server harus mengarahkan URL aplikasi yang bukan berkas nyata ke index.html, termasuk saat pengguna menyegarkan /admin/dashboard atau halaman peran lainnya. Lihat [operasional](../docs/OPERASIONAL.md) untuk pemasangan.
