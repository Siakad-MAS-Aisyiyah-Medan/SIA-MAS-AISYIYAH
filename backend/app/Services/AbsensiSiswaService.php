<?php

namespace App\Services;

use App\Http\Resources\AbsensiResource;
use App\Models\Absensi;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AbsensiSiswaService
{
    public function getFormData(int $guruId, array $params): array
    {
        $this->assertGuruCanRecord($guruId, (int) $params['id_mapel']);

        $siswaList = Siswa::with('user')
            ->aktif()
            ->where('id_kelas', $params['id_kelas'])
            ->orderBy('nama_siswa')
            ->get();

        $existing = Absensi::query()
            ->where('id_kelas', $params['id_kelas'])
            ->where('id_mapel', $params['id_mapel'])
            ->whereDate('tanggal', $params['tanggal'])
            ->where('jam_mulai', $params['jam_mulai'])
            ->get()
            ->keyBy('id_user_siswa');

        $rows = $siswaList->map(function (Siswa $siswa) use ($existing) {
            $absen = $existing->get($siswa->id_user);

            return [
                'id_user_siswa' => $siswa->id_user,
                'nama_siswa' => $siswa->nama_siswa,
                'nisn' => $siswa->nisn ?: $siswa->user?->username,
                'jenis_kelamin' => $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : ($siswa->jenis_kelamin === 'P' ? 'Perempuan' : null),
                'status' => $absen?->status,
                'keterangan' => $absen?->keterangan,
                'id_absensi' => $absen?->id_absensi,
            ];
        });

        return [
            'meta' => [
                'id_kelas' => (int) $params['id_kelas'],
                'id_mapel' => (int) $params['id_mapel'],
                'tanggal' => $params['tanggal'],
                'jam_mulai' => $params['jam_mulai'],
                'jam_selesai' => $params['jam_selesai'],
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

        $this->assertGuruCanRecord($guruId, (int) $meta['id_mapel']);

        return DB::transaction(function () use ($guruId, $meta, $items) {
            $saved = [];

            foreach ($items as $row) {
                $status = $row['status'] ?? Absensi::STATUS_HADIR;
                if (! array_key_exists($status, Absensi::statusLabels())) {
                    throw new InvalidArgumentException('Status absensi tidak valid.');
                }

                $absensi = Absensi::updateOrCreate(
                    [
                        'id_user_siswa' => $row['id_user_siswa'],
                        'id_mapel' => $meta['id_mapel'],
                        'tanggal' => $meta['tanggal'],
                        'jam_mulai' => $meta['jam_mulai'],
                    ],
                    [
                        'id_kelas' => $meta['id_kelas'],
                        'id_jadwal' => $meta['id_jadwal'] ?? null,
                        'jam_selesai' => $meta['jam_selesai'],
                        'status' => $status,
                        'id_guru_pencatat' => $guruId,
                        'tahun_ajaran' => $meta['tahun_ajaran'],
                        'semester' => $meta['semester'],
                        'keterangan' => $row['keterangan'] ?? null,
                    ]
                );

                $saved[] = (new AbsensiResource($absensi->load(['siswa.siswa', 'kelas', 'mapel'])))->resolve();
            }

            return $saved;
        });
    }

    public function getHistoryMeetings(int $guruId, array $params): Collection
    {
        $this->assertGuruCanRecord($guruId, (int) $params['id_mapel']);

        return Absensi::query()
            ->select('tanggal', 'jam_mulai', 'jam_selesai', 'tahun_ajaran', 'semester')
            ->selectRaw("SUM(CASE WHEN status = 'H' THEN 1 ELSE 0 END) as count_hadir")
            ->selectRaw("SUM(CASE WHEN status = 'S' THEN 1 ELSE 0 END) as count_sakit")
            ->selectRaw("SUM(CASE WHEN status = 'I' THEN 1 ELSE 0 END) as count_izin")
            ->selectRaw("SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as count_alpa")
            ->selectRaw("SUM(CASE WHEN status = 'T' THEN 1 ELSE 0 END) as count_terlambat")
            ->where('id_kelas', $params['id_kelas'])
            ->where('id_mapel', $params['id_mapel'])
            ->groupBy('tanggal', 'jam_mulai', 'jam_selesai', 'tahun_ajaran', 'semester')
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_mulai')
            ->get()
            ->map(function ($row) {
                return [
                    'tanggal' => Carbon::parse($row->tanggal)->format('Y-m-d'),
                    'jam_mulai' => $row->jam_mulai,
                    'jam_selesai' => $row->jam_selesai,
                    'tahun_ajaran' => $row->tahun_ajaran,
                    'semester' => $row->semester,
                    'count_hadir' => (int) $row->count_hadir,
                    'count_sakit' => (int) $row->count_sakit,
                    'count_izin' => (int) $row->count_izin,
                    'count_alpa' => (int) $row->count_alpa,
                    'count_terlambat' => (int) $row->count_terlambat,
                ];
            });
    }

    public function listForSiswa(int $userId, array $filters = []): Collection
    {
        $query = Absensi::with(['kelas', 'mapel', 'guruPencatat.guru'])
            ->where('id_user_siswa', $userId);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('tanggal')->orderBy('jam_mulai')->get()->map(
            fn ($item) => (new AbsensiResource($item))->resolve()
        );
    }

    public function rekap(array $filters = [], ?int $guruId = null): array
    {
        $query = Absensi::query();
        if ($guruId !== null) {
            $this->applyGuruRekapScope($query, $guruId, $filters);
        }
        $this->applyFilters($query, $filters);

        $rows = $query->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $total = $rows->sum();
        $breakdown = [];
        foreach (Absensi::statusLabels() as $code => $label) {
            $breakdown[] = [
                'status' => $code,
                'label' => $label,
                'total' => (int) ($rows[$code] ?? 0),
            ];
        }

        return [
            'total' => $total,
            'breakdown' => $breakdown,
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['bulan'])) {
            $start = Carbon::createFromFormat('Y-m', $filters['bulan'])->startOfMonth();
            $filters['tanggal_dari'] = $start->toDateString();
            $filters['tanggal_sampai'] = $start->copy()->endOfMonth()->toDateString();
        }

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
    }

    private function assertGuruCanRecord(int $guruId, int $mapelId): void
    {
        $user = User::findOrFail($guruId);
        if ($user->role !== 'guru') {
            throw new InvalidArgumentException('Hanya guru yang dapat mencatat absensi siswa.');
        }

        $mapel = Mapel::findOrFail($mapelId);
        if ((int) $mapel->id_guru !== $guruId) {
            throw new InvalidArgumentException('Anda bukan pengampu mata pelajaran ini.');
        }
    }

    private function applyGuruRekapScope($query, int $guruId, array $filters): void
    {
        $mapelIds = Mapel::where('id_guru', $guruId)->pluck('id_mapel');

        if ($mapelIds->isEmpty()) {
            throw new InvalidArgumentException('Anda belum ditugaskan mengampu mata pelajaran.');
        }

        if (! empty($filters['id_mapel'])) {
            $mapelId = (int) $filters['id_mapel'];
            if (! $mapelIds->contains($mapelId)) {
                throw new InvalidArgumentException('Anda bukan pengampu mata pelajaran ini.');
            }
            $query->where('id_mapel', $mapelId);
        } else {
            $query->whereIn('id_mapel', $mapelIds);
        }

        if (! empty($filters['id_kelas'])) {
            $kelasId = (int) $filters['id_kelas'];
            $teachesKelas = Mapel::where('id_guru', $guruId)
                ->whereHas('kelas', function ($q) use ($kelasId) {
                    $q->where('kelas_mapel.id_kelas', $kelasId);
                })
                ->exists();

            if (! $teachesKelas) {
                throw new InvalidArgumentException('Anda tidak mengampu kelas ini.');
            }
        }
    }

    public function deleteMeeting(int $guruId, array $params)
    {
        $this->assertGuruCanRecord($guruId, (int) $params['id_mapel']);

        $jamMulai = $params['jam_mulai'].':00';
        $jamSelesai = $params['jam_selesai'].':00';

        Absensi::where('id_kelas', $params['id_kelas'])
            ->where('id_mapel', $params['id_mapel'])
            ->where('tanggal', $params['tanggal'])
            ->where('jam_mulai', $jamMulai)
            ->where('jam_selesai', $jamSelesai)
            ->delete();
    }
}
