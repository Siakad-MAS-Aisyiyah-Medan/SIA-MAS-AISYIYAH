<?php

namespace App\Services;

use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Models\User;
use App\Utils\AuditsAdminActions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EnrollmentService
{
    use AuditsAdminActions;

    public function __construct(private PendaftaranStateService $pendaftaranState) {}

    public function enrollCalonSiswa(int $userId, ?int $idKelas = null): array
    {
        return DB::transaction(function () use ($userId, $idKelas) {
            $user = User::lockForUpdate()->find($userId);
            if (! $user) {
                throw new InvalidArgumentException('User tidak ditemukan.');
            }

            $pendaftaran = Pendaftaran::where('id_user', $user->id_user)->lockForUpdate()->first();
            if (! $pendaftaran) {
                throw new InvalidArgumentException('Data pendaftaran tidak ditemukan.');
            }

            $this->pendaftaranState->assertCanEnroll($pendaftaran, $user);

            $nisn = $user->username;
            $nis = $this->generateUniqueNis();

            Siswa::create([
                'id_user' => $user->id_user,
                'nisn' => $nisn,
                'nis' => $nis,
                'nama_siswa' => $pendaftaran->nama_lengkap ?: 'Siswa Baru',
                'tempat_lahir' => $pendaftaran->tempat_lahir ?: '-',
                'tgl_lahir' => $pendaftaran->tgl_lahir,
                'jenis_kelamin' => $this->resolveJenisKelamin($pendaftaran),
                'agama' => $pendaftaran->agama ?: '-',
                'alamat' => $pendaftaran->alamat ?: '-',
                'nama_wali' => $pendaftaran->nama_wali ?: $pendaftaran->nama_ayah ?: '-',
                'no_hp_wali' => $pendaftaran->no_telp ?: '-',
                'id_kelas' => $idKelas,
                'status_siswa' => Siswa::STATUS_AKTIF,
                'status_diubah_pada' => now(),
            ]);

            $user->role = 'siswa';
            $user->status_aktif = true;
            $user->status_akun = 'aktif';
            $user->save();

            $pendaftaran = $this->pendaftaranState->markEnrolled($pendaftaran);

            $this->auditAdmin('murid.enroll', $user, [
                'username' => $user->username,
                'id_kelas' => $idKelas,
            ]);

            return [
                'user' => $user->fresh(['siswa']),
                'pendaftaran' => $pendaftaran,
                'siswa' => $user->fresh(['siswa'])->siswa,
            ];
        });
    }

    protected function resolveJenisKelamin(Pendaftaran $pendaftaran): string
    {
        return in_array($pendaftaran->jenis_kelamin, ['L', 'P'], true)
            ? $pendaftaran->jenis_kelamin
            : 'L';
    }

    public function generateUniqueNis(): string
    {
        $year = date('Y');
        $maxAttempts = 15;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $last = Siswa::where('nis', 'like', $year.'%')
                ->lockForUpdate()
                ->orderByDesc('nis')
                ->value('nis');

            $seq = 1;
            if ($last && str_starts_with($last, $year)) {
                $seq = (int) substr($last, 4) + 1 + $attempt;
            }

            $nis = $year.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            if (! Siswa::where('nis', $nis)->exists()) {
                return $nis;
            }
        }

        throw new InvalidArgumentException('Gagal menghasilkan NIS unik. Silakan coba lagi.');
    }
}
