<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nilai extends Model
{
    protected $table = 'nilai';

    protected $primaryKey = 'id_nilai';

    protected $fillable = [
        'id_user_siswa',
        'id_mapel',
        'id_guru_input',
        'id_skema',
        'semester',
        'tahun_ajaran',
        'nilai_tugas',
        'nilai_uts',
        'nilai_uas',
        'nilai_praktik',
        'nilai_sikap',
        'nilai_akhir',
        'nilai_angka',
        'predikat',
        'validated_by_wali',
        'id_wali_validator',
        'validated_at',
    ];

    protected $casts = [
        'nilai_tugas' => 'integer',
        'nilai_uts' => 'integer',
        'nilai_uas' => 'integer',
        'nilai_praktik' => 'integer',
        'nilai_sikap' => 'integer',
        'nilai_akhir' => 'integer',
        'nilai_angka' => 'integer',
        'validated_by_wali' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function siswa()
    {
        return $this->belongsTo(User::class, 'id_user_siswa', 'id_user');
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class, 'id_mapel', 'id_mapel');
    }

    public function guruInput()
    {
        return $this->belongsTo(User::class, 'id_guru_input', 'id_user');
    }

    public function waliValidator()
    {
        return $this->belongsTo(User::class, 'id_wali_validator', 'id_user');
    }

    public function skema()
    {
        return $this->belongsTo(SkemaPenilaian::class, 'id_skema', 'id_skema');
    }
}
