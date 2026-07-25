<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomponenPenilaian extends Model
{
    protected $table = 'komponen_penilaian';

    protected $primaryKey = 'id_komponen';

    protected $fillable = [
        'id_skema',
        'nama_komponen',
        'kode_komponen',
        'bobot',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'bobot' => 'float',
        'urutan' => 'integer',
        'is_active' => 'boolean',
    ];

    public function skema()
    {
        return $this->belongsTo(SkemaPenilaian::class, 'id_skema', 'id_skema');
    }

    public function nilaiSiswa()
    {
        return $this->hasMany(NilaiKomponenSiswa::class, 'id_komponen', 'id_komponen');
    }
}
