<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_LULUS = 'lulus';

    public const STATUS_TIDAK_AKTIF = 'tidak_aktif';

    public const STATUSES = [
        self::STATUS_AKTIF,
        self::STATUS_LULUS,
        self::STATUS_TIDAK_AKTIF,
    ];

    protected $table = 'siswa';

    protected $primaryKey = 'id_siswa';

    protected $fillable = [
        'id_user', 'nisn', 'nis', 'nama_siswa', 'tempat_lahir',
        'tgl_lahir', 'jenis_kelamin', 'agama', 'alamat', 'nama_wali', 'no_hp_wali',
        'no_hp', 'tahun_masuk', 'tahun_lulus', 'status_siswa', 'status_diubah_pada',
        'id_kelas', 'foto',
    ];

    protected $casts = [
        'tgl_lahir' => 'date',
        'status_diubah_pada' => 'datetime',
    ];

    public function scopeAktif($query)
    {
        return $query->where('status_siswa', self::STATUS_AKTIF);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id_kelas');
    }
}
