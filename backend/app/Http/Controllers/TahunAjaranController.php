<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\TahunAjaran;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TahunAjaranController extends Controller
{
    use AuditsAdminActions;

    public function index()
    {
        $data = TahunAjaran::orderBy('tanggal_mulai', 'desc')->get();

        return ApiResponse::success($data, 'Data tahun ajaran berhasil diambil');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tahun_ajaran' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/', Rule::unique('tahun_ajarans')->where(fn ($q) => $q->where('semester', $request->semester))->ignore($id ?? null)],
            'semester' => 'required|in:Ganjil,Genap',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'status' => 'required|in:Aktif,Tidak Aktif,Selesai',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', 422, $validator->errors());
        }

        $tahunAjaran = DB::transaction(function () use ($request, $validator) {
            TahunAjaran::query()->lockForUpdate()->get();
            $tahunAjaran = TahunAjaran::create($validator->validated());
            if ($request->status === 'Aktif') {
                TahunAjaran::where('id', '!=', $tahunAjaran->id)->where('status', 'Aktif')->update(['status' => 'Selesai']);
                $this->updatePengaturanAktif($tahunAjaran->tahun_ajaran, $tahunAjaran->semester);
            }

            return $tahunAjaran;
        });

        $this->auditAdmin('admin.tahun_ajaran.create', $tahunAjaran, ['tahun_ajaran' => $tahunAjaran->tahun_ajaran, 'semester' => $tahunAjaran->semester]);

        return ApiResponse::success($tahunAjaran, 'Tahun ajaran berhasil ditambahkan', 201);
    }

    public function show($id)
    {
        $tahunAjaran = TahunAjaran::find($id);
        if (! $tahunAjaran) {
            return ApiResponse::error('Tahun ajaran tidak ditemukan', 404);
        }

        return ApiResponse::success($tahunAjaran, 'Data tahun ajaran berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'tahun_ajaran' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/', Rule::unique('tahun_ajarans')->where(fn ($q) => $q->where('semester', $request->semester))->ignore($id ?? null)],
            'semester' => 'required|in:Ganjil,Genap',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'status' => 'required|in:Aktif,Tidak Aktif,Selesai',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', 422, $validator->errors());
        }

        $tahunAjaran = TahunAjaran::find($id);
        if (! $tahunAjaran) {
            return ApiResponse::error('Tahun ajaran tidak ditemukan', 404);
        }

        DB::transaction(function () use ($request, $validator, $tahunAjaran) {
            TahunAjaran::query()->lockForUpdate()->get();
            if ($tahunAjaran->status === 'Aktif' && $request->status !== 'Aktif') {
                throw ValidationException::withMessages(['status' => 'Aktifkan periode pengganti untuk menutup periode aktif.']);
            }
            $tahunAjaran->update($validator->validated());
            if ($request->status === 'Aktif') {
                TahunAjaran::where('id', '!=', $tahunAjaran->id)->where('status', 'Aktif')->update(['status' => 'Selesai']);
                $this->updatePengaturanAktif($tahunAjaran->tahun_ajaran, $tahunAjaran->semester);
            }
        });

        $this->auditAdmin('admin.tahun_ajaran.update', $tahunAjaran, ['tahun_ajaran' => $tahunAjaran->tahun_ajaran, 'semester' => $tahunAjaran->semester]);

        return ApiResponse::success($tahunAjaran, 'Tahun ajaran berhasil diperbarui');
    }

    public function destroy($id)
    {
        $tahunAjaran = TahunAjaran::find($id);
        if (! $tahunAjaran) {
            return ApiResponse::error('Tahun ajaran tidak ditemukan', 404);
        }

        if ($tahunAjaran->status === 'Aktif') {
            return ApiResponse::error('Tidak dapat menghapus tahun ajaran yang sedang aktif', 400);
        }

        $tahunAjaran->delete();

        $this->auditAdmin('admin.tahun_ajaran.delete', $tahunAjaran, ['tahun_ajaran' => $tahunAjaran->tahun_ajaran, 'semester' => $tahunAjaran->semester]);

        return ApiResponse::success(null, 'Tahun ajaran berhasil dihapus');
    }

    public function activate($id)
    {
        $tahunAjaran = TahunAjaran::find($id);
        if (! $tahunAjaran) {
            return ApiResponse::error('Tahun ajaran tidak ditemukan', 404);
        }

        DB::transaction(function () use ($tahunAjaran) {
            TahunAjaran::query()->lockForUpdate()->get();
            TahunAjaran::where('id', '!=', $tahunAjaran->id)->where('status', 'Aktif')->update(['status' => 'Selesai']);
            $tahunAjaran->update(['status' => 'Aktif']);
            $this->updatePengaturanAktif($tahunAjaran->tahun_ajaran, $tahunAjaran->semester);
        });

        $this->auditAdmin('admin.tahun_ajaran.activate', $tahunAjaran, ['tahun_ajaran' => $tahunAjaran->tahun_ajaran, 'semester' => $tahunAjaran->semester]);

        return ApiResponse::success($tahunAjaran, 'Tahun ajaran berhasil diaktifkan');
    }

    private function updatePengaturanAktif($tahun_ajaran, $semester)
    {
        SystemSetting::updateOrCreate(
            ['key' => 'tahun_ajaran_aktif'],
            ['group' => 'academic', 'value' => $tahun_ajaran, 'type' => 'string', 'label' => 'Tahun Ajaran Aktif']
        );
        SystemSetting::updateOrCreate(
            ['key' => 'semester_aktif'],
            ['group' => 'academic', 'value' => $semester, 'type' => 'string', 'label' => 'Semester Aktif']
        );
    }
}
