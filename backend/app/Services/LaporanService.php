<?php

namespace App\Services;

use App\Http\Resources\AbsensiResource;
use App\Http\Resources\JadwalResource;
use App\Http\Resources\NilaiResource;
use App\Models\Absensi;
use App\Models\JadwalPelajaran;
use App\Models\Mapel;
use App\Models\Nilai;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class LaporanService
{
    public function __construct(private AbsensiSiswaService $absensiSiswa) {}

    public function generate(User $actor, string $jenis, array $filters): array
    {
        $this->assertJenisAllowed($actor, $jenis);
        $filters = $this->normalizeFilters($filters);
        $filters = $this->applyRoleScope($actor, $jenis, $filters);
        $perPage = min(
            (int) ($filters['per_page'] ?? config('laporan.per_page_default', 25)),
            config('laporan.per_page_max', 100)
        );

        $result = match ($jenis) {
            'siswa' => $this->laporanSiswa($filters, $perPage),
            'guru' => $this->laporanGuru($filters, $perPage),
            'ppdb' => $this->laporanPpdb($filters, $perPage),
            'absensi_siswa' => $this->laporanAbsensiSiswa($filters, $perPage),
            'nilai' => $this->laporanNilai($filters, $perPage),
            'jadwal' => $this->laporanJadwal($filters, $perPage),
            default => throw new InvalidArgumentException('Jenis laporan tidak dikenal.'),
        };

        return array_merge([
            'jenis' => $jenis,
            'filters_applied' => $this->publicFilters($filters),
            'export_available' => false,
            'export_message' => 'Export PDF/Excel akan tersedia pada pembaruan berikutnya.',
        ], $result);
    }

    public function laporanSiswa(array $filters, int $perPage): array
    {
        $query = User::with(['siswa.kelas'])
            ->where('role', 'siswa');

        if (! empty($filters['id_kelas'])) {
            $query->whereHas('siswa', fn ($q) => $q->where('id_kelas', $filters['id_kelas']));
        }
        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('username', 'like', "%{$s}%")
                    ->orWhereHas('siswa', fn ($sq) => $sq
                        ->where('nama_siswa', 'like', "%{$s}%")
                        ->orWhere('nisn', 'like', "%{$s}%"));
            });
        }
        if (isset($filters['status_aktif'])) {
            $query->where('status_aktif', (bool) $filters['status_aktif']);
        }

        $paginator = $query->orderBy('username')->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (User $u) => [
            'id_user' => $u->id_user,
            'nisn' => $u->siswa?->nisn ?: $u->username,
            'nama_siswa' => $u->siswa?->nama_siswa,
            'kelas' => $u->siswa?->kelas?->nama_kelas,
            'id_kelas' => $u->siswa?->id_kelas,
            'status_aktif' => $u->status_aktif,
        ])->values()->all();

        $summaryQuery = Siswa::query();
        if (! empty($filters['id_kelas'])) {
            $summaryQuery->where('id_kelas', $filters['id_kelas']);
        }
        $perKelas = (clone $summaryQuery)
            ->selectRaw('id_kelas, COUNT(*) as total')
            ->groupBy('id_kelas')
            ->with('kelas')
            ->get()
            ->map(fn ($row) => [
                'id_kelas' => $row->id_kelas,
                'nama_kelas' => $row->kelas?->nama_kelas ?? 'Tanpa kelas',
                'total' => (int) $row->total,
            ]);

        return [
            'summary' => [
                'total_siswa' => $summaryQuery->count(),
                'per_kelas' => $perKelas->values()->all(),
            ],
            'items' => $items,
            'meta' => $this->metaFromPaginator($paginator),
        ];
    }

    public function laporanGuru(array $filters, int $perPage): array
    {
        $query = User::with('guru')->where('role', 'guru');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('username', 'like', "%{$s}%")
                    ->orWhereHas('guru', fn ($g) => $g->where('nama_guru', 'like', "%{$s}%"));
            });
        }

        $paginator = $query->orderBy('username')->paginate($perPage);
        $items = $paginator->getCollection()->map(fn (User $u) => [
            'id_user' => $u->id_user,
            'username' => $u->username,
            'role' => $u->role,
            'nama_guru' => $u->guru?->nama_guru,
            'nip' => $u->guru?->nip,
            'status_aktif' => $u->status_aktif,
        ])->values()->all();

        return [
            'summary' => [
                'total' => User::where('role', 'guru')->count(),
                'per_role' => [[
                    'role' => 'guru',
                    'total' => User::where('role', 'guru')->count(),
                ]],
            ],
            'items' => $items,
            'meta' => $this->metaFromPaginator($paginator),
        ];
    }

    public function laporanPpdb(array $filters, int $perPage): array
    {
        $query = Pendaftaran::with('user')->orderByDesc('updated_at');

        if (! empty($filters['status'])) {
            $query->where('ppdb_status', $filters['status']);
        }
        $this->applyDateRangeOnColumn($query, 'created_at', $filters);

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('nama_lengkap', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$s}%"));
            });
        }

        $paginator = $query->paginate($perPage);
        $items = $paginator->getCollection()->map(fn (Pendaftaran $p) => [
            'id_pendaftaran' => $p->id_pendaftaran,
            'nama_lengkap' => $p->nama_lengkap,
            'username' => $p->user?->username,
            'ppdb_status' => $p->ppdb_status,
            'created_at' => $p->created_at?->toDateString(),
            'updated_at' => $p->updated_at?->toDateString(),
        ])->values()->all();

        $statusCounts = Pendaftaran::query()
            ->when(! empty($filters['status']), fn ($q) => $q->where('ppdb_status', $filters['status']))
            ->selectRaw('ppdb_status, COUNT(*) as total')
            ->groupBy('ppdb_status')
            ->pluck('total', 'ppdb_status');

        return [
            'summary' => [
                'total' => $statusCounts->sum(),
                'per_status' => $statusCounts->map(fn ($total, $status) => [
                    'status' => $status,
                    'total' => (int) $total,
                ])->values()->all(),
            ],
            'items' => $items,
            'meta' => $this->metaFromPaginator($paginator),
        ];
    }

    public function laporanAbsensiSiswa(array $filters, int $perPage): array
    {
        $query = Absensi::with(['siswa.siswa', 'kelas', 'mapel']);
        $this->applyAbsensiSiswaFilters($query, $filters);

        $paginator = $query->orderByDesc('tanggal')->orderBy('jam_mulai')->paginate($perPage);
        $paginator->getCollection()->transform(
            fn ($item) => (new AbsensiResource($item))->resolve()
        );

        return [
            'summary' => $this->absensiSiswa->rekap($filters),
            'items' => $paginator->items(),
            'meta' => $this->metaFromPaginator($paginator),
        ];
    }

    public function laporanNilai(array $filters, int $perPage): array
    {
        $query = Nilai::with(['siswa.siswa', 'mapel']);
        $this->applyNilaiFilters($query, $filters);

        $summaryQuery = clone $query;
        $total = (clone $summaryQuery)->count();
        $validated = (clone $summaryQuery)->where('validated_by_wali', true)->count();
        $rataRata = (clone $summaryQuery)->avg('nilai_akhir');

        $predikatRows = (clone $summaryQuery)
            ->selectRaw('predikat, COUNT(*) as total')
            ->groupBy('predikat')
            ->pluck('total', 'predikat');

        $paginator = $query->orderBy('id_mapel')->orderBy('id_user_siswa')->paginate($perPage);
        $paginator->getCollection()->transform(
            fn ($item) => (new NilaiResource($item))->resolve()
        );

        return [
            'summary' => [
                'total' => $total,
                'validated' => $validated,
                'belum_validasi' => $total - $validated,
                'rata_rata' => $rataRata !== null ? round((float) $rataRata, 1) : null,
                'per_predikat' => collect(['A', 'B', 'C', 'D', 'E'])->map(fn ($g) => [
                    'predikat' => $g,
                    'total' => (int) ($predikatRows[$g] ?? 0),
                ])->values()->all(),
            ],
            'items' => $paginator->items(),
            'meta' => $this->metaFromPaginator($paginator),
        ];
    }

    public function laporanJadwal(array $filters, int $perPage): array
    {
        $query = JadwalPelajaran::with(['kelas', 'mapel', 'guru.guru']);
        $this->applyJadwalFilters($query, $filters);

        $paginator = $query->orderBy('hari')->orderBy('jam_mulai')->paginate($perPage);
        $paginator->getCollection()->transform(
            fn ($item) => (new JadwalResource($item))->resolve()
        );

        $summaryQuery = JadwalPelajaran::query();
        $this->applyJadwalFilters($summaryQuery, $filters);

        return [
            'summary' => [
                'total' => $summaryQuery->count(),
                'per_hari' => (clone $summaryQuery)
                    ->selectRaw('hari, COUNT(*) as total')
                    ->groupBy('hari')
                    ->pluck('total', 'hari')
                    ->map(fn ($total, $hari) => ['hari' => $hari, 'total' => (int) $total])
                    ->values()
                    ->all(),
            ],
            'items' => $paginator->items(),
            'meta' => $this->metaFromPaginator($paginator),
        ];
    }

    public function assertJenisAllowed(User $actor, string $jenis): void
    {
        $allowed = config('laporan.role_jenis.'.$actor->role, []);
        if ($actor->role === 'admin') {
            return;
        }
        if (! in_array($jenis, $allowed, true)) {
            throw new InvalidArgumentException('Anda tidak memiliki akses ke jenis laporan ini.');
        }
    }

    public function normalizeFilters(array $filters): array
    {
        if (! empty($filters['bulan'])) {
            $start = Carbon::createFromFormat('Y-m', $filters['bulan'])->startOfMonth();
            $filters['tanggal_dari'] = $start->toDateString();
            $filters['tanggal_sampai'] = $start->copy()->endOfMonth()->toDateString();
        }

        return $filters;
    }

    public function applyRoleScope(User $actor, string $jenis, array $filters): array
    {
        if ($actor->role === 'admin' || $actor->role === 'kepsek') {
            return $filters;
        }

        if ($actor->role === 'guru') {
            $filters['id_guru'] = $actor->id_user;
            if (in_array($jenis, ['absensi_siswa', 'nilai', 'jadwal'], true)) {
                $mapelIds = Mapel::where('id_guru', $actor->id_user)->pluck('id_mapel')->all();
                $kelasIds = JadwalPelajaran::where('id_guru', $actor->id_user)
                    ->when(! empty($filters['tahun_ajaran']), fn ($q) => $q->where('tahun_ajaran', $filters['tahun_ajaran']))
                    ->when(! empty($filters['semester']), fn ($q) => $q->where('semester', $filters['semester']))
                    ->distinct()
                    ->pluck('id_kelas')
                    ->all();

                if (! empty($filters['id_mapel']) && ! in_array((int) $filters['id_mapel'], $mapelIds, true)) {
                    throw new InvalidArgumentException('Mapel di luar kelas ajar Anda.');
                }

                if ($jenis === 'jadwal') {
                    $filters['id_guru'] = $actor->id_user;
                }

                if ($jenis === 'absensi_siswa' && empty($filters['id_mapel']) && count($mapelIds) === 1) {
                    $filters['id_mapel'] = $mapelIds[0];
                }
            }

            return $filters;
        }

        if ($actor->role === 'siswa') {
            $filters['id_user_siswa'] = $actor->id_user;
            if ($jenis === 'jadwal') {
                $siswa = Siswa::where('id_user', $actor->id_user)->first();
                $filters['id_kelas'] = $siswa?->id_kelas ?? -1;
            }
            if ($jenis === 'nilai') {
                $filters['validated_by_wali'] = true;
            }
        }

        return $filters;
    }

    public function applyAbsensiSiswaFilters($query, array $filters): void
    {
        if (! empty($filters['id_kelas'])) {
            $query->where('id_kelas', $filters['id_kelas']);
        }
        if (! empty($filters['id_mapel'])) {
            $query->where('id_mapel', $filters['id_mapel']);
        }
        if (! empty($filters['id_user_siswa'])) {
            $query->where('id_user_siswa', $filters['id_user_siswa']);
        }
        if (! empty($filters['tanggal_dari'])) {
            $query->whereDate('tanggal', '>=', $filters['tanggal_dari']);
        }
        if (! empty($filters['tanggal_sampai'])) {
            $query->whereDate('tanggal', '<=', $filters['tanggal_sampai']);
        }
        if (! empty($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }
        if (! empty($filters['tahun_ajaran'])) {
            $query->where('tahun_ajaran', $filters['tahun_ajaran']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['id_guru'])) {
            $mapelIds = Mapel::where('id_guru', $filters['id_guru'])->pluck('id_mapel');
            $query->whereIn('id_mapel', $mapelIds);
        }
    }

    public function applyNilaiFilters($query, array $filters): void
    {
        if (! empty($filters['id_kelas'])) {
            $query->whereIn('id_user_siswa', function ($q) use ($filters) {
                $q->select('id_user')->from('siswa')->where('id_kelas', $filters['id_kelas']);
            });
        }
        if (! empty($filters['id_mapel'])) {
            $query->where('id_mapel', $filters['id_mapel']);
        }
        if (! empty($filters['id_user_siswa'])) {
            $query->where('id_user_siswa', $filters['id_user_siswa']);
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
        if (! empty($filters['id_guru'])) {
            $mapelIds = Mapel::where('id_guru', $filters['id_guru'])->pluck('id_mapel');
            $query->whereIn('id_mapel', $mapelIds);
        }
    }

    public function applyJadwalFilters($query, array $filters): void
    {
        if (! empty($filters['id_kelas'])) {
            $query->where('id_kelas', $filters['id_kelas']);
        }
        if (! empty($filters['id_mapel'])) {
            $query->where('id_mapel', $filters['id_mapel']);
        }
        if (! empty($filters['id_guru'])) {
            $query->where('id_guru', $filters['id_guru']);
        }
        if (! empty($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }
        if (! empty($filters['tahun_ajaran'])) {
            $query->where('tahun_ajaran', $filters['tahun_ajaran']);
        }
    }

    public function applyDateRangeOnColumn($query, string $column, array $filters): void
    {
        if (! empty($filters['tanggal_dari'])) {
            $query->whereDate($column, '>=', $filters['tanggal_dari']);
        }
        if (! empty($filters['tanggal_sampai'])) {
            $query->whereDate($column, '<=', $filters['tanggal_sampai']);
        }
    }

    public function metaFromPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    public function publicFilters(array $filters): array
    {
        return collect($filters)->only([
            'tahun_ajaran', 'semester', 'id_kelas', 'id_mapel', 'tanggal_dari',
            'tanggal_sampai', 'bulan', 'role', 'status', 'search', 'id_user_guru', 'validated_by_wali',
        ])->filter(fn ($v) => $v !== null && $v !== '')->all();
    }
}
