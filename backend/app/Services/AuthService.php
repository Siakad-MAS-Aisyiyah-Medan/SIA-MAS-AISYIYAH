<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Account\AccountRegistrationService;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private PermissionService $permissions,
        private AccountRegistrationService $accountRegistration,
    ) {}

    /**
     * Registrasi akun calon siswa — delegasi ke layer Account (tanpa pendaftaran PPDB).
     */
    public function registerCalonSiswa(array $data): User
    {
        return $this->accountRegistration->registerCalonSiswa($data);
    }

    /**
     * @deprecated Gunakan registerCalonSiswa — tidak lagi membuat pendaftaran otomatis.
     */
    public function registerCalonSiswaLegacy(array $data): User
    {
        return $this->registerCalonSiswa([
            'name' => $data['nama'] ?? $data['nama_lengkap'] ?? $data['name'] ?? '',
            'username' => $data['nisn'] ?? $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    /**
     * Login dengan username/NISN atau email + password.
     *
     * @return array{data: array}|array{error: string, code: int}
     */
    public function login(string $login, string $password, ?string $role = null): array
    {
        $login = trim($login);
        $loginCandidates = $this->loginLookupCandidates($login);

        $user = User::query()
            ->with(['admin', 'kepalaSekolah', 'guru', 'siswa'])
            ->when($role, fn ($query) => $query->where('role', $role))
            ->where(function ($query) use ($login, $loginCandidates) {
                $query->whereIn('username', $loginCandidates)
                    ->orWhere('email', $login)
                    ->orWhereHas('admin', function ($adminQuery) use ($loginCandidates) {
                        $adminQuery->whereIn('nip', $loginCandidates);
                    })
                    ->orWhereHas('kepalaSekolah', function ($kepsekQuery) use ($loginCandidates) {
                        $kepsekQuery->whereIn('nip', $loginCandidates);
                    })
                    ->orWhereHas('guru', function ($guruQuery) use ($loginCandidates) {
                        $guruQuery->whereIn('nip', $loginCandidates);
                    })
                    ->orWhereHas('siswa', function ($siswaQuery) use ($loginCandidates) {
                        $siswaQuery->whereIn('nisn', $loginCandidates)
                            ->orWhereIn('nis', $loginCandidates);
                    });
            })
            ->get()
            ->sortBy(fn (User $candidate) => $this->loginMatchPriority($candidate, $login, $loginCandidates, $role))
            ->first();

        if (! $user) {
            return ['error' => 'Identitas login atau password salah.', 'code' => 401];
        }

        if (! Hash::check($password, $user->password)) {
            return ['error' => 'Identitas login atau password salah.', 'code' => 401];
        }
        if (in_array($password, ['password', 'admin123', '12345678'], true)) {
            $user->forceFill(['must_change_password' => true])->save();
        }

        $statusError = $this->accountStatusError($user);
        if ($statusError !== null) {
            return $statusError;
        }

        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::create([
            'id_user' => $user->id_user,
            'action' => 'auth.login',
            'subject_type' => class_basename($user),
            'subject_id' => $user->id_user,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);

        $user->loadProfileRelations();
        $profile = $user->resolveProfile();
        $token = $user->createToken('auth_token')->plainTextToken;
        $permissions = $this->permissions->permissionsForUser($user);
        $menus = $this->permissions->menusForUser($user);

        $redirectPath = $this->permissions->redirectPathForRole($user->role);
        if ($user->role === 'calon_siswa') {
            $redirectPath = '/calon-murid/dashboard';
        }

        return [
            'data' => [
                'user' => (new UserResource($user))->resolve(),
                'profile' => $profile,
                'has_ppdb_registration' => $user->role === 'calon_siswa'
                    ? $user->pendaftaran()->exists()
                    : null,
                'token' => $token,
                'token_type' => 'Bearer',
                'permissions' => $permissions,
                'menus' => $menus,
                'redirect_path' => $redirectPath,
            ],
        ];
    }

    private function loginLookupCandidates(string $login): array
    {
        $candidates = [$login];
        $compact = preg_replace('/[\s\-.]+/', '', $login);

        if ($compact !== null && $compact !== '') {
            $candidates[] = $compact;

            if (ctype_digit($compact)) {
                $withoutLeadingZero = ltrim($compact, '0');
                $withoutLeadingZero = $withoutLeadingZero === '' ? '0' : $withoutLeadingZero;

                $candidates[] = $withoutLeadingZero;
                $candidates[] = str_pad($withoutLeadingZero, 10, '0', STR_PAD_LEFT);
            }
        }

        return array_values(array_unique($candidates));
    }

    private function loginMatchPriority(User $user, string $login, array $loginCandidates, ?string $role): int
    {
        $matches = fn ($value): bool => $value !== null && in_array((string) $value, $loginCandidates, true);

        return match (true) {
            (string) $user->email === $login => 0,
            (string) $user->username === $login => 1,
            $matches($user->username) => 2,
            $matches($user->siswa?->nisn), $matches($user->siswa?->nis) => 3,
            $matches($user->guru?->nip) => 4,
            $matches($user->kepalaSekolah?->nip) => 5,
            $matches($user->admin?->nip) => 6,
            default => 99,
        };
    }

    /**
     * @return array{error: string, code: int}|null
     */
    private function accountStatusError(User $user): ?array
    {
        if (! $user->isAkunAktif()) {
            return ['error' => 'Akun dinonaktifkan', 'code' => 403];
        }
        $status = $user->status_akun ?? (($user->status_aktif ?? true) ? 'aktif' : 'nonaktif');

        return match ($status) {
            'aktif' => null,
            'pending' => ['error' => 'Akun dinonaktifkan', 'code' => 403],
            'nonaktif' => ['error' => 'Akun dinonaktifkan', 'code' => 403],
            'diblokir' => ['error' => 'Akun dinonaktifkan', 'code' => 403],
            default => $user->isAkunAktif()
                ? null
                : ['error' => 'Akun dinonaktifkan', 'code' => 403],
        };
    }
}
