<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkemaPenilaian extends Model
{
    protected $table = 'skema_penilaian';

    protected $primaryKey = 'id_skema';

    protected $fillable = [
        'id_guru',
        'id_mapel',
        'id_kelas',
        'tahun_ajaran',
        'semester',
        'nama_skema',
        'status',
        'versi',
    ];

    protected $casts = [
        'versi' => 'integer',
    ];

    public function komponen()
    {
        return $this->hasMany(KomponenPenilaian::class, 'id_skema', 'id_skema')->orderBy('urutan');
    }

    public function komponenAktif()
    {
        return $this->hasMany(KomponenPenilaian::class, 'id_skema', 'id_skema')
            ->where('is_active', true)
            ->orderBy('urutan');
    }
}
