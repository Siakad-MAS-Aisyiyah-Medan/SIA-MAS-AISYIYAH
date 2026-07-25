<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\KomponenPenilaian;
use App\Models\Mapel;
use App\Models\Nilai;
use App\Models\NilaiKomponenSiswa;
use App\Models\Siswa;
use App\Models\SkemaPenilaian;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FlexibleNilaiService
{
    private const LEGACY_COMPONENTS = [
        'nilai_tugas' => 'Tugas',
        'nilai_uts' => 'UTS',
        'nilai_uas' => 'UAS',
        'nilai_praktik' => 'Praktik',
        'nilai_sikap' => 'Sikap',
    ];

    public function __construct(private NilaiCalculationService $calculator) {}

    public function getFormData(int $guruId, array $params): array
    {
        $this->assertGuruCanManageContext($guruId, $params);
        $skema = $this->getOrCreateScheme($guruId, $params);

        $siswaList = Siswa::with('user')
            ->aktif()
            ->where('id_kelas', $params['id_kelas'])
            ->orderBy('nama_siswa')
            ->get();

        $komponen = $skema->komponenAktif()->get();
        $componentIds = $komponen->pluck('id_komponen');
        $scores = NilaiKomponenSiswa::query()
            ->whereIn('id_komponen', $componentIds)
            ->whereIn('id_user_siswa', $siswaList->pluck('id_user'))
            ->get()
            ->groupBy('id_user_siswa');

        $summaries = Nilai::query()
            ->where('id_mapel', $params['id_mapel'])
            ->where('semester', $params['semester'])
            ->where('tahun_ajaran', $params['tahun_ajaran'])
            ->whereIn('id_user_siswa', $siswaList->pluck('id_user'))
            ->get()
            ->keyBy('id_user_siswa');

        $rows = $siswaList->map(function (Siswa $siswa) use ($scores, $summaries) {
            $studentScores = $scores->get($siswa->id_user, collect())
                ->mapWithKeys(fn (NilaiKomponenSiswa $score) => [
                    (string) $score->id_komponen => $score->nilai,
                ]);
            $summary = $summaries->get($siswa->id_user);

            return [
                'id_user_siswa' => $siswa->id_user,
                'nama_siswa' => $siswa->nama_siswa,
                'nisn' => $siswa->nisn ?: $siswa->user?->username,
                'jenis_kelamin' => $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : ($siswa->jenis_kelamin === 'P' ? 'Perempuan' : null),
                'nilai_komponen' => $studentScores->all(),
                'nilai_akhir' => $summary?->nilai_akhir,
                'predikat' => $summary?->predikat,
                'lengkap' => $summary?->nilai_akhir !== null,
                'validated_by_wali' => (bool) ($summary?->validated_by_wali ?? false),
            ];
        });

        return [
            'meta' => [
                'id_kelas' => (int) $params['id_kelas'],
                'id_mapel' => (int) $params['id_mapel'],
                'tahun_ajaran' => $params['tahun_ajaran'],
                'semester' => $params['semester'],
            ],
            'skema' => $this->serializeScheme($skema, $komponen),
            'siswa' => $rows->values()->all(),
        ];
    }

    public function saveScheme(int $guruId, array $payload): array
    {
        $skema = SkemaPenilaian::findOrFail($payload['id_skema']);
        $this->assertGuruOwnsScheme($guruId, $skema);

        $components = collect($payload['komponen'] ?? []);
        if ($components->isEmpty()) {
            throw new InvalidArgumentException('Skema harus memiliki minimal satu komponen nilai.');
        }

        $normalizedNames = $components
            ->map(fn ($item) => mb_strtolower(trim((string) ($item['nama_komponen'] ?? ''))));
        if ($normalizedNames->contains('')) {
            throw new InvalidArgumentException('Nama komponen nilai wajib diisi.');
        }
        if ($normalizedNames->unique()->count() !== $normalizedNames->count()) {
            throw new InvalidArgumentException('Nama komponen nilai tidak boleh sama.');
        }

        $totalWeight = round((float) $components->sum(fn ($item) => (float) $item['bobot']), 2);
        if (abs($totalWeight - 100) > 0.001) {
            throw new InvalidArgumentException("Total bobot harus tepat 100%. Total saat ini {$totalWeight}%.");
        }

        return DB::transaction(function () use ($skema, $payload, $components) {
            $existing = $skema->komponen()->get()->keyBy('id_komponen');
            $keptIds = [];
            $usedCodes = [];

            foreach ($components->values() as $index => $input) {
                $componentId = isset($input['id_komponen']) ? (int) $input['id_komponen'] : null;
                $component = $componentId ? $existing->get($componentId) : null;

                if ($componentId && ! $component) {
                    throw new InvalidArgumentException('Komponen nilai tidak menjadi bagian dari skema ini.');
                }

                $code = $component?->kode_komponen ?: $this->uniqueComponentCode(
                    (string) $input['nama_komponen'],
                    $usedCodes,
                    $index
                );
                $usedCodes[] = $code;

                $component = $component ?: new KomponenPenilaian(['id_skema' => $skema->id_skema]);
                $component->fill([
                    'nama_komponen' => trim((string) $input['nama_komponen']),
                    'kode_komponen' => $code,
                    'bobot' => round((float) $input['bobot'], 2),
                    'urutan' => $index + 1,
                    'is_active' => true,
                ])->save();
                $keptIds[] = $component->id_komponen;
            }

            $skema->komponen()
                ->whereNotIn('id_komponen', $keptIds)
                ->update(['is_active' => false]);

            $skema->update([
                'nama_skema' => trim((string) ($payload['nama_skema'] ?? 'Skema Penilaian')),
                'versi' => $skema->versi + 1,
            ]);

            $this->recalculateScheme($skema->fresh());

            $fresh = $skema->fresh();

            return $this->serializeScheme($fresh, $fresh->komponenAktif()->get());
        });
    }

    public function saveComponentScores(int $guruId, array $payload): array
    {
        $meta = $payload['meta'];
        $skema = SkemaPenilaian::findOrFail($meta['id_skema']);
        $this->assertGuruOwnsScheme($guruId, $skema);
        $this->assertSchemeMatchesMeta($skema, $meta);

        $component = KomponenPenilaian::query()
            ->where('id_komponen', $meta['id_komponen'])
            ->where('id_skema', $skema->id_skema)
            ->where('is_active', true)
            ->first();

        if (! $component) {
            throw new InvalidArgumentException('Komponen nilai tidak aktif atau tidak ditemukan.');
        }

        $items = collect($payload['items']);
        $validStudentIds = Siswa::query()
            ->aktif()
            ->where('id_kelas', $skema->id_kelas)
            ->whereIn('id_user', $items->pluck('id_user_siswa'))
            ->pluck('id_user');

        if ($validStudentIds->count() !== $items->pluck('id_user_siswa')->unique()->count()) {
            throw new InvalidArgumentException('Terdapat murid yang bukan anggota aktif kelas ini.');
        }

        return DB::transaction(function () use ($guruId, $skema, $component, $items) {
            foreach ($items as $item) {
                if ($item['nilai'] === null || $item['nilai'] === '') {
                    $deleted = NilaiKomponenSiswa::query()
                        ->where('id_komponen', $component->id_komponen)
                        ->where('id_user_siswa', $item['id_user_siswa'])
                        ->delete();

                    if ($deleted > 0) {
                        $this->recalculateStudent($skema, (int) $item['id_user_siswa'], $guruId);
                    }
                } else {
                    NilaiKomponenSiswa::updateOrCreate(
                        [
                            'id_komponen' => $component->id_komponen,
                            'id_user_siswa' => $item['id_user_siswa'],
                        ],
                        ['nilai' => round((float) $item['nilai'], 2)]
                    );
                    $this->recalculateStudent($skema, (int) $item['id_user_siswa'], $guruId);
                }
            }

            return [
                'id_komponen' => $component->id_komponen,
                'nama_komponen' => $component->nama_komponen,
                'tersimpan' => $items->count(),
            ];
        });
    }

    private function getOrCreateScheme(int $guruId, array $params): SkemaPenilaian
    {
        return DB::transaction(function () use ($guruId, $params) {
            $scheme = SkemaPenilaian::firstOrCreate(
                [
                    'id_guru' => $guruId,
                    'id_mapel' => $params['id_mapel'],
                    'id_kelas' => $params['id_kelas'],
                    'tahun_ajaran' => $params['tahun_ajaran'],
                    'semester' => $params['semester'],
                ],
                [
                    'nama_skema' => 'Skema Penilaian Default',
                    'status' => 'aktif',
                    'versi' => 1,
                ]
            );

            if (! $scheme->komponen()->exists()) {
                $this->createDefaultComponentsAndImportLegacy($scheme);
            }

            return $scheme->fresh();
        });
    }

    private function createDefaultComponentsAndImportLegacy(SkemaPenilaian $skema): void
    {
        $studentIds = Siswa::query()
            ->where('id_kelas', $skema->id_kelas)
            ->pluck('id_user');

        $legacyRows = Nilai::query()
            ->where('id_mapel', $skema->id_mapel)
            ->where('semester', $skema->semester)
            ->where('tahun_ajaran', $skema->tahun_ajaran)
            ->whereIn('id_user_siswa', $studentIds)
            ->get();

        $hasPractice = $legacyRows->contains(fn (Nilai $nilai) => $nilai->nilai_praktik !== null);
        $definitions = $hasPractice
            ? [
                ['nilai_tugas', 'Tugas', 25],
                ['nilai_uts', 'UTS', 25],
                ['nilai_uas', 'UAS', 30],
                ['nilai_praktik', 'Praktik', 20],
            ]
            : [
                ['nilai_tugas', 'Tugas', 30],
                ['nilai_uts', 'UTS', 30],
                ['nilai_uas', 'UAS', 40],
            ];

        foreach ($definitions as $index => [$code, $name, $weight]) {
            $component = $skema->komponen()->create([
                'nama_komponen' => $name,
                'kode_komponen' => $code,
                'bobot' => $weight,
                'urutan' => $index + 1,
                'is_active' => true,
            ]);

            foreach ($legacyRows as $legacy) {
                if ($legacy->{$code} !== null) {
                    NilaiKomponenSiswa::updateOrCreate(
                        [
                            'id_komponen' => $component->id_komponen,
                            'id_user_siswa' => $legacy->id_user_siswa,
                        ],
                        ['nilai' => $legacy->{$code}]
                    );
                }
            }
        }

        foreach ($legacyRows as $legacy) {
            $legacy->update(['id_skema' => $skema->id_skema]);
            $this->recalculateStudent($skema, (int) $legacy->id_user_siswa, (int) ($legacy->id_guru_input ?: $skema->id_guru));
        }
    }

    private function recalculateScheme(SkemaPenilaian $skema): void
    {
        $allComponentIds = $skema->komponen()->pluck('id_komponen');
        $studentIds = NilaiKomponenSiswa::query()
            ->whereIn('id_komponen', $allComponentIds)
            ->pluck('id_user_siswa')
            ->merge(
                Nilai::query()
                    ->where('id_skema', $skema->id_skema)
                    ->pluck('id_user_siswa')
            )
            ->unique();

        foreach ($studentIds as $studentId) {
            $this->recalculateStudent($skema, (int) $studentId, (int) $skema->id_guru);
        }
    }

    private function recalculateStudent(SkemaPenilaian $skema, int $studentId, int $guruId): Nilai
    {
        $components = $skema->komponenAktif()->get();
        $scores = NilaiKomponenSiswa::query()
            ->whereIn('id_komponen', $components->pluck('id_komponen'))
            ->where('id_user_siswa', $studentId)
            ->get()
            ->keyBy('id_komponen');

        $complete = $components->isNotEmpty()
            && $components->every(fn (KomponenPenilaian $component) => $scores->has($component->id_komponen));
        $finalScore = null;

        if ($complete) {
            $finalScore = (int) round($components->sum(function (KomponenPenilaian $component) use ($scores) {
                return (float) $scores->get($component->id_komponen)->nilai * ((float) $component->bobot / 100);
            }));
        }

        $legacyValues = collect(array_keys(self::LEGACY_COMPONENTS))
            ->mapWithKeys(fn ($field) => [$field => null])
            ->all();

        foreach ($components as $component) {
            if (array_key_exists($component->kode_komponen, self::LEGACY_COMPONENTS)) {
                $legacyValues[$component->kode_komponen] = $scores->get($component->id_komponen)?->nilai;
            }
        }

        return Nilai::updateOrCreate(
            [
                'id_user_siswa' => $studentId,
                'id_mapel' => $skema->id_mapel,
                'semester' => $skema->semester,
                'tahun_ajaran' => $skema->tahun_ajaran,
            ],
            array_merge($legacyValues, [
                'id_guru_input' => $guruId,
                'id_skema' => $skema->id_skema,
                'nilai_akhir' => $finalScore,
                'nilai_angka' => $finalScore,
                'predikat' => $finalScore === null ? null : $this->calculator->predikat($finalScore),
                'validated_by_wali' => false,
                'id_wali_validator' => null,
                'validated_at' => null,
            ])
        );
    }

    private function serializeScheme(SkemaPenilaian $skema, Collection $components): array
    {
        return [
            'id_skema' => $skema->id_skema,
            'nama_skema' => $skema->nama_skema,
            'status' => $skema->status,
            'versi' => $skema->versi,
            'total_bobot' => round((float) $components->sum('bobot'), 2),
            'komponen' => $components->map(fn (KomponenPenilaian $component) => [
                'id_komponen' => $component->id_komponen,
                'nama_komponen' => $component->nama_komponen,
                'kode_komponen' => $component->kode_komponen,
                'bobot' => (float) $component->bobot,
                'urutan' => $component->urutan,
            ])->values()->all(),
        ];
    }

    private function uniqueComponentCode(string $name, array $usedCodes, int $index): string
    {
        $base = Str::slug($name, '_') ?: 'komponen_'.($index + 1);
        $code = $base;
        $suffix = 2;

        while (in_array($code, $usedCodes, true)) {
            $code = $base.'_'.$suffix++;
        }

        return $code;
    }

    private function assertGuruOwnsScheme(int $guruId, SkemaPenilaian $skema): void
    {
        $user = User::findOrFail($guruId);
        if ($user->role !== 'admin' && (int) $skema->id_guru !== $guruId) {
            throw new InvalidArgumentException('Anda tidak berhak mengubah skema penilaian ini.');
        }
    }

    private function assertSchemeMatchesMeta(SkemaPenilaian $skema, array $meta): void
    {
        foreach (['id_kelas', 'id_mapel'] as $field) {
            if ((int) $skema->{$field} !== (int) $meta[$field]) {
                throw new InvalidArgumentException('Konteks skema penilaian tidak sesuai.');
            }
        }
        foreach (['tahun_ajaran', 'semester'] as $field) {
            if ((string) $skema->{$field} !== (string) $meta[$field]) {
                throw new InvalidArgumentException('Periode skema penilaian tidak sesuai.');
            }
        }
    }

    private function assertGuruCanManageContext(int $guruId, array $params): void
    {
        $user = User::findOrFail($guruId);
        if ($user->role === 'admin') {
            return;
        }
        if ($user->role !== 'guru') {
            throw new InvalidArgumentException('Hanya guru yang dapat mengatur nilai siswa.');
        }

        $mapel = Mapel::findOrFail($params['id_mapel']);
        $assigned = (int) $mapel->id_guru === $guruId
            || JadwalPelajaran::query()
                ->where('id_guru', $guruId)
                ->where('id_mapel', $params['id_mapel'])
                ->where('id_kelas', $params['id_kelas'])
                ->where('tahun_ajaran', $params['tahun_ajaran'])
                ->where('semester', $params['semester'])
                ->exists();

        if (! $assigned) {
            throw new InvalidArgumentException('Anda bukan pengampu mata pelajaran pada kelas dan periode ini.');
        }
    }
}
