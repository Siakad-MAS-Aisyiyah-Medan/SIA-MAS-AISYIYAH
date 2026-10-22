<?php

namespace App\Http\Controllers;

use App\Services\GuruService;
use App\Utils\ApiResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GuruController extends Controller
{
    public function __construct(private GuruService $service) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer',
            'role' => 'nullable|in:guru',
        ]);

        $paginator = $this->service->listGuru(
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 15),
            $validated['role'] ?? null
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data guru');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|min:8',
            'nama_guru' => 'required|string|max:255',
            'nip' => 'required|string|min:10|max:50|unique:guru,nip',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'required|string|max:50',
            'alamat' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'role' => 'required|in:guru',
            'status' => 'required|in:aktif,nonaktif',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $guru = $this->service->createGuru($validated);

            return ApiResponse::success($guru, 'Data Guru berhasil ditambahkan', 201);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal menyimpan data guru', 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'username' => 'required|unique:users,username,'.$id.',id_user',
            'email' => 'nullable|email|unique:users,email,'.$id.',id_user',
            'password' => 'nullable|min:8',
            'nama_guru' => 'required|string|max:255',
            'nip' => 'required|string|min:10|max:50|unique:guru,nip,'.$id.',id_user',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'required|string|max:50',
            'alamat' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'role' => 'required|in:guru',
            'status' => 'required|in:aktif,nonaktif',
            'status_aktif' => 'nullable|boolean',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $guru = $this->service->updateGuru((int) $id, $validated);

            return ApiResponse::success($guru, 'Data Guru berhasil diperbarui');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal memperbarui data guru', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->service->deleteGuru((int) $id);

            return ApiResponse::success(null, 'Data Guru berhasil dihapus');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal menghapus data guru', 500);
        }
    }
}
