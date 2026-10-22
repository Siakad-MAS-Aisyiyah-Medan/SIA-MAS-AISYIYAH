<?php

namespace App\Services;

use App\Http\Resources\JadwalResource;
use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use App\Utils\AuditsAdminActions;
use App\Utils\SearchInput;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class JadwalService
{
    use AuditsAdminActions;

    public function list(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = JadwalPelajaran::with(['kelas', 'mapel', 'guru.guru']);

        if ($term = SearchInput::escape($search)) {
            $query->where(function ($q) use ($term) {
                $q->whereHas('kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$term}%"))
                    ->orWhereHas('mapel', fn ($m) => $m->where('nama_mapel', 'like', "%{$term}%"));
            });
        }

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(
            fn ($item) => (new JadwalResource($item))->resolve()
        );

        return $paginator;
    }

    public function listForGuru(int $userId, ?string $tahunAjaran = null, ?string $semester = null): Collection
    {
        $query = JadwalPelajaran::with(['kelas', 'mapel', 'guru.guru'])
            ->where('id_guru', $userId);

        if ($tahunAjaran) {
            $query->where('tahun_ajaran', $tahunAjaran);
        }
        if ($semester) {
            $query->where('semester', $semester);
        }

        return $query->get()->map(
            fn ($item) => (new JadwalResource($item))->resolve()
        );
    }

    public function listForSiswa(int $userId, ?string $tahunAjaran = null, ?string $semester = null): Collection
    {
        $siswa = Siswa::where('id_user', $userId)->first();

        if (! $siswa?->id_kelas) {
            throw new InvalidArgumentException('Anda belum terdaftar di kelas. Hubungi admin sekolah.');
        }

        $query = JadwalPelajaran::with(['kelas', 'mapel', 'guru.guru'])
            ->where('id_kelas', $siswa->id_kelas);

        if ($tahunAjaran) {
            $query->where('tahun_ajaran', $tahunAjaran);
        }
        if ($semester) {
            $query->where('semester', $semester);
        }

        return $query->get()->map(
            fn ($item) => (new JadwalResource($item))->resolve()
        );
    }

    public function create(array $data): array
    {
        $jadwal = JadwalPelajaran::create($data);
        $this->auditAdmin('jadwal.create', $jadwal, ['id_jadwal' => $jadwal->id_jadwal]);

        return (new JadwalResource($jadwal->load(['kelas', 'mapel', 'guru.guru'])))->resolve();
    }

    public function processUpdate(int $id, array $data): array
    {
        $jadwal = JadwalPelajaran::findOrFail($id);
        $jadwal->update($data);
        $this->auditAdmin('jadwal.update', $jadwal, ['id_jadwal' => $jadwal->id_jadwal]);

        return (new JadwalResource($jadwal->fresh(['kelas', 'mapel', 'guru.guru'])))->resolve();
    }

    public function delete(int $id): void
    {
        $jadwal = JadwalPelajaran::findOrFail($id);
        $this->auditAdmin('jadwal.delete', $jadwal, ['id_jadwal' => $jadwal->id_jadwal]);
        $jadwal->delete();
    }
}
