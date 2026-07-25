<?php

namespace App\Http\Controllers;

use App\Http\Resources\JadwalResource;
use App\Models\JadwalPelajaran;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use App\Utils\GuruPengampuUser;
use App\Utils\SearchInput;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class JadwalController extends Controller
{
    public function __construct() {}

    public function adminIndex(Request $request)
    {
        $paginator = $this->list(
            $request->query('search'),
            (int) $request->query('per_page', 15)
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data jadwal');
    }

    public function adminStore(Request $request)
    {
        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'id_guru' => ['required', 'integer', 'exists:users,id_user', new GuruPengampuUser],
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|in:Ganjil,Genap',
        ]);

        try {
            $jadwal = $this->create($validated);

            return ApiResponse::success($jadwal, 'Jadwal berhasil ditambahkan', 201);

        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal menyimpan jadwal', 500);
        }
    }

    public function adminUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'id_guru' => ['required', 'integer', 'exists:users,id_user', new GuruPengampuUser],
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|in:Ganjil,Genap',
        ]);

        try {
            $jadwal = $this->processUpdate((int) $id, $validated);

            return ApiResponse::success($jadwal, 'Jadwal berhasil diperbarui');

        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal memperbarui jadwal', 500);
        }
    }

    public function adminDestroy($id)
    {
        try {
            $this->delete((int) $id);

            return ApiResponse::success(null, 'Jadwal berhasil dihapus');
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal menghapus jadwal', 500);
        }
    }

    public function guruIndex(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'guru') {
            return ApiResponse::error('Akses jadwal mengajar hanya untuk guru.', 403);
        }

        $tahunAjaran = TahunAjaran::where('status', 'aktif')->first()?->tahun_ajaran ?? '2025/2026';
        $semester = (int) date('n') >= 7 || (int) date('n') <= 12 ? 'Ganjil' : 'Genap';

        $items = Mapel::with(['kelas'])
            ->where('id_guru', $user->id_user)
            ->get()
            ->map(function ($mapel) use ($tahunAjaran, $semester) {
                return $mapel->kelas->map(function ($kelas) use ($mapel, $tahunAjaran, $semester) {
                    return [
                        'id_mapel' => $mapel->id_mapel,
                        'id_kelas' => $kelas->id_kelas,
                        'tahun_ajaran' => $tahunAjaran,
                        'semester' => $semester,
                        'mapel' => [
                            'id_mapel' => $mapel->id_mapel,
                            'nama_mapel' => $mapel->nama_mapel,
                            'tingkat' => $mapel->tingkat,
                        ],
                        'kelas' => [
                            'id_kelas' => $kelas->id_kelas,
                            'nama_kelas' => $kelas->nama_kelas,
                            'tingkat' => $kelas->tingkat,
                        ],
                    ];
                });
            })
            ->flatten(1)
            ->values()
            ->all();

        return ApiResponse::success($items, 'Berhasil mengambil daftar penugasan kelas');
    }

    public function guruMuridDiajar(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'guru') {
            return ApiResponse::error('Akses data murid diajar hanya untuk guru.', 403);
        }

        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|in:Ganjil,Genap',
        ]);

        $mapelExists = Mapel::where('id_mapel', $validated['id_mapel'])
            ->where('id_guru', $user->id_user)
            ->whereHas('kelas', function ($q) use ($validated) {
                $q->where('kelas_mapel.id_kelas', $validated['id_kelas']);
            })
            ->exists();

        if (! $mapelExists) {
            return ApiResponse::error('Anda tidak mengampu kelas atau mata pelajaran ini.', 422);
        }

        $murid = Siswa::with('user')
            ->aktif()
            ->where('id_kelas', $validated['id_kelas'])
            ->orderBy('nama_siswa')
            ->get()
            ->map(function (Siswa $siswa) {
                return [
                    'id_user_siswa' => $siswa->id_user,
                    'nama_siswa' => $siswa->nama_siswa,
                    'nisn' => $siswa->nisn ?: $siswa->user?->username,
                    'jenis_kelamin' => $siswa->jenis_kelamin === 'L'
                        ? 'Laki-laki'
                        : ($siswa->jenis_kelamin === 'P' ? 'Perempuan' : null),
                ];
            })
            ->values();

        return ApiResponse::success([
            'meta' => [
                'id_kelas' => (int) $validated['id_kelas'],
                'id_mapel' => (int) $validated['id_mapel'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
            ],
            'siswa' => $murid,
        ], 'Berhasil mengambil data murid yang diajar');
    }

    public function siswaIndex(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'siswa') {
            return ApiResponse::error('Akses jadwal pelajaran hanya untuk siswa.', 403);
        }

        try {
            $items = $this->listForSiswa(
                (int) $user->id_user,
                $request->query('tahun_ajaran'),
                $request->query('semester')
            );

            return ApiResponse::success($items, 'Berhasil mengambil jadwal pelajaran');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    // --- Inlined from JadwalService ---

    use AuditsAdminActions;

    private function list(?string $search = null, int $perPage = 15): LengthAwarePaginator
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

    private function listForGuru(int $userId, ?string $tahunAjaran = null, ?string $semester = null): Collection
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

    private function listForSiswa(int $userId, ?string $tahunAjaran = null, ?string $semester = null): Collection
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

    private function create(array $data): array
    {
        $jadwal = JadwalPelajaran::create($data);
        $this->auditAdmin('jadwal.create', $jadwal, ['id_jadwal' => $jadwal->id_jadwal]);

        return (new JadwalResource($jadwal->load(['kelas', 'mapel', 'guru.guru'])))->resolve();
    }

    private function processUpdate(int $id, array $data): array
    {
        $jadwal = JadwalPelajaran::findOrFail($id);
        $jadwal->update($data);
        $this->auditAdmin('jadwal.update', $jadwal, ['id_jadwal' => $jadwal->id_jadwal]);

        return (new JadwalResource($jadwal->fresh(['kelas', 'mapel', 'guru.guru'])))->resolve();
    }

    private function delete(int $id): void
    {
        $jadwal = JadwalPelajaran::findOrFail($id);
        $this->auditAdmin('jadwal.delete', $jadwal, ['id_jadwal' => $jadwal->id_jadwal]);
        $jadwal->delete();
    }
}
