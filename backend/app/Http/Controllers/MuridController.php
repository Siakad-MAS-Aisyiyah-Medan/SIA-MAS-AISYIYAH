<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class MuridController extends Controller
{
    public function __construct(private EnrollmentService $enrollment) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer',
            'id_kelas' => 'nullable|integer|exists:kelas,id_kelas',
            'status_siswa' => 'nullable|in:aktif,lulus,tidak_aktif',
        ]);

        $paginator = $this->listMurid(
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 15),
            $validated['id_kelas'] ?? null,
            $validated['status_siswa'] ?? null
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil data murid');
    }

    public function stats()
    {
        $stats = $this->getStats();

        return ApiResponse::success($stats, 'Berhasil mengambil statistik murid');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'nama_siswa' => 'required|string',
            'nisn' => 'nullable|string|min:10|unique:siswa,nisn',
            'nis' => 'nullable|string',
            'jenis_kelamin' => 'required|in:L,P',
            'tempat_lahir' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string',
            'no_hp' => 'nullable|string',
            'tahun_masuk' => 'required|integer',
            'tahun_lulus' => 'nullable|integer',
            'status_siswa' => 'nullable|in:aktif,lulus,tidak_aktif',
            'id_kelas' => 'nullable|exists:kelas,id_kelas',
            'status_aktif' => 'nullable|boolean',
        ]);

        try {
            $user = $this->createMurid($validated);

            return ApiResponse::success($user, 'Murid berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan murid: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'username' => 'nullable|string',
                'email' => 'nullable|email',
                'password' => 'nullable|string|min:6',
                'role' => 'nullable|in:siswa,calon_siswa',
                'nama_siswa' => 'required|string',
                'nisn' => 'nullable|string|min:10|unique:siswa,nisn,'.$id.',id_user',
                'nis' => 'nullable|string',
                'jenis_kelamin' => 'required|in:L,P',
                'tempat_lahir' => 'nullable|string',
                'tanggal_lahir' => 'nullable|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string',
                'tahun_masuk' => 'nullable|integer',
                'tahun_lulus' => 'nullable|integer',
                'status_siswa' => 'nullable|in:aktif,lulus,tidak_aktif',
                'id_kelas' => 'nullable|exists:kelas,id_kelas',
                'status_aktif' => 'nullable|boolean',
            ]);
            $user = $this->updateMurid((int) $id, $validated);

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
            $result = $this->enrollMurid((int) $id, $validated['id_kelas'] ?? null);

            return ApiResponse::success($result, 'Calon siswa berhasil dipromosikan menjadi siswa');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function destroy($id)
    {
        try {
            $this->deleteMurid((int) $id);

            return ApiResponse::success(null, 'Data murid berhasil dihapus permanen');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    // --- Inlined from MuridService ---

    use AuditsAdminActions;

    private function listMurid(
        ?string $search = null,
        int $perPage = 15,
        ?int $idKelas = null,
        ?string $statusSiswa = null
    ): LengthAwarePaginator {
        $query = User::query()
            ->with(['siswa.kelas', 'pendaftaran'])
            ->where('role', 'siswa');

        if ($idKelas) {
            $query->whereHas('siswa', fn ($s) => $s->where('id_kelas', $idKelas));
        }

        if ($statusSiswa) {
            $query->whereHas('siswa', fn ($s) => $s->where('status_siswa', $statusSiswa));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('siswa', fn ($s) => $s
                        ->where('nama_siswa', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%"))
                    ->orWhereHas('siswa.kelas', fn ($k) => $k
                        ->where('nama_kelas', 'like', "%{$search}%"))
                    ->orWhereHas('pendaftaran', fn ($p) => $p->where('nama_lengkap', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc('id_user')->paginate($perPage);
    }

    private function getStats(): array
    {
        return [
            'total_murid' => Siswa::count(),
            'siswa_aktif' => Siswa::where('status_siswa', Siswa::STATUS_AKTIF)->count(),
            'calon_siswa' => User::where('role', 'calon_siswa')->count(),
            'alumni' => Siswa::where('status_siswa', Siswa::STATUS_LULUS)->count(),
            'siswa_tidak_aktif' => Siswa::where('status_siswa', Siswa::STATUS_TIDAK_AKTIF)->count(),
        ];
    }

    private function createMurid(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $statusSiswa = $data['status_siswa'] ?? Siswa::STATUS_AKTIF;
            $akunAktif = $statusSiswa === Siswa::STATUS_AKTIF
                ? ($data['status_aktif'] ?? true)
                : false;

            $user = User::create([
                'name' => $data['nama_siswa'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => 'siswa',
                'status_aktif' => $akunAktif,
                'status_akun' => $akunAktif ? 'aktif' : 'nonaktif',
            ]);

            $user->siswa()->create([
                'nama_siswa' => $data['nama_siswa'],
                'nisn' => $data['nisn'] ?? $data['username'],
                'nis' => $data['nis'] ?? $this->enrollment->generateUniqueNis(),
                'jenis_kelamin' => $data['jenis_kelamin'],
                'tempat_lahir' => $data['tempat_lahir'] ?? '-',
                'tgl_lahir' => $data['tanggal_lahir'] ?? now()->toDateString(),
                'agama' => $data['agama'] ?? 'Islam',
                'alamat' => $data['alamat'] ?? null,
                'nama_wali' => $data['nama_wali'] ?? '-',
                'no_hp_wali' => $data['no_hp_wali'] ?? ($data['no_hp'] ?? '-'),
                'no_hp' => $data['no_hp'] ?? null,
                'tahun_masuk' => $data['tahun_masuk'],
                'tahun_lulus' => $statusSiswa === Siswa::STATUS_LULUS
                    ? ($data['tahun_lulus'] ?? now()->year)
                    : null,
                'status_siswa' => $statusSiswa,
                'status_diubah_pada' => now(),
                'id_kelas' => $data['id_kelas'] ?? null,
            ]);

            $fresh = $user->fresh(['siswa']);
            $this->auditAdmin('murid.create', $fresh, ['username' => $fresh->username]);

            return $fresh;
        });
    }

    private function updateMurid(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $statusSebelum = $user->siswa?->status_siswa ?? Siswa::STATUS_AKTIF;
        if (! in_array($user->role, ['siswa', 'calon_siswa'], true)) {
            throw new InvalidArgumentException('User bukan murid/calon siswa.');
        }

        if (array_key_exists('role', $data) && $data['role'] !== $user->role) {
            if ($data['role'] === 'siswa') {
                throw new InvalidArgumentException(
                    'Promosi ke siswa hanya melalui aksi Jadikan Siswa (enroll).'
                );
            }
            throw new InvalidArgumentException('Perubahan role tidak diizinkan melalui update.');
        }

        DB::transaction(function () use ($user, $data) {
            $statusLama = $user->siswa?->status_siswa ?? Siswa::STATUS_AKTIF;
            $statusSiswa = $data['status_siswa'] ?? $statusLama;
            $akunAktif = $statusSiswa === Siswa::STATUS_AKTIF
                ? ($data['status_aktif'] ?? $user->status_aktif)
                : false;

            $user->update([
                'status_aktif' => $akunAktif,
                'status_akun' => $akunAktif ? 'aktif' : 'nonaktif',
            ]);

            if (! $akunAktif) {
                $user->tokens()->delete();
            }

            if ($user->siswa) {
                $siswaData = [];
                $fields = ['nama_siswa', 'nisn', 'nis', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'alamat', 'no_hp', 'tahun_masuk', 'id_kelas'];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $data)) {
                        // Prevent wiping NOT NULL columns
                        if (($field === 'nis' || $field === 'nisn') && $data[$field] === null) {
                            continue;
                        }
                        $siswaData[$field === 'tanggal_lahir' ? 'tgl_lahir' : $field] = $data[$field];
                    }
                }

                $siswaData['status_siswa'] = $statusSiswa;
                $siswaData['tahun_lulus'] = $statusSiswa === Siswa::STATUS_LULUS
                    ? ($data['tahun_lulus'] ?? $user->siswa->tahun_lulus ?? now()->year)
                    : null;

                if ($statusSiswa !== $statusLama) {
                    $siswaData['status_diubah_pada'] = now();
                }

                if (! empty($siswaData)) {
                    $user->siswa->update($siswaData);
                }
            }
        });

        $fresh = $user->fresh(['siswa', 'pendaftaran']);
        $this->auditAdmin('murid.update', $fresh, [
            'username' => $fresh->username,
            'status_sebelum' => $statusSebelum,
            'status_sesudah' => $fresh->siswa?->status_siswa,
            'tahun_lulus' => $fresh->siswa?->tahun_lulus,
        ]);

        return $fresh;
    }

    private function deleteMurid(int $id): void
    {
        $user = User::findOrFail($id);
        if (! in_array($user->role, ['siswa', 'calon_siswa'], true)) {
            throw new InvalidArgumentException('User bukan murid/calon siswa.');
        }

        if ($user->role === 'siswa') {
            if ($user->siswa?->status_siswa === Siswa::STATUS_LULUS) {
                throw new InvalidArgumentException(
                    'Data alumni tidak boleh dihapus. Gunakan arsip alumni untuk mempertahankan riwayat akademik.'
                );
            }

            $hasAcademicHistory = DB::table('nilai')->where('id_user_siswa', $id)->exists()
                || DB::table('absensi')->where('id_user_siswa', $id)->exists();

            if ($hasAcademicHistory) {
                throw new InvalidArgumentException(
                    'Data murid memiliki riwayat nilai atau absensi dan tidak boleh dihapus permanen. Ubah statusnya menjadi Tidak Aktif.'
                );
            }
        }

        $this->auditAdmin('murid.delete', $user, ['username' => $user->username]);
        $user->delete();
    }

    private function enrollMurid(int $id, ?int $idKelas = null): array
    {
        return $this->enrollment->enrollCalonSiswa($id, $idKelas);
    }
}
