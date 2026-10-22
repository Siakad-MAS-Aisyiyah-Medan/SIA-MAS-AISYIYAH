<?php

namespace App\Http\Resources;

use App\Services\PpdbBerkasService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PendaftaranResource extends JsonResource
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
            'id_pendaftaran' => $this->id_pendaftaran,
            'id_user' => $this->id_user,
            'nama_lengkap' => $this->nama_lengkap,
            'tempat_lahir' => $this->tempat_lahir,
            'tgl_lahir' => $this->tgl_lahir,
            'jenis_kelamin' => $this->jenis_kelamin,
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
            'sekolah_asal' => $this->sekolah_asal,
            'no_sttb' => $this->no_sttb,
            'pindahan_dari' => $this->pindahan_dari,
            'no_surat_pindah' => $this->no_surat_pindah,
            'nama_ayah' => $this->nama_ayah,
            'nama_ibu' => $this->nama_ibu,
            'pendidikan_ayah' => $this->pendidikan_ayah,
            'pendidikan_ibu' => $this->pendidikan_ibu,
            'pekerjaan_ayah' => $this->pekerjaan_ayah,
            'pekerjaan_ibu' => $this->pekerjaan_ibu,
            'agama_ortu' => $this->agama_ortu,
            'alamat_ortu' => $this->alamat_ortu,
            'nama_wali' => $this->nama_wali,
            'pendidikan_wali' => $this->pendidikan_wali,
            'pekerjaan_wali' => $this->pekerjaan_wali,
            'agama_wali' => $this->agama_wali,
            'alamat_wali' => $this->alamat_wali,
            'hobi' => $this->hobi,
            'cita_cita' => $this->cita_cita,
            'ppdb_status' => $this->ppdb_status,
            'status_kelulusan' => $this->status_kelulusan,
            'files' => $this->fileUrls(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->adminView) {
            $data['catatan_admin'] = $this->catatan_admin;
            $data['file_paths'] = $this->filePaths();
            if ($this->relationLoaded('user') && $this->user) {
                $data['user'] = (new UserResource($this->user))->resolve();
            }
        }

        return $data;
    }

    private function fileUrls(): array
    {
        $keys = [
            'file_ijazah', 'file_stk', 'file_pas_photo', 'file_nisn', 'file_kk', 'file_ktp_ortua',
        ];
        $out = [];
        foreach ($keys as $key) {
            $path = $this->{$key};
            $jenis = array_search($key, PpdbBerkasService::FILE_COLUMNS, true);
            $out[$key] = $path ? PpdbBerkasService::documentUrl($this->id_pendaftaran, $jenis) : null;
        }

        return $out;
    }

    private function filePaths(): array
    {
        return [
            'file_ijazah' => $this->file_ijazah,
            'file_stk' => $this->file_stk,
            'file_pas_photo' => $this->file_pas_photo,
            'file_nisn' => $this->file_nisn,
            'file_kk' => $this->file_kk,
            'file_ktp_ortua' => $this->file_ktp_ortua,
        ];
    }
}
