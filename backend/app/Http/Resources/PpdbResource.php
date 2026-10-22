<?php

namespace App\Http\Resources;

use App\Services\PpdbBerkasService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PpdbResource extends JsonResource
{
    public function __construct($resource, private readonly bool $adminView = false)
    {
        parent::__construct($resource);
    }

    public static function applicant($resource): self
    {
        return new self($resource, false);
    }

    public static function admin($resource): self
    {
        return new self($resource, true);
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id_pendaftaran,
            'id_pendaftaran' => $this->id_pendaftaran,
            'user_id' => $this->id_user,
            'id_user' => $this->id_user,
            'no_registrasi' => $this->no_registrasi,
            'nisn' => $this->nisn ?? $this->user?->username,
            'nama_lengkap' => $this->nama_lengkap,
            'jenis_kelamin' => $this->jenis_kelamin,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tgl_lahir,
            'tgl_lahir' => $this->tgl_lahir,
            'agama' => $this->agama,
            'kewarganegaraan' => $this->kewarganegaraan,
            'anak_ke' => $this->anak_ke,
            'jml_saudara_kandung' => $this->jml_saudara_kandung,
            'jml_saudara_tiri' => $this->jml_saudara_tiri,
            'alamat' => $this->alamat,
            'no_telp' => $this->no_telp,
            'status_yatim' => $this->status_yatim,
            'berat_badan' => $this->berat_badan,
            'tinggi_badan' => $this->tinggi_badan,
            'gol_darah' => $this->gol_darah,
            'penyakit_diderita' => $this->penyakit_diderita,
            'cacat_badan' => $this->cacat_badan,
            'asal_sekolah' => $this->sekolah_asal,
            'sekolah_asal' => $this->sekolah_asal,
            'tahun_lulus' => $this->tahun_lulus,
            'no_sttb' => $this->no_sttb,
            'pindahan_dari' => $this->pindahan_dari,
            'no_surat_pindah' => $this->no_surat_pindah,
            'nama_ayah' => $this->nama_ayah,
            'pendidikan_ayah' => $this->pendidikan_ayah,
            'pekerjaan_ayah' => $this->pekerjaan_ayah,
            'nama_ibu' => $this->nama_ibu,
            'pendidikan_ibu' => $this->pendidikan_ibu,
            'pekerjaan_ibu' => $this->pekerjaan_ibu,
            'agama_ortu' => $this->agama_ortu,
            'alamat_ortu' => $this->alamat_ortu,
            'no_hp_ortu' => $this->no_hp_ortu,
            'no_hp_ayah' => $this->no_hp_ayah,
            'no_hp_ibu' => $this->no_hp_ibu,
            'nama_wali' => $this->nama_wali,
            'pendidikan_wali' => $this->pendidikan_wali,
            'pekerjaan_wali' => $this->pekerjaan_wali,
            'agama_wali' => $this->agama_wali,
            'alamat_wali' => $this->alamat_wali,
            'hobi' => $this->hobi,
            'cita_cita' => $this->cita_cita,
            'status' => $this->ppdb_status,
            'ppdb_status' => $this->ppdb_status,
            'status_pendaftaran' => $this->status_pendaftaran,
            'current_step' => $this->current_step,
            'catatan_admin' => $this->when(! $this->adminView, $this->catatan_admin),
            'submitted_at' => $this->submitted_at,
            'verified_at' => $this->verified_at,
            'accepted_at' => $this->accepted_at,
            'berkas' => $this->berkasUrls(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->adminView) {
            $data['catatan_admin'] = $this->catatan_admin;
            if ($this->relationLoaded('user') && $this->user) {
                $data['user'] = (new UserResource($this->user))->resolve();
            }
        }

        return $data;
    }

    private function berkasUrls(): array
    {
        if (! $this->relationLoaded('dokumen') && ! $this->relationLoaded('berkas')) {
            return [];
        }

        $dokumen = $this->dokumen;
        if (! $dokumen && $this->relationLoaded('berkas')) {
            $dokumen = $this->berkas->first();
        }

        if (! $dokumen) {
            return [];
        }

        $columns = [
            'ijazah_atau_skl' => 'file_ijazah',
            'stk' => 'file_stk',
            'pas_foto' => 'file_pas_photo',
            'nisn' => 'file_nisn',
            'kartu_keluarga' => 'file_kk',
            'ktp_orang_tua' => 'file_ktp_ortua',
        ];

        $items = [];

        foreach ($columns as $jenis => $column) {
            $filePath = $dokumen->{$column} ?? null;
            if (! $filePath) {
                continue;
            }

            $label = PpdbBerkasService::JENIS[$jenis] ?? $jenis;

            $items[] = [
                'id' => $dokumen->id,
                'jenis_berkas' => $jenis,
                'label' => $label,
                'file_path' => $filePath,
                'url' => PpdbBerkasService::documentUrl($this->id_pendaftaran, $jenis),
                'status_verifikasi' => 'valid',
                'status' => 'valid',
                'catatan' => $dokumen->catatan_dokumen,
            ];
        }

        return $items;
    }
}
