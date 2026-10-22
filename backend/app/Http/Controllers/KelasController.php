<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Services\KelasService;
use App\Utils\ApiResponse;
use App\Utils\WaliKelasUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelasController extends Controller
{
    public function __construct(private KelasService $service) {}

    public function index(Request $request)
    {
        $paginator = $this->service->list(
            $request->only(['search', 'tingkat', 'jurusan']),
            (int) $request->query('per_page', 15)
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data kelas');
    }

    public function stats()
    {
        $stats = $this->service->getStats();

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

        $kelas = $this->service->create($validated);

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

        $kelas = $this->service->processUpdate((int) $id, $validated);

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
        if (DB::table('absensi')->where('id_kelas', $id)->exists()
            || DB::table('skema_penilaian')->where('id_kelas', $id)->exists()
            || $kelas->jadwal()->exists()) {
            return ApiResponse::error('Kelas memiliki riwayat akademik dan tidak dapat dihapus. Nonaktifkan kelas untuk menyimpan arsip.', 422);
        }

        $this->service->delete((int) $id);

        return ApiResponse::success(null, 'Kelas berhasil dihapus');
    }
}
