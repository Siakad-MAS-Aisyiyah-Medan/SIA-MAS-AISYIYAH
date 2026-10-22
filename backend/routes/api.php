<?php

require __DIR__.'/api/auth.php';
require __DIR__.'/api/ppdb.php';
require __DIR__.'/api/admin.php';
require __DIR__.'/api/guru.php';
require __DIR__.'/api/siswa.php';
require __DIR__.'/api/kepsek.php';
require __DIR__.'/api/calon-siswa.php';

// Route Publik
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\ProfilSekolahController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('/pengumuman', [PengumumanController::class, 'publicIndex']);
    Route::get('/pengumuman/{id}', [PengumumanController::class, 'publicShow']);
    Route::get('/profil-sekolah', [ProfilSekolahController::class, 'show']);
});
