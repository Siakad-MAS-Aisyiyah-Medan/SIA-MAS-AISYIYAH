<?php

use App\Http\Controllers\BerkasController;
use App\Http\Controllers\PendaftaranController;
use App\Http\Controllers\PpdbSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PPDB API Routes
|--------------------------------------------------------------------------
| Public:
|   GET  /api/ppdb/info
| Auth (calon_siswa):
|   GET  /api/ppdb/my-registration
|   POST /api/ppdb/start
|   PUT  /api/ppdb/step/*
|   POST /api/ppdb/submit
|   GET  /api/ppdb/status
*/

Route::get('/ppdb/info', [PendaftaranController::class, 'publicInfo']);
Route::get('/ppdb/brosur/download', [PendaftaranController::class, 'downloadBrosur']);
Route::get('/ppdb/documents/{pendaftaran}/{jenis}', [BerkasController::class, 'show'])
    ->middleware('signed')->name('ppdb.documents.show');

Route::middleware([
    'auth:sanctum', 'active',
    'permission:view_status_pendaftaran,view_dashboard_siswa',
])->prefix('ppdb')->group(function () {
    Route::get('/my-registration', [PendaftaranController::class, 'myRegistration']);
    Route::get('/status', [PendaftaranController::class, 'status']);
});

Route::middleware([
    'auth:sanctum', 'active',
    'permission:manage_pendaftaran_pribadi,manage_formulir_pendaftaran,manage_berkas_pendaftaran,submit_pendaftaran,view_status_pendaftaran',
])->prefix('ppdb')->group(function () {
    Route::post('/start', [PendaftaranController::class, 'start']);
    Route::put('/step/keterangan-pribadi', [PendaftaranController::class, 'saveKeteranganPribadi']);
    Route::put('/step/kesehatan', [PendaftaranController::class, 'saveKesehatan']);
    Route::put('/step/pendidikan-asal', [PendaftaranController::class, 'savePendidikanAsal']);
    Route::put('/step/orang-tua-wali', [PendaftaranController::class, 'saveOrangTuaWali']);
    Route::put('/step/kepribadian', [PendaftaranController::class, 'saveKepribadian']);
    Route::put('/step/dokumen', [PendaftaranController::class, 'saveDokumen']);
    Route::post('/submit', [PendaftaranController::class, 'submit']);

    Route::get('/berkas', [BerkasController::class, 'index']);
    Route::post('/berkas', [BerkasController::class, 'store']);
    Route::delete('/berkas/{jenis}', [BerkasController::class, 'destroy']);

    /** Legacy endpoints — tetap tersedia sementara */
    Route::get('/me', [PendaftaranController::class, 'calonPpdbMe']);
    Route::post('/formulir', [PendaftaranController::class, 'calonPpdbSaveFormulir']);
    Route::get('/bukti', [PendaftaranController::class, 'calonPpdbBukti']);
});

Route::middleware(['auth:sanctum', 'active', 'permission:manage_ppdb'])->prefix('admin/ppdb')->group(function () {
    Route::get('/settings', [PpdbSettingController::class, 'getSettings']);
    Route::post('/settings', [PpdbSettingController::class, 'updateSettings']);
});

Route::middleware(['auth:sanctum', 'active', 'permission:manage_ppdb,view_data_ppdb'])->prefix('admin/ppdb')->group(function () {
    Route::get('/stats', [PendaftaranController::class, 'adminPpdbStats']);
    Route::get('/', [PendaftaranController::class, 'adminPpdbIndex']);
    Route::get('/{id}', [PendaftaranController::class, 'adminPpdbShow']);
});

Route::middleware(['auth:sanctum', 'active', 'permission:manage_ppdb'])->prefix('admin/ppdb')->group(function () {
    Route::post('/{id}/verifikasi', [PendaftaranController::class, 'adminPpdbVerifikasi']);
    Route::post('/{id}/revisi', [PendaftaranController::class, 'adminPpdbRevisi']);
    Route::post('/{id}/terima', [PendaftaranController::class, 'adminPpdbTerima']);
    Route::post('/{id}/tolak', [PendaftaranController::class, 'adminPpdbTolak']);
    Route::post('/{id}/jadikan-murid', [PendaftaranController::class, 'adminPpdbJadikanMurid']);
});
