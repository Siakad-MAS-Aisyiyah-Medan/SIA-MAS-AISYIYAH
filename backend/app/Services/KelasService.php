<?php

namespace App\Services;

use App\Http\Resources\KelasResource;
use App\Models\Kelas;
use App\Utils\AuditsAdminActions;
use App\Utils\SearchInput;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class KelasService
{
    use AuditsAdminActions;

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Kelas::with(['waliKelas.guru'])
            ->withCount([
                'jadwal',
                'siswa as siswa_count' => fn ($q) => $q->aktif(),
            ]);
        $user = auth()->user();
        if ($user?->role === 'siswa') {
            $query->where('id_kelas', $user->siswa?->id_kelas ?? 0);
        } elseif ($user?->role === 'guru') {
            $query->where(function ($q) use ($user) {
                $q->where('id_wali_kelas', $user->id_user)
                    ->orWhereHas('mapel', fn ($m) => $m->where('id_guru', $user->id_user));
            });
        }

        if (! empty($filters['search'])) {
            $term = SearchInput::escape($filters['search']);
            $query->where('nama_kelas', 'like', "%{$term}%");
        }

        if (! empty($filters['tingkat']) && $filters['tingkat'] !== 'Semua') {
            $query->where('tingkat', $filters['tingkat']);
        }

        if (! empty($filters['jurusan']) && $filters['jurusan'] !== 'Semua') {
            $query->where('jurusan', $filters['jurusan']);
        }

        $paginator = $query->orderBy('nama_kelas')->paginate($perPage);
        $paginator->getCollection()->transform(
            fn ($item) => (new KelasResource($item))->resolve()
        );

        return $paginator;
    }

    public function getStats(): array
    {
        return [
            'total_kelas' => Kelas::count(),
            'ipa' => Kelas::where('jurusan', 'IPA')->count(),
            'ips' => Kelas::where('jurusan', 'IPS')->count(),
            'belum_ada_wali' => Kelas::whereNull('id_wali_kelas')->count(),
        ];
    }

    public function create(array $data): array
    {
        $kelas = Kelas::create($data);
        $this->auditAdmin('kelas.create', $kelas, ['nama_kelas' => $kelas->nama_kelas]);

        return (new KelasResource($kelas->load('waliKelas.guru')))->resolve();
    }

    public function processUpdate(int $id, array $data): array
    {
        $kelas = Kelas::findOrFail($id);
        if ($kelas->siswa()->aktif()->count() > $data['kapasitas_maksimal']) {
            throw ValidationException::withMessages([
                'kapasitas_maksimal' => 'Kapasitas tidak boleh kurang dari jumlah murid aktif.',
            ]);
        }
        $kelas->update($data);
        $this->auditAdmin('kelas.update', $kelas, ['nama_kelas' => $kelas->nama_kelas]);

        return (new KelasResource($kelas->fresh(['waliKelas.guru'])))->resolve();
    }

    public function delete(int $id): void
    {
        $kelas = Kelas::findOrFail($id);
        $this->auditAdmin('kelas.delete', $kelas, ['nama_kelas' => $kelas->nama_kelas]);
        $kelas->delete();
    }
}
