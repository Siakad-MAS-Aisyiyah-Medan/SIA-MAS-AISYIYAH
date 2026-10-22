# Backend SIAKAD

Laravel API. Jalur eksekusi: routes/api -> Controller -> Service -> Model -> Resource. Aturan bisnis diletakkan pada Services agar tidak disalin ke controller lain.

- AuthService: login, profil, menu, dan izin.
- PermissionService: hak akses berdasarkan peran; RbacSeeder menginisialisasinya.
- MuridService, GuruService, KelasService, MapelService: data master dan perlindungan arsip akademik.
- StudentPlacementService: validasi kapasitas dan status kelas di dalam transaksi.
- FlexibleNilaiService: bobot komponen, nilai siswa, dan rekap nilai akhir.
- AbsensiSiswaService: penugasan guru, anggota kelas, dan pertemuan absensi.
- PpdbService dan PendaftaranStateService: formulir, sinkronisasi detail, dan perubahan status.
- PpdbBerkasService: dokumen privat dan tautan pratinjau bertanda tangan selama 30 menit.
- EnrollmentService dan SchoolNumberService: promosi siswa dan nomor urut dengan penguncian database.

Database menggunakan foreign key dan unique index. Data PPDB lama pada tabel induk dipertahankan demi kompatibilitas; simpan perubahan melalui service agar tabel detail tetap sinkron. Jangan menghapus atau menggabungkan migrasi yang sudah dijalankan pada database sekolah.

Dokumen pribadi memakai disk local (storage/app/private). Foto dan brosur memakai disk public. public/storage harus menunjuk ke storage/app/public pada instalasi ini. Hanya backend/public yang menjadi document root server.

Perintah operator dan panduan pemasangan ada pada [panduan operasional](../docs/OPERASIONAL.md). Jangan menjalankan migrate:fresh pada database sekolah.
