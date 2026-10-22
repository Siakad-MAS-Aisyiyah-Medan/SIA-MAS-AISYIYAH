<?php

use App\Http\Controllers\LaporanController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum', 'active',
    'permission:view_dashboard_kepsek,view_data_guru,view_data_siswa,view_data_kelas,view_mapel,view_data_ppdb,view_transkrip_murid',
])->group(function () {
    Route::get('/kepsek/laporan', [LaporanController::class, 'index']);
});
