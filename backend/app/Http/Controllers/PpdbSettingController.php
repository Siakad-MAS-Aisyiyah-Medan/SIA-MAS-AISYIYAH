<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PpdbSettingController extends Controller
{
    use AuditsAdminActions;

    private const OFFICIAL_PPDB_PERSYARATAN = [
        'Mengisi formulir pendaftaran online',
        'Foto Copy Ijazah/SKHUN: 2 lembar',
        'STK asli dan foto copy (dilegalisir): 2 lembar',
        'Pas photo 3x4 cm (pakai jilbab): 4 lembar',
        'NISN',
        'FC Kartu Keluarga: 1 lembar',
        'FC KTP Orang Tua: 1 lembar',
    ];

    /**
     * Get all PPDB settings
     */
    public function getSettings()
    {
        $keys = [
            'ppdb_judul',
            'ppdb_tahun_ajaran',
            'ppdb_deskripsi',
            'ppdb_hero_highlights',
            'ppdb_gelombang',
            'ppdb_promo',
            'ppdb_persyaratan',
            'ppdb_fasilitas',
            'ppdb_ekstrakurikuler',

            'ppdb_alur',
            'ppdb_kontak',
            'ppdb_alamat',
            'ppdb_brosur',
        ];

        $defaults = [
            'ppdb_judul' => 'Penerimaan Peserta Didik Baru',
            'ppdb_tahun_ajaran' => '2026/2027',
            'ppdb_deskripsi' => 'MAS Aisyiyah Medan membuka pendaftaran peserta didik baru Tahun Pelajaran 2026/2027. Buat akun calon murid, lengkapi formulir dan berkas, lalu pantau status pendaftaran secara online.',
            'ppdb_hero_highlights' => [
                ['teks' => 'Gratis uang pembangunan', 'ikon' => 'gift'],
                ['teks' => 'Gratis pendaftaran 20 pendaftar pertama', 'ikon' => 'award'],
                ['teks' => 'Diskon alumni Muhammadiyah/Aisyiyah', 'ikon' => 'percent'],
                ['teks' => 'Gratis SPP bagi anak yatim (syarat berlaku)', 'ikon' => 'heart'],
            ],
            'ppdb_gelombang' => [
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
            ],
            'ppdb_promo' => [
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
            ],
            'ppdb_persyaratan' => self::OFFICIAL_PPDB_PERSYARATAN,
            'ppdb_ekstrakurikuler' => [],
            'ppdb_fasilitas' => [
                ['nama' => 'Ruang Kelas Luas', 'ikon' => 'building'],
                ['nama' => 'Ruang Perpustakaan', 'ikon' => 'library'],
                ['nama' => 'Mushola', 'ikon' => 'church'],
                ['nama' => 'Lapangan Olahraga', 'ikon' => 'volleyball'],
                ['nama' => 'Ruang Komputer', 'ikon' => 'monitor'],
                ['nama' => 'Ruang Keterampilan', 'ikon' => 'utensils'],
            ],

            'ppdb_alur' => [
                'Lihat informasi PPDB',
                'Buat akun calon murid',
                'Login calon murid',
                'Isi formulir online',
                'Upload berkas',
                'Submit pendaftaran',
                'Verifikasi admin',
                'Pengumuman hasil',
            ],
            'ppdb_kontak' => [
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
            ],
            'ppdb_alamat' => 'Jl. Demak No. 3, Medan',
            'ppdb_brosur' => null,
        ];

        $settings = [];
        foreach ($keys as $key) {
            $val = SystemSetting::getValue($key);
            // Decode JSON if it's an array field
            if (in_array($key, [
                'ppdb_hero_highlights', 'ppdb_gelombang', 'ppdb_promo',
                'ppdb_persyaratan', 'ppdb_fasilitas', 'ppdb_ekstrakurikuler',
                'ppdb_alur', 'ppdb_kontak',
            ])) {
                $decoded = $val ? json_decode($val, true) : null;
                $settings[$key] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : ($defaults[$key] ?? null);
                if ($key === 'ppdb_persyaratan') {
                    $settings[$key] = $this->normalizePersyaratan($settings[$key]);
                }
            } else {
                $settings[$key] = $val ?: ($defaults[$key] ?? null);
            }
        }

        return ApiResponse::success($settings, 'Pengaturan PPDB berhasil diambil');
    }

    private function normalizePersyaratan(array $items): array
    {
        $joined = implode(' ', $items);
        if (str_contains($joined, 'Fotokopi akta kelahiran') || str_contains($joined, 'Fotokopi KIP')) {
            return self::OFFICIAL_PPDB_PERSYARATAN;
        }

        return $items;
    }

    /**
     * Save PPDB settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'ppdb_brosur_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'ppdb_judul' => 'sometimes|nullable|string|max:255',
            'ppdb_tahun_ajaran' => ['sometimes', 'nullable', 'regex:/^\d{4}\/\d{4}$/'],
            'ppdb_deskripsi' => 'sometimes|nullable|string|max:10000',
            'ppdb_alamat' => 'sometimes|nullable|string|max:1000',
            'ppdb_brosur' => 'sometimes|nullable|string|max:2000',
        ]);

        $jsonKeys = [
            'ppdb_hero_highlights', 'ppdb_gelombang', 'ppdb_promo',
            'ppdb_persyaratan', 'ppdb_fasilitas', 'ppdb_ekstrakurikuler',
            'ppdb_alur', 'ppdb_kontak',
        ];

        $data = $request->only(array_merge($jsonKeys, [
            'ppdb_judul', 'ppdb_tahun_ajaran', 'ppdb_deskripsi', 'ppdb_alamat', 'ppdb_brosur',
        ]));
        foreach ($jsonKeys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = is_string($data[$key]) ? json_decode($data[$key], true) : $data[$key];
            if (! is_array($value)) {
                throw ValidationException::withMessages([$key => 'Isi harus berupa daftar JSON yang valid.']);
            }
            $data[$key] = $value;
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $jsonKeys)) {
                $valToSave = is_array($value) ? json_encode($value) : (is_string($value) ? $value : json_encode([]));
                SystemSetting::setValue($key, $valToSave);
            } else {
                SystemSetting::setValue($key, $value);
            }
        }

        // Handle file upload
        if ($request->hasFile('ppdb_brosur_file')) {
            $file = $request->file('ppdb_brosur_file');
            $path = $file->store('ppdb/brosur', 'public');
            $url = Storage::disk('public')->url($path);
            SystemSetting::setValue('ppdb_brosur', $url);
        }

        $this->auditAdmin('admin.ppdb_setting.update', null, ['keys' => array_keys($data)]);

        return ApiResponse::success(null, 'Pengaturan PPDB berhasil disimpan');
    }
}
