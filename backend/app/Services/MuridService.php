<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\User;
use App\Utils\AuditsAdminActions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class MuridService
{
    public function __construct(private EnrollmentService $enrollment) {}

    use AuditsAdminActions;

    public function listMurid(
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

    public function getStats(): array
    {
        return [
            'total_murid' => Siswa::count(),
            'siswa_aktif' => Siswa::where('status_siswa', Siswa::STATUS_AKTIF)->count(),
            'calon_siswa' => User::where('role', 'calon_siswa')->count(),
            'alumni' => Siswa::where('status_siswa', Siswa::STATUS_LULUS)->count(),
            'siswa_tidak_aktif' => Siswa::where('status_siswa', Siswa::STATUS_TIDAK_AKTIF)->count(),
        ];
    }

    public function createMurid(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $statusSiswa = $data['status_siswa'] ?? Siswa::STATUS_AKTIF;
            if ($statusSiswa === Siswa::STATUS_AKTIF) {
                app(StudentPlacementService::class)->assertAvailable($data['id_kelas'] ?? null);
            }
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

    public function updateMurid(int $id, array $data): User
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
            if ($statusSiswa === Siswa::STATUS_AKTIF) {
                app(StudentPlacementService::class)->assertAvailable($data['id_kelas'] ?? $user->siswa?->id_kelas, $user->id_user);
            }
            $akunAktif = $statusSiswa === Siswa::STATUS_AKTIF
                ? ($data['status_aktif'] ?? $user->status_aktif)
                : false;

            $user->update([
                'name' => $data['nama_siswa'],
                'username' => $data['username'] ?? $user->username,
                'email' => array_key_exists('email', $data) ? $data['email'] : $user->email,
                'status_aktif' => $akunAktif,
                'status_akun' => $akunAktif ? 'aktif' : 'nonaktif',
            ]);
            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }

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

    public function deleteMurid(int $id): void
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
        $user->tokens()->delete();
        $user->delete();
    }

    public function enrollMurid(int $id, ?int $idKelas = null): array
    {
        return $this->enrollment->enrollCalonSiswa($id, $idKelas);
    }
}
