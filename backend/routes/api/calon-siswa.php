<?php

use App\Http\Controllers\PendaftaranController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'permission:manage_pendaftaran_pribadi,manage_formulir_pendaftaran,submit_pendaftaran,view_status_pendaftaran'])->group(function () {
    Route::get('/pendaftaran', [PendaftaranController::class, 'calonShow']);
    Route::put('/pendaftaran', [PendaftaranController::class, 'calonUpdate']);
    Route::post('/pendaftaran/submit', [PendaftaranController::class, 'calonSubmit']);
});
