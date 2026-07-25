<?php

namespace App\Services;

use App\Http\Resources\NilaiResource;
use App\Models\Mapel;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NilaiService
{
    public function __construct(private NilaiCalculationService $calculator) {}

    public function getFormData(int $guruId, array $params): array
    {
        $this->assertGuruCanInput($guruId, (int) $params['id_mapel']);

        $siswaList = Siswa::with('user')
            ->aktif()
            ->where('id_kelas', $params['id_kelas'])
            ->orderBy('nama_siswa')
            ->get();

        $existing = Nilai::query()
            ->where('id_mapel', $params['id_mapel'])
            ->where('semester', $params['semester'])
            ->where('tahun_ajaran', $params['tahun_ajaran'])
            ->whereIn('id_user_siswa', $siswaList->pluck('id_user'))
            ->get()
            ->keyBy('id_user_siswa');

        $rows = $siswaList->map(function (Siswa $siswa) use ($existing) {
            $nilai = $existing->get($siswa->id_user);

            return [
                'id_user_siswa' => $siswa->id_user,
                'nama_siswa' => $siswa->nama_siswa,
                'nisn' => $siswa->nisn ?: $siswa->user?->username,
                'jenis_kelamin' => $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : ($siswa->jenis_kelamin === 'P' ? 'Perempuan' : null),
                'id_nilai' => $nilai?->id_nilai,
                'nilai_tugas' => $nilai?->nilai_tugas,
                'nilai_uts' => $nilai?->nilai_uts,
                'nilai_uas' => $nilai?->nilai_uas,
                'nilai_praktik' => $nilai?->nilai_praktik,
                'nilai_sikap' => $nilai?->nilai_sikap,
                'nilai_akhir' => $nilai?->nilai_akhir,
                'predikat' => $nilai?->predikat,
                'validated_by_wali' => (bool) ($nilai?->validated_by_wali ?? false),
            ];
        });

        return [
            'meta' => [
                'id_kelas' => (int) $params['id_kelas'],
                'id_mapel' => (int) $params['id_mapel'],
                'tahun_ajaran' => $params['tahun_ajaran'],
                'semester' => $params['semester'],
            ],
            'siswa' => $rows->values()->all(),
        ];
    }

    public function bulkSave(int $guruId, array $payload): array
    {
        $meta = $payload['meta'];
        $items = $payload['items'] ?? [];
        $singleComponent = $meta['komponen_nilai'] ?? null;
        $allowedComponents = ['nilai_tugas', 'nilai_uts', 'nilai_uas', 'nilai_praktik', 'nilai_sikap'];

        $this->assertGuruCanInput($guruId, (int) $meta['id_mapel']);

        if ($singleComponent !== null && ! in_array($singleComponent, $allowedComponents, true)) {
            throw new InvalidArgumentException('Komponen nilai tidak valid.');
        }

        return DB::transaction(function () use ($guruId, $meta, $items, $singleComponent, $allowedComponents) {
            $saved = [];

            foreach ($items as $row) {
                $existing = Nilai::query()
                    ->where('id_user_siswa', $row['id_user_siswa'])
                    ->where('id_mapel', $meta['id_mapel'])
                    ->where('semester', $meta['semester'])
                    ->where('tahun_ajaran', $meta['tahun_ajaran'])
                    ->first();

                $input = [];
                foreach ($allowedComponents as $component) {
                    if ($singleComponent !== null) {
                        $input[$component] = $component === $singleComponent
                            ? ($row[$component] ?? null)
                            : $existing?->{$component};
                    } else {
                        $input[$component] = array_key_exists($component, $row)
                            ? $row[$component]
                            : $existing?->{$component};
                    }
                }

                $calculated = $this->calculator->calculate([
                    'nilai_tugas' => $input['nilai_tugas'],
                    'nilai_uts' => $input['nilai_uts'],
                    'nilai_uas' => $input['nilai_uas'],
                    'nilai_praktik' => $input['nilai_praktik'],
                    'nilai_sikap' => $input['nilai_sikap'],
                ]);

                $nilai = Nilai::updateOrCreate(
                    [
                        'id_user_siswa' => $row['id_user_siswa'],
                        'id_mapel' => $meta['id_mapel'],
                        'semester' => $meta['semester'],
                        'tahun_ajaran' => $meta['tahun_ajaran'],
                    ],
                    array_merge($calculated, [
                        'id_guru_input' => $guruId,
                        'validated_by_wali' => false,
                        'id_wali_validator' => null,
                        'validated_at' => null,
                    ])
                );

                $saved[] = (new NilaiResource(
                    $nilai->load(['siswa.siswa', 'mapel', 'guruInput.guru'])
                ))->resolve();
            }

            return $saved;
        });
    }

    public function listForSiswa(int $userId, array $filters = [], bool $onlyValidated = false): Collection
    {
        $query = Nilai::with(['mapel', 'guruInput.guru'])
            ->where('id_user_siswa', $userId);

        if ($onlyValidated) {
            $query->where('validated_by_wali', true);
        }

        $this->applyFilters($query, $filters);

        return $query->orderBy('semester')->orderBy('tahun_ajaran')->get()->map(
            fn ($item) => (new NilaiResource($item))->resolve()
        );
    }

    public function raportForSiswa(int $userId, string $semester, string $tahunAjaran): array
    {
        return $this->raportForStudent($userId, $semester, $tahunAjaran);
    }

    public function raportForStudent(int $userId, string $semester, string $tahunAjaran): array
    {
        $items = Nilai::with('mapel')
            ->where('id_user_siswa', $userId)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('id_mapel')
            ->get();

        $siswa = Siswa::with(['kelas', 'user'])->where('id_user', $userId)->first();

        return [
            'siswa' => [
                'nama_siswa' => $siswa?->nama_siswa,
                'nisn' => $siswa?->nisn ?: $siswa?->user?->username,
                'kelas' => $siswa?->kelas?->nama_kelas,
            ],
            'semester' => $semester,
            'tahun_ajaran' => $tahunAjaran,
            'mapel' => $items->map(fn ($n) => (new NilaiResource($n))->resolve())->values()->all(),
            'rata_rata' => $items->isEmpty() ? null : (int) round($items->avg('nilai_akhir')),
        ];
    }

    public function laporan(array $filters = []): array
    {
        return app(LaporanService::class)->generate(
            auth()->user(),
            'nilai',
            array_merge($filters, ['per_page' => 1, 'page' => 1])
        )['summary'] ?? [];
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['id_mapel'])) {
            $query->where('id_mapel', $filters['id_mapel']);
        }
        if (! empty($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }
        if (! empty($filters['tahun_ajaran'])) {
            $query->where('tahun_ajaran', $filters['tahun_ajaran']);
        }
        if (isset($filters['validated_by_wali'])) {
            $query->where('validated_by_wali', (bool) $filters['validated_by_wali']);
        }
    }

    private function assertGuruCanInput(int $guruId, int $mapelId): void
    {
        $user = User::findOrFail($guruId);

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role !== 'guru') {
            throw new InvalidArgumentException('Hanya guru yang dapat menginput nilai siswa.');
        }

        $mapel = Mapel::findOrFail($mapelId);
        if ((int) $mapel->id_guru !== $guruId) {
            throw new InvalidArgumentException('Anda bukan pengampu mata pelajaran ini.');
        }
    }

    private function buildSummary(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'validated' => $rows->where('validated_by_wali', true)->count(),
            'belum_validasi' => $rows->where('validated_by_wali', false)->count(),
            'per_predikat' => $this->predikatBreakdown($rows),
        ];
    }

    private function predikatBreakdown(Collection $rows): array
    {
        $counts = $rows->groupBy('predikat')->map->count();

        return collect(['A', 'B', 'C', 'D', 'E'])->map(fn ($g) => [
            'predikat' => $g,
            'total' => (int) ($counts[$g] ?? 0),
        ])->values()->all();
    }

    private function perKelasBreakdown(Collection $rows, array $filters): array
    {
        if (! empty($filters['id_kelas'])) {
            return [];
        }

        $siswaKelas = Siswa::query()
            ->whereIn('id_user', $rows->pluck('id_user_siswa')->unique())
            ->with('kelas')
            ->get()
            ->keyBy('id_user');

        return $rows->groupBy(fn ($n) => $siswaKelas->get($n->id_user_siswa)?->id_kelas ?? 0)
            ->map(function ($group, $idKelas) use ($siswaKelas) {
                $first = $group->first();
                $kelasNama = $siswaKelas->get($first->id_user_siswa)?->kelas?->nama_kelas ?? 'Tidak diketahui';

                return [
                    'id_kelas' => (int) $idKelas,
                    'nama_kelas' => $kelasNama,
                    'total' => $group->count(),
                    'rata_rata' => round($group->avg('nilai_akhir'), 1),
                ];
            })
            ->values()
            ->all();
    }
}
