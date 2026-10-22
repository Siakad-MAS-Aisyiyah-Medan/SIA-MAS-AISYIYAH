<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\User;
use App\Services\GuruService;
use App\Services\MuridService;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AkunController extends Controller
{
    use AuditsAdminActions;

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['admin', 'guru', 'siswa', 'kepalaSekolah'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'aktif') {
                $query->where(function ($q) {
                    $q->where('status_akun', 'aktif')->orWhere('status_aktif', true);
                });
            } elseif ($request->status === 'nonaktif') {
                $query->where(function ($q) {
                    $q->where('status_akun', 'nonaktif')->orWhere('status_aktif', false);
                });
            }
        }

        $paginator = $query->paginate($request->get('limit', 10));

        $users = collect($paginator->items())->map(function ($user) {
            $nipNisn = $user->username ?: '-';
            $noHp = '-';
            $realName = $user->name;

            if ($user->role === 'admin' && $user->admin) {
                $nipNisn = $user->admin->nip ?? '-';
                $noHp = $user->admin->no_hp ?? '-';
                if (empty(trim($realName))) {
                    $realName = $user->admin->nama_admin ?? '-';
                }
            } elseif ($user->role === 'guru' && $user->guru) {
                $nipNisn = $user->guru->nip ?? '-';
                $noHp = $user->guru->no_hp ?? '-';
                if (empty(trim($realName))) {
                    $realName = $user->guru->nama_guru ?? '-';
                }
            } elseif ($user->role === 'kepsek' && ($user->kepalaSekolah || $user->guru)) {
                $kepsekProfile = $user->kepalaSekolah ?: $user->guru;
                $nipNisn = $kepsekProfile->nip ?? '-';
                $noHp = $kepsekProfile->no_hp ?? '-';
                if (empty(trim($realName))) {
                    $realName = $kepsekProfile->nama_kepsek ?? $kepsekProfile->nama_guru ?? '-';
                }
            } elseif ($user->role === 'siswa' && $user->siswa) {
                $nipNisn = $user->siswa->nisn ?? '-';
                $noHp = $user->siswa->no_hp_wali ?? '-';
                if (empty(trim($realName))) {
                    $realName = $user->siswa->nama_siswa ?? '-';
                }
            } elseif ($user->role === 'calon_siswa' && $user->pendaftaran) {
                if (empty(trim($realName))) {
                    $realName = $user->pendaftaran->nama_lengkap ?? '-';
                }
            }

            return [
                'id' => $user->id_user,
                'name' => $realName ?: '-',
                'username' => $user->username,
                'email' => $user->displayEmail(),
                'role' => $user->role,
                'status' => $user->status_akun ?? ($user->status_aktif ? 'aktif' : 'nonaktif'),
                'nip_nisn' => $nipNisn,
                'no_hp' => $noHp,
            ];
        })->values()->toArray();

        $activeRoles = User::select('role')->distinct()->count('role');
        $totalAkun = User::count();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users,
                'total_akun' => $totalAkun,
                'role_aktif' => $activeRoles,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'no_hp' => 'nullable|string|max:20',
            'role' => 'required|string|in:'.implode(',', User::ROLES),
            'status' => 'nullable|string|in:aktif,nonaktif',
        ]);

        if (in_array($validated['role'], ['guru', 'siswa'], true)) {
            return ApiResponse::error('Tambahkan guru atau murid melalui menu Data Guru atau Data Murid agar biodata akademiknya lengkap.', 422);
        }

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'status_aktif' => ($validated['status'] ?? 'aktif') === 'aktif',
                'status_akun' => $validated['status'] ?? 'aktif',
            ]);
            if ($user->role === 'admin') {
                $user->admin()->create(['nip' => $user->username, 'nama_admin' => $user->name, 'no_hp' => $validated['no_hp'] ?? '-']);
            } elseif ($user->role === 'kepsek') {
                $user->kepalaSekolah()->create(['nip' => $user->username, 'nama_kepsek' => $user->name, 'no_hp' => $validated['no_hp'] ?? '-']);
            }

            return $user;
        });

        $prefix = match ($user->role) {
            'kepsek' => 'kepsek',
            'guru' => 'guru',
            'siswa' => 'murid',
            'admin' => 'admin',
            default => 'akun'
        };
        $this->auditAdmin("{$prefix}.create", $user, ['username' => $user->username]);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil ditambahkan',
            'data' => $user,
        ], 201);
    }

    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (auth()->id() == $id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 403);
        }

        if ($this->isActiveAdmin($user) && $this->activeAdminCount() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal harus ada satu akun administrator aktif.',
            ], 422);
        }

        if (in_array($user->role, ['siswa', 'calon_siswa'], true)) {
            app(MuridService::class)->deleteMurid((int) $id);

            return ApiResponse::success(null, 'Akun murid berhasil dihapus.');
        }
        if ($user->role === 'guru') {
            app(GuruService::class)->deleteGuru((int) $id);

            return ApiResponse::success(null, 'Akun guru dinonaktifkan; riwayat tetap tersimpan.');
        }
        $prefix = match ($user->role) {
            'kepsek' => 'kepsek',
            'guru' => 'guru',
            'siswa' => 'murid',
            'admin' => 'admin',
            default => 'akun'
        };
        $this->auditAdmin("{$prefix}.delete", $user, ['username' => $user->username]);
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dihapus',
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,'.$user->id_user.',id_user',
            'role' => 'required|string|in:'.implode(',', User::ROLES),
            'status' => 'nullable|string|in:aktif,nonaktif',
            'no_hp' => 'nullable|string|max:20',
        ]);

        if ($user->role === 'calon_siswa' && $validated['role'] === 'siswa') {
            return ApiResponse::error('Gunakan proses penerimaan PPDB untuk menjadikan calon murid sebagai siswa.', 422);
        }
        if ($validated['role'] !== $user->role && ($user->siswa()->exists()
            || Mapel::where('id_guru', $user->id_user)->exists()
            || Kelas::where('id_wali_kelas', $user->id_user)->exists())) {
            return ApiResponse::error('Role masih terhubung dengan data akademik. Selesaikan penugasan atau gunakan pengelolaan status murid.', 422);
        }
        $oldRole = $user->role;
        if ($user->siswa && $user->siswa->status_siswa !== 'aktif' && ($validated['status'] ?? null) === 'aktif') {
            return ApiResponse::error('Aktifkan kembali status murid melalui menu Data Murid terlebih dahulu.', 422);
        }
        if ($validated['role'] !== $oldRole && in_array($validated['role'], ['guru', 'siswa'], true)) {
            $relation = $validated['role'] === 'guru' ? 'guru' : 'siswa';
            if (! $user->{$relation}()->exists()) {
                return ApiResponse::error('Role tujuan memerlukan biodata dari menu Data Guru atau Data Murid.', 422);
            }
        }

        if (auth()->id() == $user->id_user && (
            $validated['role'] !== $user->role
            || ($request->filled('status') && $validated['status'] !== ($user->status_akun ?? ($user->status_aktif ? 'aktif' : 'nonaktif')))
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat mengubah role atau status akun Anda sendiri dari manajemen pengguna.',
            ], 403);
        }

        if ($this->wouldRemoveActiveAdmin($user, $validated['role'], $validated['status'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal harus ada satu akun administrator aktif.',
            ], 422);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'] ?? null;
        $user->role = $validated['role'];
        if ($request->filled('status')) {
            $user->status_akun = $validated['status'];
            $user->status_aktif = $validated['status'] === 'aktif';
        }

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:8']);
            $user->password = Hash::make($request->password);
        }

        $user->save();

        if (! $user->isAkunAktif() || $oldRole !== $user->role || $request->filled('password')) {
            $user->tokens()->delete();
        }
        if ($user->role === 'admin') {
            $user->admin()->firstOrCreate(['id_user' => $user->id_user], ['nip' => $user->username, 'nama_admin' => $user->name, 'no_hp' => $validated['no_hp'] ?? '-']);
        } elseif ($user->role === 'kepsek') {
            $user->kepalaSekolah()->firstOrCreate(['id_user' => $user->id_user], ['nip' => $user->username, 'nama_kepsek' => $user->name, 'no_hp' => $validated['no_hp'] ?? '-']);
        }
        $relation = $user->expectedProfileRelation();
        $nameField = ['admin' => 'nama_admin', 'kepsek' => 'nama_kepsek', 'guru' => 'nama_guru', 'siswa' => 'nama_siswa'][$user->role] ?? null;
        if ($relation && $nameField) {
            $user->{$relation}()->update([$nameField => $user->name]);
        }

        if ($request->has('no_hp')) {
            $phone = $validated['no_hp'] ?: '-';

            if ($user->role === 'admin' && $user->admin) {
                $user->admin->update(['no_hp' => $phone]);
            } elseif ($user->role === 'guru' && $user->guru) {
                $user->guru->update(['no_hp' => $phone]);
            } elseif ($user->role === 'kepsek') {
                if ($user->kepalaSekolah) {
                    $user->kepalaSekolah->update(['no_hp' => $phone]);
                }
                if ($user->guru) {
                    $user->guru->update(['no_hp' => $phone]);
                }
            } elseif ($user->role === 'siswa' && $user->siswa) {
                $user->siswa->update(['no_hp_wali' => $phone]);
            }
        }

        $prefix = match ($user->role) {
            'kepsek' => 'kepsek',
            'guru' => 'guru',
            'siswa' => 'murid',
            'admin' => 'admin',
            default => 'akun'
        };
        $this->auditAdmin("{$prefix}.update", $user, ['username' => $user->username]);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diperbarui',
            'data' => $user,
        ]);
    }

    private function wouldRemoveActiveAdmin(User $user, string $nextRole, ?string $nextStatus): bool
    {
        if (! $this->isActiveAdmin($user)) {
            return false;
        }

        $nextStatus ??= $user->status_akun ?? ($user->status_aktif ? 'aktif' : 'nonaktif');

        if ($nextRole === 'admin' && $nextStatus === 'aktif') {
            return false;
        }

        return $this->activeAdminCount() <= 1;
    }

    private function isActiveAdmin(User $user): bool
    {
        return $user->role === 'admin' && $user->isAkunAktif();
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('role', 'admin')
            ->where(function ($query) {
                $query->where('status_akun', 'aktif')
                    ->orWhere(function ($query) {
                        $query->whereNull('status_akun')
                            ->where('status_aktif', true);
                    });
            })
            ->count();
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,'.$user->id_user.',id_user',
            'current_password' => 'nullable|string',
            'new_password' => 'nullable|string|min:8|confirmed|not_in:password,admin123,12345678',
        ]);

        if ($request->filled('new_password')) {
            if (! $request->filled('current_password') || ! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password saat ini salah.',
                    'errors' => ['current_password' => ['Password saat ini tidak sesuai.']],
                ], 422);
            }
            if ($request->new_password !== $request->new_password_confirmation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konfirmasi password tidak sesuai.',
                    'errors' => ['new_password_confirmation' => ['Konfirmasi password tidak cocok.']],
                ], 422);
            }
            $user->password = Hash::make($request->new_password);
            $user->must_change_password = false;
            $token = $user->currentAccessToken();
            $user->tokens()->when($token instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $token->id))->delete();
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'] ?? null;
        $user->save();

        $relation = $user->expectedProfileRelation();
        $nameField = ['admin' => 'nama_admin', 'kepsek' => 'nama_kepsek', 'guru' => 'nama_guru', 'siswa' => 'nama_siswa'][$user->role] ?? null;
        if ($relation && $nameField) {
            $user->{$relation}()->update([$nameField => $user->name]);
        }

        $this->auditAdmin('profile.update', $user, ['username' => $user->username]);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => $user,
        ]);
    }
}
