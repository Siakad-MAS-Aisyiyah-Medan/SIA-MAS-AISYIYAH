<?php

namespace App\Http\Middleware;

use App\Utils\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isAkunAktif()) {
            $user->tokens()->delete();

            return ApiResponse::error('Akun dinonaktifkan. Silakan hubungi administrator.', 401);
        }
        if ($user?->must_change_password && ! $request->is('api/me', 'api/auth/me', 'api/logout', 'api/auth/logout', 'api/akun/profile')) {
            return response()->json([
                'success' => false,
                'message' => 'Ganti password bawaan melalui Pengaturan Akun sebelum melanjutkan.',
                'code' => 'PASSWORD_CHANGE_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
