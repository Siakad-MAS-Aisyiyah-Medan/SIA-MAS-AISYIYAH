<?php

namespace App\Services;

use App\Models\Pendaftaran;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PendaftaranStateService
{
    public const STATUSES = [
        'draft',
        'diajukan',
        'revisi',
        'terverifikasi',
        'diterima',
        'ditolak',
        'daftar_ulang',
        'menjadi_murid',
    ];

    public const CALON_FILLABLE = [
        'nama_lengkap',
        'tempat_lahir',
        'tgl_lahir',
        'jenis_kelamin',
        'agama',
        'kewarganegaraan',
        'anak_ke',
        'jml_saudara_kandung',
        'jml_saudara_tiri',
        'alamat',
        'no_telp',
        'status_yatim',
        'berat_badan',
        'tinggi_badan',
        'gol_darah',
        'penyakit_diderita',
        'cacat_badan',
        'sekolah_asal',
        'tahun_lulus',
        'no_sttb',
        'pindahan_dari',
        'no_surat_pindah',
        'nama_ayah',
        'nama_ibu',
        'pendidikan_ayah',
        'pendidikan_ibu',
        'pekerjaan_ayah',
        'pekerjaan_ibu',
        'agama_ortu',
        'alamat_ortu',
        'no_hp_ortu',
        'nama_wali',
        'pendidikan_wali',
        'pekerjaan_wali',
        'agama_wali',
        'alamat_wali',
        'hobi',
        'cita_cita',
        'nisn',
    ];

    protected const ADMIN_TRANSITIONS = [
        'terverifikasi' => ['diajukan', 'submitted'],
        'revisi' => ['diajukan', 'submitted', 'terverifikasi', 'verified'],
        'diterima' => ['diajukan', 'submitted', 'terverifikasi', 'verified', 'daftar_ulang'],
        'ditolak' => ['diajukan', 'submitted', 'terverifikasi', 'verified', 'revisi'],
    ];

    protected const REQUIRED_FORM = [
        'nama_lengkap',
        'tempat_lahir',
        'tgl_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'sekolah_asal',
        'nama_ayah',
        'nama_ibu',
        'pekerjaan_ayah',
        'pekerjaan_ibu',
    ];

    /**
     * @deprecated Mulai formulir melalui endpoint PPDB start, terpisah dari registrasi akun.
     */
    public function createDraftForUser(User $user, array $defaults = []): Pendaftaran
    {
        return Pendaftaran::firstOrCreate(
            ['id_user' => $user->id_user],
            array_merge([
                'nama_lengkap' => $defaults['nama_lengkap'] ?? '',
                'nisn' => $user->username,
                'tempat_lahir' => '',
                'tgl_lahir' => now()->toDateString(),
                'jenis_kelamin' => 'L',
                'agama' => '',
                'kewarganegaraan' => 'Indonesia',
                'anak_ke' => 1,
                'jml_saudara_kandung' => 0,
                'jml_saudara_tiri' => 0,
                'alamat' => '',
                'no_telp' => '',
                'status_yatim' => 'Tidak',
                'berat_badan' => 0,
                'tinggi_badan' => 0,
                'gol_darah' => '-',
                'sekolah_asal' => '',
                'no_sttb' => '',
                'nama_ayah' => '',
                'nama_ibu' => '',
                'pendidikan_ayah' => '',
                'pendidikan_ibu' => '',
                'pekerjaan_ayah' => '',
                'pekerjaan_ibu' => '',
                'agama_ortu' => '',
                'alamat_ortu' => '',
                'no_hp_ortu' => '',
                'hobi' => '',
                'cita_cita' => '',
                'ppdb_status' => 'draft',
                'status_kelulusan' => 'Pending',
            ], $defaults)
        );
    }

    public function saveDraft(User $user, array $data): Pendaftaran
    {
        $pendaftaran = $this->getOwnedOrFail($user);
        $this->assertEditableByCalon($pendaftaran);

        $safe = $this->filterCalonInput($data);
        $pendaftaran->fill($safe);
        $pendaftaran->save();

        return $pendaftaran->fresh();
    }

    public function submit(User $user): Pendaftaran
    {
        $pendaftaran = $this->getOwnedOrFail($user);
        $this->assertEditableByCalon($pendaftaran);

        if (! in_array($pendaftaran->ppdb_status, ['draft', 'revisi'], true)) {
            throw new InvalidArgumentException('Pendaftaran sudah dikirim atau sedang diproses.');
        }

        $this->assertCompleteForSubmit($pendaftaran);

        return DB::transaction(function () use ($pendaftaran) {
            $pendaftaran->ppdb_status = 'diajukan';
            $pendaftaran->status_pendaftaran = 'submitted';
            $pendaftaran->status_kelulusan = 'Pending';
            $pendaftaran->submitted_at = now();
            $pendaftaran->save();

            return $pendaftaran->fresh(['berkas']);
        });
    }

    public function transitionByAdmin(Pendaftaran $pendaftaran, string $status, ?string $catatan = null): Pendaftaran
    {
        if (! in_array($status, ['terverifikasi', 'revisi', 'diterima', 'ditolak'], true)) {
            throw new InvalidArgumentException('Status admin tidak valid.');
        }

        if ($pendaftaran->ppdb_status === 'menjadi_murid') {
            throw new InvalidArgumentException('Pendaftaran sudah menjadi murid aktif.');
        }

        $allowedFrom = self::ADMIN_TRANSITIONS[$status] ?? [];
        if (! in_array($pendaftaran->ppdb_status, $allowedFrom, true)) {
            throw new InvalidArgumentException(
                "Tidak dapat mengubah status ke {$status} dari status {$pendaftaran->ppdb_status}."
            );
        }

        if (in_array($status, ['revisi', 'ditolak'], true) && empty(trim((string) $catatan))) {
            throw new InvalidArgumentException('Catatan admin wajib diisi.');
        }

        return DB::transaction(function () use ($pendaftaran, $status, $catatan) {
            $pendaftaran->ppdb_status = $status;
            $pendaftaran->status_pendaftaran = match ($status) {
                'revisi' => 'revision',
                'terverifikasi' => 'verified',
                'diterima' => 'accepted',
                'ditolak' => 'rejected',
                default => $pendaftaran->status_pendaftaran,
            };
            if ($status === 'revisi') {
                $pendaftaran->current_step = '1';
            }
            if ($catatan !== null && $catatan !== '') {
                $pendaftaran->catatan_admin = $catatan;
            }
            if ($status === 'terverifikasi') {
                $pendaftaran->verified_at = now();
            }
            if ($status === 'diterima') {
                $pendaftaran->accepted_at = now();
                $pendaftaran->status_kelulusan = 'Lulus';
            }
            if ($status === 'ditolak') {
                $pendaftaran->status_kelulusan = 'Tidak Lulus';
            }
            $pendaftaran->save();

            return $pendaftaran->fresh(['berkas', 'user']);
        });
    }

    public function assertCanEnroll(Pendaftaran $pendaftaran, User $user): void
    {
        if ($user->role !== 'calon_siswa') {
            throw new InvalidArgumentException('User bukan calon murid.');
        }

        if ($pendaftaran->ppdb_status === 'menjadi_murid') {
            throw new InvalidArgumentException('Calon murid sudah menjadi siswa aktif.');
        }

        if (! in_array($pendaftaran->ppdb_status, ['diterima', 'daftar_ulang'], true)) {
            throw new InvalidArgumentException('PPDB harus berstatus diterima sebelum promosi.');
        }

        if ($user->siswa()->exists()) {
            throw new InvalidArgumentException('Profil siswa sudah ada.');
        }
    }

    public function markEnrolled(Pendaftaran $pendaftaran): Pendaftaran
    {
        if ($pendaftaran->ppdb_status === 'menjadi_murid') {
            throw new InvalidArgumentException('Sudah menjadi murid aktif.');
        }

        $pendaftaran->ppdb_status = 'menjadi_murid';
        $pendaftaran->status_kelulusan = 'Lulus';
        $pendaftaran->save();

        return $pendaftaran->fresh();
    }

    public function getOwnedOrFail(User $user): Pendaftaran
    {
        $pendaftaran = Pendaftaran::where('id_user', $user->id_user)->first();
        if (! $pendaftaran) {
            throw new InvalidArgumentException('Data pendaftaran tidak ditemukan.');
        }

        return $pendaftaran;
    }

    protected function assertEditableByCalon(Pendaftaran $pendaftaran): void
    {
        $locked = ['diajukan', 'terverifikasi', 'diterima', 'ditolak', 'daftar_ulang', 'menjadi_murid'];
        if (in_array($pendaftaran->ppdb_status, $locked, true)) {
            throw new InvalidArgumentException(
                'Pendaftaran tidak dapat diubah pada status: '.$pendaftaran->ppdb_status.'.'
            );
        }
    }

    protected function assertCompleteForSubmit(Pendaftaran $pendaftaran): void
    {
        foreach (self::REQUIRED_FORM as $field) {
            if (empty(trim((string) $pendaftaran->{$field}))) {
                throw new InvalidArgumentException("Field {$field} wajib diisi sebelum submit.");
            }
        }

        app(PpdbBerkasService::class)->assertAllRequiredUploaded($pendaftaran->load('dokumen'));
    }

    protected function filterCalonInput(array $data): array
    {
        return array_intersect_key($data, array_flip(self::CALON_FILLABLE));
    }
}
