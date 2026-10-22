<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\NilaiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'permission:view_absensi_pribadi'])->group(function () {
    Route::get('/siswa/absensi', [AbsensiController::class, 'siswaIndex']);
});

Route::middleware(['auth:sanctum', 'active', 'permission:view_transkrip_pribadi,view_nilai_pribadi'])->group(function () {
    Route::get('/siswa/nilai', [NilaiController::class, 'siswaIndex']);
    Route::get('/siswa/nilai/raport', [NilaiController::class, 'siswaRaport']);
});
