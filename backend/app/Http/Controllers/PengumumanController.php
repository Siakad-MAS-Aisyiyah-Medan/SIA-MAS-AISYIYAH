<?php

namespace App\Http\Controllers;

use App\Http\Resources\PengumumanResource;
use App\Models\Pengumuman;
use App\Services\PengumumanService;
use App\Utils\ApiResponse;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function __construct(private PengumumanService $service) {}

    public function adminIndex(Request $request)
    {
        $paginator = $this->service->list(
            $request->query('search'),
            (int) $request->query('per_page', 15)
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data pengumuman');
    }

    public function adminShow($id)
    {
        return ApiResponse::success(
            $this->service->find((int) $id),
            'Berhasil mengambil detail pengumuman'
        );
    }

    public function adminStore(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'isi' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            'akses' => 'required|in:umum,internal',
            'kategori' => 'nullable|string|max:100',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $item = $this->service->create($validated);

        return ApiResponse::success($item, 'Pengumuman berhasil ditambahkan', 201);
    }

    public function adminUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'isi' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            'akses' => 'required|in:umum,internal',
            'kategori' => 'nullable|string|max:100',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $item = $this->service->processUpdate((int) $id, $validated);

        return ApiResponse::success($item, 'Pengumuman berhasil diperbarui');
    }

    public function adminDestroy($id)
    {
        $this->service->delete((int) $id);

        return ApiResponse::success(null, 'Pengumuman berhasil dihapus');
    }

    /**
     * Mengambil daftar pengumuman yang sudah dipublikasi.
     * Tampilan untuk publik (tanpa login).
     */
    public function publicIndex()
    {
        // Hanya pengumuman dengan akses umum yang boleh tampil di halaman publik.
        $pengumuman = Pengumuman::where('akses', 'umum')
            ->orderByDesc('tanggal_publikasi')
            ->orderByDesc('id')
            ->paginate(12);

        return ApiResponse::paginated(
            $pengumuman,
            'Berita dan pengumuman berhasil diambil',
            fn ($item) => (new PengumumanResource($item))->resolve()
        );
    }

    /**
     * Mengambil detail satu pengumuman.
     */
    public function publicShow($id)
    {
        // Pastikan hanya pengumuman dengan akses umum yang bisa dilihat publik.
        $pengumuman = Pengumuman::where('akses', 'umum')->findOrFail($id);

        return ApiResponse::success(
            (new PengumumanResource($pengumuman))->resolve(),
            'Detail berita berhasil diambil'
        );
    }
}
