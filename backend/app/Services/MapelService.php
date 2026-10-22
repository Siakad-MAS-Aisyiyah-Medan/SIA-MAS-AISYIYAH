<?php

namespace App\Services;

use App\Http\Resources\MapelResource;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Utils\AuditsAdminActions;
use App\Utils\SearchInput;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MapelService
{
    use AuditsAdminActions;

    public function list(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Mapel::with(['guru.guru', 'kelas']);
        $user = auth()->user();
        if ($user?->role === 'guru') {
            $query->where('id_guru', $user->id_user);
        }

        if ($user && $user->role === 'siswa') {
            $siswa = Siswa::where('id_user', $user->id_user)->first();
            if ($siswa && $siswa->id_kelas) {
                $query->whereHas('kelas', function ($q) use ($siswa) {
                    $q->where('kelas_mapel.id_kelas', $siswa->id_kelas);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($term = SearchInput::escape($search)) {
            $query->where(function ($q) use ($term) {
                $q->where('nama_mapel', 'like', "%{$term}%")
                    ->orWhereHas('guru.guru', fn ($g) => $g->where('nama_guru', 'like', "%{$term}%"));
            });
        }

        $paginator = $query->orderBy('nama_mapel')->paginate($perPage);
        $paginator->getCollection()->transform(
            fn ($item) => (new MapelResource($item))->resolve()
        );

        return $paginator;
    }

    public function create(array $data): array
    {
        $idKelas = $data['id_kelas'] ?? [];
        unset($data['id_kelas']);

        $mapel = Mapel::create($data);
        $mapel->kelas()->sync($idKelas);

        $this->auditAdmin('mapel.create', $mapel, ['nama_mapel' => $mapel->nama_mapel]);

        return (new MapelResource($mapel->load(['guru.guru', 'kelas'])))->resolve();
    }

    public function processUpdate(int $id, array $data): array
    {
        $idKelas = $data['id_kelas'] ?? [];
        unset($data['id_kelas']);

        $mapel = Mapel::findOrFail($id);
        $mapel->update($data);
        $mapel->kelas()->sync($idKelas);

        $this->auditAdmin('mapel.update', $mapel, ['nama_mapel' => $mapel->nama_mapel]);

        return (new MapelResource($mapel->fresh(['guru.guru', 'kelas'])))->resolve();
    }

    public function delete(int $id): void
    {
        $mapel = Mapel::findOrFail($id);
        $this->auditAdmin('mapel.delete', $mapel, ['nama_mapel' => $mapel->nama_mapel]);
        $mapel->delete();
    }
}
