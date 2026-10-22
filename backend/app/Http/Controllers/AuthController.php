<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\PermissionService;
use App\Utils\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /** @deprecated Gunakan registerCalonSiswa — alias kompatibilitas */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'nisn' => 'required|string|min:10|max:20|unique:users,username',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $data = $validated;
        $user = $this->authService->registerCalonSiswa([
            'name' => $data['nama'],
            'email' => $data['email'],
            'username' => $data['nisn'],
            'password' => $data['password'],
        ]);

        return ApiResponse::success(
            (new UserResource($user))->resolve(),
            'Akun berhasil dibuat. Login lalu mulai formulir PPDB dari dashboard jika ingin mendaftar.',
            201
        );
    }

    /**
     * Registrasi akun saja (tabel users). Tidak membuat draft pendaftaran PPDB.
     */
    public function registerCalonSiswa(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'username'),
            ],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $user = $this->authService->registerCalonSiswa($validated);

        return ApiResponse::success(
            [
                'user' => (new UserResource($user))->resolve(),
                'next_steps' => [
                    'login' => '/login-calon-murid',
                    'dashboard' => '/calon-murid/dashboard',
                    'start_ppdb' => 'POST /api/ppdb/start (setelah login, opsional)',
                ],
            ],
            'Akun berhasil dibuat. Login untuk mengakses dashboard. Data PPDB dibuat saat Anda memulai formulir pendaftaran.',
            201
        );
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required|string',
            'username' => 'sometimes|string|max:255',
            'role' => 'nullable|in:admin,kepsek,guru,siswa,calon_siswa',
        ]);

        $result = $this->authService->login(
            $validated['login'],
            $validated['password'],
            $validated['role'] ?? null
        );

        if (isset($result['error'])) {
            return ApiResponse::error($result['error'], $result['code']);
        }

        return ApiResponse::success($result['data'], 'Login Berhasil');
    }

    public function logout(Request $request)
    {
        app(AuditLogService::class)->logAdminAction('auth.logout', $request->user());
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logout berhasil');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->loadProfileRelations();

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve(),
            'profile' => $user->resolveProfile(),
            'permissions' => app(PermissionService::class)->permissionsForUser($user),
            'menus' => app(PermissionService::class)->menusForUser($user),
        ]);
    }
}
