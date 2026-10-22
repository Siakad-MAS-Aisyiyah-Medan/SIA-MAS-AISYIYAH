<?php

use App\Http\Controllers\AkunController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BiodataController;
use App\Http\Controllers\FotoProfilController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register-calon-siswa', [AuthController::class, 'registerCalonSiswa']);
    /** @deprecated */
    Route::post('/register', [AuthController::class, 'register']);
});

/** Legacy paths tanpa prefix auth */
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::prefix('auth')->middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/akun/profile', [AkunController::class, 'updateProfile']);
    Route::put('/biodata/profile', [BiodataController::class, 'update']);
    Route::post('/biodata/foto', [FotoProfilController::class, 'upload']);
    Route::delete('/biodata/foto', [FotoProfilController::class, 'delete']);
});
