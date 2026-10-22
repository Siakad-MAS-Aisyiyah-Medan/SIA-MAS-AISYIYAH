<?php

namespace App\Http\Controllers;

use App\Services\MapelService;
use App\Utils\ApiResponse;
use App\Utils\GuruPengampuUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MapelController extends Controller
{
    public function __construct(private MapelService $service) {}

    public function index(Request $request)
    {
        $paginator = $this->service->list(
            $request->query('search'),
            (int) $request->query('per_page', 15)
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data mata pelajaran');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:100', Rule::unique('mata_pelajaran')->where(fn ($q) => $q->where('tingkat', $request->tingkat))],
            'id_guru' => ['required', 'integer', 'exists:users,id_user', new GuruPengampuUser],
            'tingkat' => 'required|in:X,XI,XII',
            'kelompok_mapel' => 'nullable|string|max:100',
            'id_kelas' => 'nullable|array',
            'id_kelas.*' => 'integer|distinct|exists:kelas,id_kelas',
        ]);

        $mapel = DB::transaction(fn () => $this->service->create($validated));

        return ApiResponse::success($mapel, 'Mata pelajaran berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:100', Rule::unique('mata_pelajaran')->where(fn ($q) => $q->where('tingkat', $request->tingkat))->ignore($id, 'id_mapel')],
            'id_guru' => ['required', 'integer', 'exists:users,id_user', new GuruPengampuUser],
            'tingkat' => 'required|in:X,XI,XII',
            'kelompok_mapel' => 'nullable|string|max:100',
            'id_kelas' => 'nullable|array',
            'id_kelas.*' => 'integer|distinct|exists:kelas,id_kelas',
        ]);

        $mapel = DB::transaction(fn () => $this->service->processUpdate((int) $id, $validated));

        return ApiResponse::success($mapel, 'Mata pelajaran berhasil diperbarui');
    }

    public function destroy($id)
    {
        if (DB::table('nilai')->where('id_mapel', $id)->exists()
            || DB::table('absensi')->where('id_mapel', $id)->exists()
            || DB::table('skema_penilaian')->where('id_mapel', $id)->exists()) {
            return ApiResponse::error('Mata pelajaran memiliki riwayat akademik dan tidak dapat dihapus.', 422);
        }
        $this->service->delete((int) $id);

        return ApiResponse::success(null, 'Mata pelajaran berhasil dihapus');
    }
}
