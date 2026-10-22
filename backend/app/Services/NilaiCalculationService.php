<?php

namespace App\Services;

class NilaiCalculationService
{
    public function calculate(array $input): array
    {
        $tugas = array_key_exists('nilai_tugas', $input) && $input['nilai_tugas'] !== null && $input['nilai_tugas'] !== ''
            ? (int) $input['nilai_tugas']
            : null;
        $uts = array_key_exists('nilai_uts', $input) && $input['nilai_uts'] !== null && $input['nilai_uts'] !== ''
            ? (int) $input['nilai_uts']
            : null;
        $uas = array_key_exists('nilai_uas', $input) && $input['nilai_uas'] !== null && $input['nilai_uas'] !== ''
            ? (int) $input['nilai_uas']
            : null;
        $praktik = array_key_exists('nilai_praktik', $input) && $input['nilai_praktik'] !== null && $input['nilai_praktik'] !== ''
            ? (int) $input['nilai_praktik']
            : null;
        $sikap = array_key_exists('nilai_sikap', $input) && $input['nilai_sikap'] !== null && $input['nilai_sikap'] !== ''
            ? (int) $input['nilai_sikap']
            : null;

        foreach ([$tugas, $uts, $uas, $praktik, $sikap] as $n) {
            if ($n !== null) {
                $this->assertRange($n);
            }
        }

        $calcTugas = $tugas ?? 0;
        $calcUts = $uts ?? 0;
        $calcUas = $uas ?? 0;

        if ($praktik !== null) {
            $weights = config('nilai.weights.with_praktik');
            $nilaiAkhir = (int) round(
                $calcTugas * $weights['tugas']
                + $calcUts * $weights['uts']
                + $calcUas * $weights['uas']
                + $praktik * $weights['praktik']
            );
        } else {
            $weights = config('nilai.weights.default');
            $nilaiAkhir = (int) round(
                $calcTugas * $weights['tugas']
                + $calcUts * $weights['uts']
                + $calcUas * $weights['uas']
            );
        }

        if ($tugas === null || $uts === null || $uas === null) {
            $nilaiAkhir = null;
        }

        return [
            'nilai_tugas' => $tugas,
            'nilai_uts' => $uts,
            'nilai_uas' => $uas,
            'nilai_praktik' => $praktik,
            'nilai_sikap' => $sikap,
            'nilai_akhir' => $nilaiAkhir,
            'nilai_angka' => $nilaiAkhir,
            'predikat' => $nilaiAkhir === null ? null : $this->predikat($nilaiAkhir),
        ];
    }

    public function predikat(int $nilai): string
    {
        foreach (config('nilai.predikat', []) as $row) {
            if ($nilai >= $row['min']) {
                return $row['grade'];
            }
        }

        return 'E';
    }

    protected function assertRange(int $nilai): void
    {
        if ($nilai < 0 || $nilai > 100) {
            throw new \InvalidArgumentException('Nilai harus antara 0 dan 100.');
        }
    }
}
