<?php

namespace App\Http\Controllers;

use App\Models\Pendaftaran;
use App\Services\PpdbBerkasService;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class BerkasController extends Controller
{
    use AuditsAdminActions;

    public function __construct(private PpdbBerkasService $berkasService) {}

    public function show(Pendaftaran $pendaftaran, string $jenis)
    {
        $column = PpdbBerkasService::FILE_COLUMNS[$jenis] ?? null;
        abort_unless($column, 404);
        $path = $pendaftaran->dokumen?->{$column};
        $disk = Storage::disk(config('ppdb.berkas.storage_disk'));
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index()
    {
        try {
            return ApiResponse::success($this->berkasService->listForUser(Auth::user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function store(Request $request)
    {
        $jenis = implode(',', PpdbBerkasService::allJenisKeys());
        $maxKb = (int) config('ppdb.berkas.max_size_kb', 2048);

        $validated = $request->validate([
            'jenis_berkas' => 'required|string|in:'.$jenis,
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:'.$maxKb,
        ]);

        try {
            $item = $this->berkasService->upload(
                Auth::user(),
                $validated['jenis_berkas'],
                $request->file('file')
            );

            $this->auditAdmin('calon_siswa.berkas.upload', null, ['jenis_berkas' => $validated['jenis_berkas']]);

            return ApiResponse::success($item, 'Berkas berhasil diunggah');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function destroy(string $jenis)
    {
        try {
            $this->berkasService->delete(Auth::user(), $jenis);

            $this->auditAdmin('calon_siswa.berkas.delete', null, ['jenis_berkas' => $jenis]);

            return ApiResponse::success(null, 'Berkas berhasil dihapus');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
