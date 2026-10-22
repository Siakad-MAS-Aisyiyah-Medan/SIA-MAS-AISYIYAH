<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidatePagination;
use App\Utils\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

$builder = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'active' => EnsureActiveAccount::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
            ValidatePagination::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error(
                    'Validasi gagal',
                    422,
                    $e->errors()
                );
            }
        });

        $exceptions->render(function (Throwable $e, $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return null;
            }
            if ($e instanceof InvalidArgumentException) {
                return ApiResponse::error($e->getMessage(), 422);
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            if ($e instanceof RouteNotFoundException && str_contains($e->getMessage(), '[login]')) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = config('app.debug') ? $e->getMessage() : match ($status) {
                    403 => 'Akses ditolak.',
                    404 => 'Resource tidak ditemukan.',
                    429 => 'Terlalu banyak permintaan. Coba lagi nanti.',
                    default => 'Permintaan tidak dapat diproses.',
                };

                return ApiResponse::error($message, $status);
            }

            report($e);

            $message = config('app.debug')
                ? $e->getMessage()
                : 'Terjadi kesalahan pada server.';

            return ApiResponse::error($message, 500);
        });
    });

$app = $builder->create();

foreach ([
    $app->storagePath('framework/cache/data'),
    $app->storagePath('framework/sessions'),
    $app->storagePath('framework/views'),
    $app->storagePath('logs'),
] as $runtimePath) {
    if (! is_dir($runtimePath)) {
        @mkdir($runtimePath, 0755, true);
    }
}

return $app;
