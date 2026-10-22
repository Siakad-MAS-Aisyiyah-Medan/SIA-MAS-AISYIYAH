<?php

namespace App\Http\Controllers;

use App\Services\AbsensiSiswaService;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AbsensiController extends Controller
{
    use AuditsAdminActions;

    protected AbsensiSiswaService $absensiService;

    public function __construct(AbsensiSiswaService $absensiService)
    {
        $this->absensiService = $absensiService;
    }

    public function guruFormData(Request $request)
    {
        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|in:Ganjil,Genap',
            'id_jadwal' => 'nullable|exists:jadwal_pelajaran,id_jadwal',
        ]);

        try {
            $data = $this->absensiService->getFormData(
                (int) auth()->id(),
                $validated
            );

            return ApiResponse::success($data, 'Berhasil mengambil daftar siswa');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function guruBulkStore(Request $request)
    {
        $validated = $request->validate([
            'meta' => 'required|array',
            'meta.id_kelas' => 'required|exists:kelas,id_kelas',
            'meta.id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'meta.tanggal' => 'required|date',
            'meta.jam_mulai' => 'required|date_format:H:i',
            'meta.jam_selesai' => 'required|date_format:H:i|after:meta.jam_mulai',
            'meta.tahun_ajaran' => 'required|string|max:20',
            'meta.semester' => 'required|in:Ganjil,Genap',
            'meta.id_jadwal' => 'nullable|exists:jadwal_pelajaran,id_jadwal',
            'items' => 'required|array|min:1',
            'items.*.id_user_siswa' => 'required|integer|distinct|exists:users,id_user',
            'items.*.status' => ['required', Rule::in(['H', 'A', 'I', 'S', 'T'])],
            'items.*.keterangan' => 'nullable|string|max:255',
        ]);

        try {
            $saved = $this->absensiService->bulkSave(
                (int) auth()->id(),
                $validated
            );

            $this->auditAdmin('guru.absensi.bulk_store', null, [
                'id_kelas' => $validated['meta']['id_kelas'],
                'id_mapel' => $validated['meta']['id_mapel'],
                'tanggal' => $validated['meta']['tanggal'],
            ]);

            return ApiResponse::success($saved, 'Absensi siswa berhasil disimpan');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal menyimpan absensi siswa', 500);
        }
    }

    public function guruRekapSiswa(Request $request)
    {
        $validated = $request->validate([
            'id_kelas' => 'nullable|exists:kelas,id_kelas',
            'id_mapel' => 'nullable|exists:mata_pelajaran,id_mapel',
            'tanggal_dari' => 'nullable|date',
            'tanggal_sampai' => 'nullable|date|after_or_equal:tanggal_dari',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:20',
            'bulan' => 'nullable|date_format:Y-m',
        ]);

        try {
            $rekap = $this->absensiService->rekap(
                $validated,
                (int) auth()->id()
            );

            return ApiResponse::success($rekap, 'Berhasil mengambil rekap absensi siswa');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function guruHistoryAbsensi(Request $request)
    {
        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
        ]);

        try {
            $history = $this->absensiService->getHistoryMeetings(
                (int) auth()->id(),
                $validated
            );

            return ApiResponse::success($history, 'Berhasil mengambil riwayat absensi pertemuan');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function guruDeleteMeeting(Request $request)
    {
        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
        ]);

        try {
            $this->absensiService->deleteMeeting((int) auth()->id(), $validated);

            $this->auditAdmin('guru.absensi.delete_meeting', null, [
                'id_kelas' => $validated['id_kelas'],
                'id_mapel' => $validated['id_mapel'],
                'tanggal' => $validated['tanggal'],
            ]);

            return ApiResponse::success(null, 'Data absensi pertemuan berhasil dihapus');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function siswaIndex(Request $request)
    {
        $validated = $request->validate([
            'tahun_ajaran' => 'nullable|string|max:20',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tanggal_dari' => 'nullable|date',
            'tanggal_sampai' => 'nullable|date|after_or_equal:tanggal_dari',
            'id_mapel' => 'nullable|exists:mata_pelajaran,id_mapel',
        ]);

        $items = $this->absensiService->listForSiswa(
            (int) auth()->id(),
            $validated
        );

        return ApiResponse::success($items, 'Berhasil mengambil riwayat absensi');
    }

    public function kepsekSiswa(Request $request)
    {
        $rekap = $this->absensiService->rekap($request->only([
            'id_kelas', 'id_mapel', 'tanggal_dari', 'tanggal_sampai', 'semester', 'tahun_ajaran', 'bulan',
        ]));

        return ApiResponse::success($rekap, 'Berhasil mengambil laporan absensi siswa');
    }
}
