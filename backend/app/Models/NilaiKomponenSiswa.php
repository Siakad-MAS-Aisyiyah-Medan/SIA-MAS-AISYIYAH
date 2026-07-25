<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NilaiKomponenSiswa extends Model
{
    protected $table = 'nilai_komponen_siswa';

    protected $primaryKey = 'id_nilai_komponen';

    protected $fillable = [
        'id_komponen',
        'id_user_siswa',
        'nilai',
        'catatan',
    ];

    protected $casts = [
        'nilai' => 'float',
    ];

    public function komponen()
    {
        return $this->belongsTo(KomponenPenilaian::class, 'id_komponen', 'id_komponen');
    }
}
