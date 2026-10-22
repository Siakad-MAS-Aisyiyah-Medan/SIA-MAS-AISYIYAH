<?php

namespace App\Http\Controllers;

use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\JadwalService;
use App\Utils\ApiResponse;
use App\Utils\GuruPengampuUser;
use Illuminate\Http\Request;
use InvalidArgumentException;

class JadwalController extends Controller
{
    public function __construct(private JadwalService $service) {}

    public function adminIndex(Request $request)
    {
        $paginator = $this->service->list(
            $request->query('search'),
            (int) $request->query('per_page', 15)
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data jadwal');
    }

    public function adminStore(Request $request)
    {
        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'id_guru' => ['required', 'integer', 'exists:users,id_user', new GuruPengampuUser],
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|in:Ganjil,Genap',
        ]);

        try {
            $jadwal = $this->service->create($validated);

            return ApiResponse::success($jadwal, 'Jadwal berhasil ditambahkan', 201);

        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal menyimpan jadwal', 500);
        }
    }

    public function adminUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'id_guru' => ['required', 'integer', 'exists:users,id_user', new GuruPengampuUser],
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|in:Ganjil,Genap',
        ]);

        try {
            $jadwal = $this->service->processUpdate((int) $id, $validated);

            return ApiResponse::success($jadwal, 'Jadwal berhasil diperbarui');

        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal memperbarui jadwal', 500);
        }
    }

    public function adminDestroy($id)
    {
        try {
            $this->service->delete((int) $id);

            return ApiResponse::success(null, 'Jadwal berhasil dihapus');
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal menghapus jadwal', 500);
        }
    }

    public function guruIndex(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'guru') {
            return ApiResponse::error('Akses jadwal mengajar hanya untuk guru.', 403);
        }

        $period = TahunAjaran::where('status', 'Aktif')->first();
        $year = now()->month >= 7 ? now()->year : now()->year - 1;
        $tahunAjaran = $period?->tahun_ajaran ?? $year.'/'.($year + 1);
        $semester = $period?->semester ?? (now()->month >= 7 ? 'Ganjil' : 'Genap');

        $items = Mapel::with(['kelas'])
            ->where('id_guru', $user->id_user)
            ->get()
            ->map(function ($mapel) use ($tahunAjaran, $semester) {
                return $mapel->kelas->map(function ($kelas) use ($mapel, $tahunAjaran, $semester) {
                    return [
                        'id_mapel' => $mapel->id_mapel,
                        'id_kelas' => $kelas->id_kelas,
                        'tahun_ajaran' => $tahunAjaran,
                        'semester' => $semester,
                        'mapel' => [
                            'id_mapel' => $mapel->id_mapel,
                            'nama_mapel' => $mapel->nama_mapel,
                            'tingkat' => $mapel->tingkat,
                        ],
                        'kelas' => [
                            'id_kelas' => $kelas->id_kelas,
                            'nama_kelas' => $kelas->nama_kelas,
                            'tingkat' => $kelas->tingkat,
                        ],
                    ];
                });
            })
            ->flatten(1)
            ->values()
            ->all();

        return ApiResponse::success($items, 'Berhasil mengambil daftar penugasan kelas');
    }

    public function guruMuridDiajar(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'guru') {
            return ApiResponse::error('Akses data murid diajar hanya untuk guru.', 403);
        }

        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'id_mapel' => 'required|exists:mata_pelajaran,id_mapel',
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|in:Ganjil,Genap',
        ]);

        $mapelExists = Mapel::where('id_mapel', $validated['id_mapel'])
            ->where('id_guru', $user->id_user)
            ->whereHas('kelas', function ($q) use ($validated) {
                $q->where('kelas_mapel.id_kelas', $validated['id_kelas']);
            })
            ->exists();

        if (! $mapelExists) {
            return ApiResponse::error('Anda tidak mengampu kelas atau mata pelajaran ini.', 422);
        }

        $murid = Siswa::with('user')
            ->aktif()
            ->where('id_kelas', $validated['id_kelas'])
            ->orderBy('nama_siswa')
            ->get()
            ->map(function (Siswa $siswa) {
                return [
                    'id_user_siswa' => $siswa->id_user,
                    'nama_siswa' => $siswa->nama_siswa,
                    'nisn' => $siswa->nisn ?: $siswa->user?->username,
                    'jenis_kelamin' => $siswa->jenis_kelamin === 'L'
                        ? 'Laki-laki'
                        : ($siswa->jenis_kelamin === 'P' ? 'Perempuan' : null),
                ];
            })
            ->values();

        return ApiResponse::success([
            'meta' => [
                'id_kelas' => (int) $validated['id_kelas'],
                'id_mapel' => (int) $validated['id_mapel'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
            ],
            'siswa' => $murid,
        ], 'Berhasil mengambil data murid yang diajar');
    }

    public function siswaIndex(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'siswa') {
            return ApiResponse::error('Akses jadwal pelajaran hanya untuk siswa.', 403);
        }

        try {
            $items = $this->service->listForSiswa(
                (int) $user->id_user,
                $request->query('tahun_ajaran'),
                $request->query('semester')
            );

            return ApiResponse::success($items, 'Berhasil mengambil jadwal pelajaran');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
