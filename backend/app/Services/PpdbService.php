<?php

namespace App\Services;

use App\Models\Pendaftaran;
use App\Models\ProfilSekolah;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Account\AccountRegistrationService;
use App\Utils\AuditsAdminActions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PpdbService
{
    use AuditsAdminActions;

    public function __construct(private PendaftaranStateService $state, private PpdbBerkasService $berkasService, private AccountRegistrationService $auth, private EnrollmentService $enrollment) {}

    public function saveDraftForUser(User $user, array $data, Request $request): Pendaftaran
    {
        $fields = array_flip(PpdbBerkasService::FILE_COLUMNS);
        foreach (PpdbBerkasService::allJenisKeys() as $jenis) {
            $fields[$jenis] = $jenis;
        }
        foreach ($fields as $field => $jenis) {
            if ($request->hasFile($field)) {
                $this->uploadBerkas($user, $jenis, $request->file($field));
            }
        }

        return $this->saveFormulir($user, $data);
    }

    public function submitForUser(User $user): Pendaftaran
    {
        return $this->processSubmit($user);
    }

    public function hasLegacyUploads(Request $request): bool
    {
        foreach (['file_ijazah', 'file_stk', 'file_pas_photo', 'file_nisn', 'file_kk', 'file_ktp_ortua'] as $key) {
            if ($request->hasFile($key)) {
                return true;
            }
        }

        return false;
    }

    public function getPublicInfo(): array
    {
        $getJson = function ($key, $default) {
            $val = SystemSetting::getValue($key);
            if (! $val) {
                return $default;
            }
            $decoded = json_decode($val, true);

            return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $default;
        };

        $profil = ProfilSekolah::first();
        $officialPersyaratan = [
            'Mengisi formulir pendaftaran online',
            'Foto Copy Ijazah/SKHUN: 2 lembar',
            'STK asli dan foto copy (dilegalisir): 2 lembar',
            'Pas photo 3x4 cm (pakai jilbab): 4 lembar',
            'NISN',
            'FC Kartu Keluarga: 1 lembar',
            'FC KTP Orang Tua: 1 lembar',
        ];
        $normalizePersyaratan = function (array $items) use ($officialPersyaratan) {
            $joined = implode(' ', $items);
            if (str_contains($joined, 'Fotokopi akta kelahiran') || str_contains($joined, 'Fotokopi KIP')) {
                return $officialPersyaratan;
            }

            return $items;
        };

        return [
            'nama_sekolah' => $profil?->nama_sekolah ?: 'MAS Aisyiyah Medan',
            'judul' => SystemSetting::getValue('ppdb_judul') ?: 'Penerimaan Peserta Didik Baru',
            'tahun_ajaran' => SystemSetting::getValue('ppdb_tahun_ajaran') ?: '2026/2027',
            'deskripsi' => SystemSetting::getValue('ppdb_deskripsi') ?: 'MAS Aisyiyah Medan membuka pendaftaran peserta didik baru Tahun Pelajaran 2026/2027. Buat akun calon murid, lengkapi formulir dan berkas, lalu pantau status pendaftaran secara online.',
            'ekstrakurikuler' => $getJson('ppdb_ekstrakurikuler', []),
            'hero_highlights' => $getJson('ppdb_hero_highlights', [
                ['teks' => 'Gratis uang pembangunan', 'ikon' => 'gift'],
                ['teks' => 'Gratis pendaftaran 20 pendaftar pertama', 'ikon' => 'award'],
                ['teks' => 'Diskon alumni Muhammadiyah/Aisyiyah', 'ikon' => 'percent'],
                ['teks' => 'Gratis SPP bagi anak yatim (syarat berlaku)', 'ikon' => 'heart'],
            ]),
            'gelombang' => $getJson('ppdb_gelombang', [
                [
                    'id' => 'gelombang-1',
                    'judul' => 'Gelombang 1',
                    'periode' => 'Januari – Maret 2026',
                    'badge' => 'Dibuka',
                    'keuntungan' => [
                        'Gratis uang pembangunan',
                        'Gratis uang ekskul 6 bulan untuk juara 1, 2, 3',
                    ],
                ],
                [
                    'id' => 'gelombang-2',
                    'judul' => 'Gelombang 2',
                    'periode' => 'April – Juni 2026',
                    'badge' => 'Segera',
                    'keuntungan' => [
                        'Gratis uang pembangunan',
                        'Gratis uang ekskul 3 bulan untuk juara 1, 2, 3',
                    ],
                ],
            ]),
            'promo' => $getJson('ppdb_promo', [
                [
                    'judul' => 'Gratis Biaya Pendaftaran',
                    'deskripsi' => 'Untuk 20 pendaftar pertama pada periode yang ditentukan.',
                    'ikon' => 'gift',
                ],
                [
                    'judul' => 'Diskon Alumni 50%',
                    'deskripsi' => 'Alumni SMP/MTs Muhammadiyah/Aisyiyah mendapat diskon khusus.',
                    'ikon' => 'percent',
                ],
                [
                    'judul' => 'Gratis SPP Anak Yatim',
                    'deskripsi' => 'Program bantuan SPP bagi anak yatim sesuai ketentuan sekolah.',
                    'ikon' => 'heart',
                ],
                [
                    'judul' => 'Lingkungan Islami',
                    'deskripsi' => 'Pembinaan karakter dan keagamaan terintegrasi setiap hari.',
                    'ikon' => 'church',
                ],
                [
                    'judul' => 'Guru Berpengalaman',
                    'deskripsi' => 'Tenaga pendidik profesional dan berkompeten di bidangnya.',
                    'ikon' => 'graduation',
                ],
                [
                    'judul' => 'Pembelajaran Modern',
                    'deskripsi' => 'Kurikulum nasional dengan penguatan IPTEK dan literasi digital.',
                    'ikon' => 'monitor',
                ],
            ]),
            'persyaratan' => $normalizePersyaratan($getJson('ppdb_persyaratan', $officialPersyaratan)),
            'fasilitas' => $getJson('ppdb_fasilitas', [
                ['nama' => 'Ruang Kelas Luas', 'ikon' => 'building'],
                ['nama' => 'Ruang Perpustakaan', 'ikon' => 'library'],
                ['nama' => 'Mushola', 'ikon' => 'church'],
                ['nama' => 'Lapangan Olahraga', 'ikon' => 'volleyball'],
                ['nama' => 'Ruang Komputer', 'ikon' => 'monitor'],
                ['nama' => 'Ruang Keterampilan', 'ikon' => 'utensils'],
            ]),

            'alur' => $getJson('ppdb_alur', [
                'Lihat informasi PPDB',
                'Buat akun calon murid',
                'Login calon murid',
                'Isi formulir online',
                'Upload berkas',
                'Submit pendaftaran',
                'Verifikasi admin',
                'Pengumuman hasil',
            ]),
            'kontak' => $getJson('ppdb_kontak', [
                [
                    'nama' => 'Muharleny Br Damanik, S.Ag',
                    'telepon' => ['+62 813 9686 5480'],
                ],
                [
                    'nama' => 'Sri Wahyuni, S.Pd',
                    'telepon' => ['+62 813 7444 5100'],
                ],
                [
                    'nama' => 'Anggi Mira, S.Pd',
                    'telepon' => ['+62 813 9686 5480', '+62 813 7444 5100'],
                ],
            ]),
            'alamat' => $profil?->alamat ?: 'Jl. Demak No. 3, Medan',
            'brosur' => $this->resolveBrosurPublicUrl(),
            'diperbarui_pada' => now()->toIso8601String(),
        ];
    }

    public function resolveBrosurStoragePath(): ?string
    {
        $brosurUrl = SystemSetting::getValue('ppdb_brosur');

        if ($brosurUrl) {
            $path = parse_url($brosurUrl, PHP_URL_PATH) ?: '';
            $path = preg_replace('/^\/storage\//', '', $path);

            if ($path && Storage::disk('public')->exists($path)) {
                return $path;
            }
        }

        $files = Storage::disk('public')->files('ppdb/brosur');
        if (empty($files)) {
            return null;
        }

        usort($files, function ($a, $b) {
            return Storage::disk('public')->lastModified($b) <=> Storage::disk('public')->lastModified($a);
        });

        return $files[0] ?? null;
    }

    public function resolveBrosurPublicUrl(): ?string
    {
        $path = $this->resolveBrosurStoragePath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * @deprecated Gunakan AccountRegistrationService via POST /auth/register-calon-siswa (hanya akun, tanpa PPDB).
     */
    public function registerCalon(array $data): User
    {
        if (User::where('username', $data['nisn'] ?? $data['username'])->exists()) {
            throw new InvalidArgumentException('NISN sudah terdaftar.');
        }
        if (User::where('email', $data['email'])->exists()) {
            throw new InvalidArgumentException('Email sudah terdaftar.');
        }

        return $this->auth->registerCalonSiswa([
            'name' => $data['nama_lengkap'] ?? $data['nama'] ?? $data['name'] ?? '',
            'email' => $data['email'],
            'username' => $data['nisn'] ?? $data['username'],
            'password' => $data['password'],
        ]);
    }

    public function getByUser(User $user): ?Pendaftaran
    {
        return Pendaftaran::with($this->pendaftaranRelations())
            ->where('id_user', $user->id_user)
            ->first();
    }

    public function pendaftaranRelations(): array
    {
        return [
            'berkas',
            'user',
            'keteranganPribadi',
            'kesehatan',
            'pendidikanAsal',
            'orangTuaWali',
            'kepribadian',
            'dokumen',
        ];
    }

    public function syncPendaftaranDetail(Pendaftaran $pendaftaran, array $data): void
    {
        $groups = [
            'keteranganPribadi' => [
                'nisn',
                'nama_lengkap',
                'tempat_lahir',
                'tgl_lahir',
                'jenis_kelamin',
                'agama',
                'kewarganegaraan',
                'anak_ke',
                'jml_saudara_kandung',
                'jml_saudara_tiri',
                'alamat',
                'no_telp',
                'status_yatim',
            ],
            'kesehatan' => [
                'berat_badan',
                'tinggi_badan',
                'gol_darah',
                'penyakit_diderita',
                'cacat_badan',
            ],
            'pendidikanAsal' => [
                'sekolah_asal',
                'tahun_lulus',
                'no_sttb',
                'pindahan_dari',
                'no_surat_pindah',
            ],
            'orangTuaWali' => [
                'nama_ayah',
                'nama_ibu',
                'pendidikan_ayah',
                'pendidikan_ibu',
                'pekerjaan_ayah',
                'pekerjaan_ibu',
                'agama_ortu',
                'alamat_ortu',
                'no_hp_ortu',
                'no_hp_ayah',
                'no_hp_ibu',
                'nama_wali',
                'pendidikan_wali',
                'pekerjaan_wali',
                'agama_wali',
                'alamat_wali',
            ],
            'kepribadian' => [
                'hobi',
                'cita_cita',
            ],
            'dokumen' => [
                'file_ijazah',
                'file_stk',
                'file_pas_photo',
                'file_nisn',
                'file_kk',
                'file_ktp_ortua',
                'catatan_dokumen',
            ],
        ];

        foreach ($groups as $relation => $fields) {
            $payload = array_intersect_key($data, array_flip($fields));

            if ($payload === []) {
                continue;
            }

            $pendaftaran->{$relation}()->updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id_pendaftaran],
                $payload
            );
        }
    }

    public function saveFormulir(User $user, array $data): Pendaftaran
    {
        $mapped = $this->mapFormulirInput($data);
        $pendaftaran = $this->state->saveDraft($user, $mapped);
        $this->syncPendaftaranDetail($pendaftaran, $mapped);

        if (! empty($data['nisn'])) {
            $pendaftaran->nisn = $data['nisn'];
            $pendaftaran->save();
        }

        return $pendaftaran->fresh($this->pendaftaranRelations());
    }

    public function uploadBerkas(User $user, string $jenis, UploadedFile $file): array
    {
        return $this->berkasService->upload($user, $jenis, $file);
    }

    public function processSubmit(User $user): Pendaftaran
    {
        $pendaftaran = $this->state->submit($user);
        if (! $pendaftaran->no_registrasi) {
            $pendaftaran->no_registrasi = $this->generateNoRegistrasi();
            $pendaftaran->submitted_at = now();
            $pendaftaran->save();
        }

        return $pendaftaran->fresh(['berkas']);
    }

    public function adminList(?string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        $query = Pendaftaran::with(['user', 'berkas'])->orderByDesc('updated_at');

        if ($status === 'belum_mengirim') {
            $query->where('ppdb_status', 'draft');
        } elseif ($status === 'sudah_mengirim') {
            $query->whereIn('ppdb_status', ['submitted', 'diajukan', 'terverifikasi', 'verified']);
        } elseif ($status === 'revisi') {
            $query->whereIn('ppdb_status', ['revisi', 'dikembalikan']);
        } elseif ($status === 'diterima') {
            $query->whereIn('ppdb_status', ['diterima', 'accepted', 'menjadi_murid']);
        } elseif ($status === 'ditolak') {
            $query->whereIn('ppdb_status', ['ditolak', 'rejected']);
        } elseif ($status) {
            $query->where('ppdb_status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('no_registrasi', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($perPage);
    }

    public function adminFind(int $id): Pendaftaran
    {
        $pendaftaran = Pendaftaran::with(['user', 'berkas', 'dokumen'])->findOrFail($id);

        $dokumen = $pendaftaran->dokumen;
        if ($dokumen) {
            $pendaftaran->file_ijazah ??= $dokumen->file_ijazah;
            $pendaftaran->file_stk ??= $dokumen->file_stk;
            $pendaftaran->file_pas_photo ??= $dokumen->file_pas_photo;
            $pendaftaran->file_nisn ??= $dokumen->file_nisn;
            $pendaftaran->file_kk ??= $dokumen->file_kk;
            $pendaftaran->file_ktp_ortua ??= $dokumen->file_ktp_ortua;
        }

        return $pendaftaran;
    }

    public function adminVerifikasi(int $id): Pendaftaran
    {
        return $this->state->transitionByAdmin(
            Pendaftaran::findOrFail($id),
            'terverifikasi'
        );
    }

    public function adminRevisi(int $id, string $catatan): Pendaftaran
    {
        return $this->state->transitionByAdmin(
            Pendaftaran::findOrFail($id),
            'revisi',
            $catatan
        );
    }

    public function adminTerima(int $id, ?string $catatan = null): Pendaftaran
    {
        return DB::transaction(function () use ($id, $catatan) {
            $pendaftaran = $this->state->transitionByAdmin(
                Pendaftaran::findOrFail($id),
                'diterima',
                $catatan
            );

            // Auto enroll menjadi siswa
            $this->enrollment->enrollCalonSiswa($pendaftaran->id_user);

            return $pendaftaran->fresh(['user', 'berkas']);
        });
    }

    public function adminTolak(int $id, string $catatan): Pendaftaran
    {
        return $this->state->transitionByAdmin(
            Pendaftaran::findOrFail($id),
            'ditolak',
            $catatan
        );
    }

    public function adminJadikanMurid(int $id, ?int $idKelas = null): array
    {
        $pendaftaran = Pendaftaran::findOrFail($id);

        return $this->enrollment->enrollCalonSiswa($pendaftaran->id_user, $idKelas);
    }

    public function getBuktiData(Pendaftaran $pendaftaran): array
    {
        return [
            'no_registrasi' => $pendaftaran->no_registrasi,
            'nama_lengkap' => $pendaftaran->nama_lengkap,
            'nisn' => $pendaftaran->nisn ?? $pendaftaran->user?->username,
            'status' => $pendaftaran->ppdb_status,
            'submitted_at' => $pendaftaran->submitted_at,
            'accepted_at' => $pendaftaran->accepted_at,
            'sekolah' => 'MAS Aisyiyah Medan',
            'tahun_ajaran' => '2026/2027',
        ];
    }

    public function generateNoRegistrasi(): string
    {
        $prefix = 'PPDB-'.now()->year.'-';

        return app(SchoolNumberService::class)->next('ppdb-'.now()->year, $prefix, 'pendaftaran', 'no_registrasi');
    }

    public function mapFormulirInput(array $data): array
    {
        $map = [
            'tanggal_lahir' => 'tgl_lahir',
            'asal_sekolah' => 'sekolah_asal',
        ];

        $out = [];
        foreach ($data as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }

        return $out;
    }

    public function normalizeNumericInputs(Request $request, array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (! $request->exists($field)) {
                continue;
            }

            $value = $request->input($field);
            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $normalized[$field] = null;

                    continue;
                }
            }

            if (is_numeric($value)) {
                $normalized[$field] = (int) $value;
            }
        }

        return $normalized;
    }
}
