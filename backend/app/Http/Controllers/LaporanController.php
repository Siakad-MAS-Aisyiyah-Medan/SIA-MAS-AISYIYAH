<?php

namespace App\Http\Controllers;

use App\Services\LaporanService;
use App\Utils\ApiResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class LaporanController extends Controller
{
    public function __construct(private LaporanService $service) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'jenis' => 'required|in:siswa,guru,ppdb,absensi_siswa,nilai,jadwal',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:1000',
            'id_kelas' => 'nullable|integer|exists:kelas,id_kelas',
            'id_mapel' => 'nullable|integer|exists:mata_pelajaran,id_mapel',
            'id_user_siswa' => 'nullable|integer|exists:users,id_user',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:20',
            'tanggal_dari' => 'nullable|date',
            'tanggal_sampai' => 'nullable|date|after_or_equal:tanggal_dari',
            'status' => 'nullable|string|max:30',
            'status_aktif' => 'nullable|boolean',
        ]);

        try {
            $data = $this->service->generate(
                $request->user(),
                $request->input('jenis'),
                $validated
            );

            return ApiResponse::success($data, 'Berhasil mengambil laporan');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
