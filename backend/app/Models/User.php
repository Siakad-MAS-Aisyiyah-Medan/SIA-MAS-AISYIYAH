<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id_user';

    /** Role akun - data PPDB terpisah di tabel pendaftaran (hanya untuk calon_siswa yang mulai PPDB). */
    public const ROLES = [
        'admin',
        'calon_siswa',
        'guru',
        'siswa',
        'kepsek',
    ];

    public const STATUS_AKUN = ['aktif', 'pending', 'nonaktif', 'diblokir'];

    private const SYSTEM_EMAIL_DOMAINS = [
        '@siswa.siakad.sch.id',
        '@guru.siakad.sch.id',
        '@mas.sch.id',
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'status_aktif',
        'status_akun',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status_aktif' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->hasOne(Admin::class, 'id_user', 'id_user');
    }

    public static function isSystemGeneratedEmail(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        foreach (self::SYSTEM_EMAIL_DOMAINS as $domain) {
            if (str_ends_with(strtolower($email), $domain)) {
                return true;
            }
        }

        return false;
    }

    public function displayEmail(): ?string
    {
        return self::isSystemGeneratedEmail($this->email) ? null : $this->email;
    }

    public function kepalaSekolah()
    {
        return $this->hasOne(KepalaSekolah::class, 'id_user', 'id_user');
    }

    /** @deprecated Gunakan kepalaSekolah() */
    public function kepsek()
    {
        return $this->kepalaSekolah();
    }

    public function guru()
    {
        return $this->hasOne(Guru::class, 'id_user', 'id_user');
    }

    public function siswa()
    {
        return $this->hasOne(Siswa::class, 'id_user', 'id_user');
    }

    public function pendaftaran()
    {
        return $this->hasOne(Pendaftaran::class, 'id_user', 'id_user');
    }

    public function isAkunAktif(): bool
    {
        return (bool) $this->status_aktif
            && ($this->status_akun === null || $this->status_akun === 'aktif');
    }

    /** Apakah user sudah punya baris pendaftaran PPDB (bukan sekadar akun login). */
    public function hasPpdbRegistration(): bool
    {
        if ($this->role !== 'calon_siswa') {
            return false;
        }

        return $this->relationLoaded('pendaftaran')
            ? $this->pendaftaran !== null
            : $this->pendaftaran()->exists();
    }

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role', 'key');
    }

    /** Relasi profil yang perlu di-eager-load sesuai role. */
    public static function profileRelationsForRole(?string $role): array
    {
        return match ($role) {
            'admin' => ['admin'],
            'kepsek' => ['kepalaSekolah', 'guru'],
            'guru' => ['guru'],
            'siswa' => ['siswa.kelas.waliKelas'],
            'calon_siswa' => ['pendaftaran'],
            default => [],
        };
    }

    public function loadProfileRelations(): self
    {
        $relations = self::profileRelationsForRole($this->role);
        if ($relations !== []) {
            $this->load($relations);
        }

        return $this;
    }

    /**
     * Profil aktif sesuai role (bukan relasi generik).
     * - calon_siswa -> pendaftaran
     * - siswa -> siswa
     */
    public function resolveProfile(): ?Model
    {
        return match ($this->role) {
            'admin' => $this->admin,
            'kepsek' => $this->kepalaSekolah ?: $this->guru,
            'guru' => $this->guru,
            'siswa' => $this->siswa,
            'calon_siswa' => $this->pendaftaran,
            default => null,
        };
    }

    public function expectedProfileRelation(): ?string
    {
        return match ($this->role) {
            'admin' => 'admin',
            'kepsek' => 'kepalaSekolah',
            'guru' => 'guru',
            'siswa' => 'siswa',
            'calon_siswa' => 'pendaftaran',
            default => null,
        };
    }

    public function hasExpectedProfile(): bool
    {
        // Calon siswa boleh login tanpa data PPDB; pendaftaran dibuat saat POST /ppdb/start.
        if ($this->role === 'calon_siswa') {
            return true;
        }

        if ($this->role === 'kepsek') {
            if ($this->relationLoaded('kepalaSekolah') || $this->relationLoaded('guru')) {
                return $this->kepalaSekolah !== null || $this->guru !== null;
            }

            return $this->kepalaSekolah()->exists() || $this->guru()->exists();
        }

        $relation = $this->expectedProfileRelation();
        if ($relation === null) {
            return true;
        }

        return $this->relationLoaded($relation)
            ? $this->resolveProfile() !== null
            : $this->{$relation}()->exists();
    }
}
