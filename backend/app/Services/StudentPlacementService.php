<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Validation\ValidationException;

class StudentPlacementService
{
    /** Call inside the transaction that saves the student. */
    public function assertAvailable(?int $classId, ?int $studentUserId = null): void
    {
        if (! $classId) {
            return;
        }
        $kelas = Kelas::lockForUpdate()->findOrFail($classId);
        if (strtolower($kelas->status ?? 'aktif') !== 'aktif') {
            throw ValidationException::withMessages(['id_kelas' => 'Kelas sudah nonaktif.']);
        }
        $count = Siswa::aktif()->where('id_kelas', $classId)
            ->when($studentUserId, fn ($q) => $q->where('id_user', '!=', $studentUserId))->count();
        if ($kelas->kapasitas_maksimal && $count >= $kelas->kapasitas_maksimal) {
            throw ValidationException::withMessages(['id_kelas' => 'Kapasitas kelas sudah penuh.']);
        }
    }
}
