<?php

use App\Http\Controllers\AkunController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\MapelController;
use App\Http\Controllers\MuridController;
use App\Http\Controllers\NilaiController;
use App\Http\Controllers\PendaftaranController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\ProfilSekolahController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TahunAjaranController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:view_dashboard_admin,view_dashboard_kepsek,view_dashboard_guru');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:manage_all');

    Route::get('/murid/stats', [MuridController::class, 'stats'])->middleware('permission:manage_murid,view_data_siswa');
    Route::get('/murid', [MuridController::class, 'index'])->middleware('permission:manage_murid,view_data_siswa');
    Route::middleware('permission:manage_murid')->group(function () {
        Route::post('/murid', [MuridController::class, 'store']);
        Route::put('/murid/{id}', [MuridController::class, 'update']);
        Route::post('/murid/{id}/enroll', [MuridController::class, 'enroll']);
        Route::delete('/murid/{id}', [MuridController::class, 'destroy']);
        Route::post('/murid/import', [ImportController::class, 'importSiswa']);
        Route::get('/murid/template-import', [ImportController::class, 'downloadTemplateSiswa']);
    });

    // Legacy PPDB routes (redirect ke modul baru - tetap untuk kompatibilitas)
    Route::middleware('permission:manage_ppdb')->group(function () {
        Route::get('/ppdb-legacy', [PendaftaranController::class, 'adminPpdbIndex']);
        Route::patch('/ppdb/{id}/status', [PendaftaranController::class, 'updateStatus']);
    });

    Route::get('/guru', [GuruController::class, 'index'])->middleware('permission:manage_guru,view_data_guru');
    Route::middleware('permission:manage_guru')->group(function () {
        Route::post('/guru', [GuruController::class, 'store']);
        Route::put('/guru/{id}', [GuruController::class, 'update']);
        Route::delete('/guru/{id}', [GuruController::class, 'destroy']);
        Route::post('/guru/import', [ImportController::class, 'importGuru']);
        Route::get('/guru/template-import', [ImportController::class, 'downloadTemplateGuru']);
    });

    Route::get('/kelas', [KelasController::class, 'index'])->middleware('permission:manage_kelas,view_data_kelas,view_kelas_diajar,view_kelas_pribadi');
    Route::get('/kelas/stats', [KelasController::class, 'stats'])->middleware('permission:manage_kelas,view_data_kelas,view_kelas_diajar,view_kelas_pribadi');
    Route::middleware('permission:manage_kelas')->group(function () {
        Route::post('/kelas', [KelasController::class, 'store']);
        Route::put('/kelas/{id}', [KelasController::class, 'update']);
        Route::delete('/kelas/{id}', [KelasController::class, 'destroy']);
    });

    Route::get('/mapel', [MapelController::class, 'index'])->middleware('permission:manage_mapel,view_mapel,view_mapel_diampu');
    Route::middleware('permission:manage_mapel')->group(function () {
        Route::post('/mapel', [MapelController::class, 'store']);
        Route::put('/mapel/{id}', [MapelController::class, 'update']);
        Route::delete('/mapel/{id}', [MapelController::class, 'destroy']);
    });

    Route::get('/pengumuman', [PengumumanController::class, 'adminIndex'])->middleware('permission:manage_pengumuman,view_pengumuman');
    Route::get('/pengumuman/{id}', [PengumumanController::class, 'adminShow'])->middleware('permission:manage_pengumuman,view_pengumuman');
    Route::middleware('permission:manage_pengumuman')->group(function () {
        Route::post('/pengumuman', [PengumumanController::class, 'adminStore']);
        Route::put('/pengumuman/{id}', [PengumumanController::class, 'adminUpdate']);
        Route::delete('/pengumuman/{id}', [PengumumanController::class, 'adminDestroy']);
    });

    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/akun', [AkunController::class, 'index']);
        Route::post('/akun', [AkunController::class, 'store']);
        Route::put('/akun/{id}', [AkunController::class, 'update']);
        Route::delete('/akun/{id}', [AkunController::class, 'destroy']);
    });

    Route::middleware('permission:view_transkrip_murid')->group(function () {
        Route::get('/admin/transkrip-akademik/{idUserSiswa}/raport', [NilaiController::class, 'adminStudentRaport']);
    });

    Route::get('/tahun-ajaran', [TahunAjaranController::class, 'index']);
    Route::middleware('permission:manage_tahun_ajaran')->group(function () {
        Route::apiResource('/tahun-ajaran', TahunAjaranController::class)->except(['index']);
        Route::put('/tahun-ajaran/{id}/activate', [TahunAjaranController::class, 'activate']);
    });

    Route::get('/profil-sekolah', [ProfilSekolahController::class, 'show'])->middleware('permission:manage_profil_sekolah,view_dashboard_kepsek');

    Route::middleware('permission:manage_profil_sekolah')->group(function () {
        Route::put('/profil-sekolah', [ProfilSekolahController::class, 'update']);
    });

    Route::middleware('permission:manage_all')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings/{key}', [SettingsController::class, 'update']);
        Route::post('/settings/bulk', [SettingsController::class, 'bulkUpdate']);
    });
});
