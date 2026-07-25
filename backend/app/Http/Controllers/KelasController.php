<?php

namespace App\Http\Controllers;

use App\Http\Resources\KelasResource;
use App\Models\Kelas;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use App\Utils\SearchInput;
use App\Utils\WaliKelasUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    public function index(Request $request)
    {
        $paginator = $this->list(
            $request->only(['search', 'tingkat', 'jurusan']),
            (int) $request->query('per_page', 15)
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data kelas');
    }

    public function stats()
    {
        $stats = $this->getStats();

        return ApiResponse::success($stats, 'Berhasil mengambil statistik kelas');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:50',
            'tingkat' => 'required|in:X,XI,XII',
            'jurusan' => 'required|in:IPA,IPS',
            'id_wali_kelas' => ['nullable', 'integer', 'exists:users,id_user', new WaliKelasUser],
            'tahun_ajaran' => 'nullable|string|max:20',
            'status' => 'nullable|in:aktif,nonaktif,Aktif,Nonaktif',
            'kapasitas_maksimal' => 'required|integer|min:1|max:100',
            'ruangan' => 'nullable|string|max:100',
        ]);
        $validated['status'] = strtolower($validated['status'] ?? 'aktif');

        $kelas = $this->create($validated);

        return ApiResponse::success($kelas, 'Kelas berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:50',
            'tingkat' => 'required|in:X,XI,XII',
            'jurusan' => 'required|in:IPA,IPS',
            'id_wali_kelas' => ['nullable', 'integer', 'exists:users,id_user', new WaliKelasUser],
            'tahun_ajaran' => 'nullable|string|max:20',
            'status' => 'nullable|in:aktif,nonaktif,Aktif,Nonaktif',
            'kapasitas_maksimal' => 'required|integer|min:1|max:100',
            'ruangan' => 'nullable|string|max:100',
        ]);
        if (array_key_exists('status', $validated)) {
            $validated['status'] = strtolower($validated['status'] ?? 'aktif');
        }

        $kelas = $this->processUpdate((int) $id, $validated);

        return ApiResponse::success($kelas, 'Kelas berhasil diperbarui');
    }

    public function destroy($id)
    {
        $kelas = Kelas::withCount('siswa')->findOrFail((int) $id);
        if ($kelas->siswa_count > 0) {
            return ApiResponse::error(
                'Kelas masih terhubung dengan data murid atau alumni. Ubah status kelas menjadi nonaktif agar riwayat kelas tetap tersimpan.',
                422
            );
        }

        $this->delete((int) $id);

        return ApiResponse::success(null, 'Kelas berhasil dihapus');
    }

    // --- Inlined from KelasService ---

    use AuditsAdminActions;

    private function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Kelas::with(['waliKelas.guru'])
            ->withCount([
                'jadwal',
                'siswa as siswa_count' => fn ($q) => $q->aktif(),
            ]);

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

    private function getStats(): array
    {
        return [
            'total_kelas' => Kelas::count(),
            'ipa' => Kelas::where('jurusan', 'IPA')->count(),
            'ips' => Kelas::where('jurusan', 'IPS')->count(),
            'belum_ada_wali' => Kelas::whereNull('id_wali_kelas')->count(),
        ];
    }

    private function create(array $data): array
    {
        $kelas = Kelas::create($data);
        $this->auditAdmin('kelas.create', $kelas, ['nama_kelas' => $kelas->nama_kelas]);

        return (new KelasResource($kelas->load('waliKelas.guru')))->resolve();
    }

    private function processUpdate(int $id, array $data): array
    {
        $kelas = Kelas::findOrFail($id);
        $kelas->update($data);
        $this->auditAdmin('kelas.update', $kelas, ['nama_kelas' => $kelas->nama_kelas]);

        return (new KelasResource($kelas->fresh(['waliKelas.guru'])))->resolve();
    }

    private function delete(int $id): void
    {
        $kelas = Kelas::findOrFail($id);
        $this->auditAdmin('kelas.delete', $kelas, ['nama_kelas' => $kelas->nama_kelas]);
        $kelas->delete();
    }
}
