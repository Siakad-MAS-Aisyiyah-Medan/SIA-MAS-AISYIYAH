<?php

namespace App\Http\Controllers;

use App\Services\MuridService;
use App\Utils\ApiResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MuridController extends Controller
{
    public function __construct(private MuridService $service) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer',
            'id_kelas' => 'nullable|integer|exists:kelas,id_kelas',
            'status_siswa' => 'nullable|in:aktif,lulus,tidak_aktif',
        ]);

        $paginator = $this->service->listMurid(
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 15),
            $validated['id_kelas'] ?? null,
            $validated['status_siswa'] ?? null
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data murid');
    }

    public function stats()
    {
        $stats = $this->service->getStats();

        return ApiResponse::success($stats, 'Berhasil mengambil statistik murid');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:8',
            'nama_siswa' => 'required|string',
            'nisn' => 'required|string|min:10|unique:siswa,nisn',
            'nis' => 'nullable|string|unique:siswa,nis',
            'jenis_kelamin' => 'required|in:L,P',
            'tempat_lahir' => 'required|string',
            'tanggal_lahir' => 'required|date|before_or_equal:today',
            'alamat' => 'required|string',
            'no_hp' => 'nullable|string',
            'tahun_masuk' => 'required|integer',
            'tahun_lulus' => 'nullable|integer',
            'status_siswa' => 'nullable|in:aktif,lulus,tidak_aktif',
            'id_kelas' => 'nullable|exists:kelas,id_kelas',
            'status_aktif' => 'nullable|boolean',
        ]);

        try {
            $user = $this->service->createMurid($validated);

            return ApiResponse::success($user, 'Murid berhasil ditambahkan', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'username' => 'nullable|string|unique:users,username,'.$id.',id_user',
                'email' => 'nullable|email|unique:users,email,'.$id.',id_user',
                'password' => 'nullable|string|min:8',
                'role' => 'nullable|in:siswa,calon_siswa',
                'nama_siswa' => 'required|string',
                'nisn' => 'nullable|string|min:10|unique:siswa,nisn,'.$id.',id_user',
                'nis' => 'nullable|string|unique:siswa,nis,'.$id.',id_user',
                'jenis_kelamin' => 'required|in:L,P',
                'tempat_lahir' => 'sometimes|required|string',
                'tanggal_lahir' => 'sometimes|required|date|before_or_equal:today',
                'alamat' => 'sometimes|required|string',
                'no_hp' => 'nullable|string',
                'tahun_masuk' => 'nullable|integer',
                'tahun_lulus' => 'nullable|integer',
                'status_siswa' => 'nullable|in:aktif,lulus,tidak_aktif',
                'id_kelas' => 'nullable|exists:kelas,id_kelas',
                'status_aktif' => 'nullable|boolean',
            ]);
            $user = $this->service->updateMurid((int) $id, $validated);

            return ApiResponse::success($user, 'Status Murid diperbarui');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function enroll(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'id_kelas' => 'nullable|exists:kelas,id_kelas',
            ]);
            $result = $this->service->enrollMurid((int) $id, $validated['id_kelas'] ?? null);

            return ApiResponse::success($result, 'Calon siswa berhasil dipromosikan menjadi siswa');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function destroy($id)
    {
        try {
            $this->service->deleteMurid((int) $id);

            return ApiResponse::success(null, 'Data murid berhasil dihapus permanen');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
