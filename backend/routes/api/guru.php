<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\NilaiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:view_dashboard_guru,view_murid_diajar,view_kelas_diajar,view_mapel_diampu'])->group(function () {
    Route::get('/guru/jadwal', [JadwalController::class, 'guruIndex']);
    Route::get('/guru/murid-diajar', [JadwalController::class, 'guruMuridDiajar']);
});

Route::middleware(['auth:sanctum', 'permission:manage_absensi_siswa'])->group(function () {
    Route::get('/guru/absensi/siswa/form', [AbsensiController::class, 'guruFormData']);
    Route::post('/guru/absensi/siswa/bulk', [AbsensiController::class, 'guruBulkStore']);
    Route::get('/guru/absensi/siswa/rekap', [AbsensiController::class, 'guruRekapSiswa']);
    Route::get('/guru/absensi/siswa/history', [AbsensiController::class, 'guruHistoryAbsensi']);
    Route::delete('/guru/absensi/siswa/meeting', [AbsensiController::class, 'guruDeleteMeeting']);
});

Route::middleware(['auth:sanctum', 'permission:manage_nilai_siswa'])->group(function () {
    Route::get('/guru/nilai/form', [NilaiController::class, 'guruFormData']);
    Route::put('/guru/nilai/skema', [NilaiController::class, 'guruSaveScheme']);
    Route::post('/guru/nilai/komponen/bulk', [NilaiController::class, 'guruBulkStoreComponent']);
    Route::post('/guru/nilai/bulk', [NilaiController::class, 'guruBulkStore']);
});

