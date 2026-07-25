<?php

namespace App\Imports;

use App\Models\Siswa;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SiswaImport
{
    protected $idKelas;

    public function __construct($idKelas)
    {
        $this->idKelas = $idKelas;
    }

    public function import($rows)
    {
        DB::beginTransaction();

        try {
            $rowIndex = 2;
            foreach ($rows as $row) {
                $row = $this->normalizeHeaders($row);

                // Lewati baris kosong atau jika diisi strip (-)
                if (
                    empty(trim($row['nis'] ?? '')) || trim($row['nis']) === '-' ||
                    empty(trim($row['nama'] ?? '')) || trim($row['nama']) === '-'
                ) {
                    $rowIndex++;

                    continue;
                }

                $nis = $row['nis'];
                $nisn = $row['nisn'] ?? '-';
                $noHp = $this->firstFilledValue($row, [
                    'no_hp',
                    'no_hp_wali',
                    'nomor_hp',
                    'nomor_hp_wali',
                    'no_hp_ortu',
                    'nomor_hp_ortu',
                    'no_telepon',
                    'nomor_telepon',
                    'hp',
                ]);

                if ($noHp === null) {
                    throw new Exception(
                        'Baris '.$rowIndex.': Nomor HP tidak ditemukan. Gunakan header no_hp atau no_hp_wali dan pastikan nilainya terisi.'
                    );
                }

                // Cek apakah NIS atau NISN sudah ada
                $existingSiswa = Siswa::where('nis', $nis)->orWhere('nisn', $nisn)->first();
                if ($existingSiswa) {
                    $sameStudent = (string) $existingSiswa->nis === (string) $nis
                        && ($nisn === '-' || (string) $existingSiswa->nisn === (string) $nisn);

                    if ($sameStudent && $this->isPlaceholderPhone($existingSiswa->no_hp)) {
                        $existingSiswa->update([
                            'no_hp' => $noHp,
                            'no_hp_wali' => $this->isPlaceholderPhone($existingSiswa->no_hp_wali)
                                ? $noHp
                                : $existingSiswa->no_hp_wali,
                        ]);
                        $rowIndex++;

                        continue;
                    }

                    throw new Exception('Baris '.$rowIndex.": NIS ($nis) atau NISN ($nisn) sudah terdaftar di sistem.");
                }

                if (User::where('username', $nis)->exists()) {
                    throw new Exception('Baris '.$rowIndex.": Username/NIS ($nis) sudah terdaftar di tabel pengguna.");
                }

                // Buat User
                $user = User::create([
                    'name' => $row['nama'],
                    'username' => $nis,
                    'email' => null,
                    'password' => Hash::make('admin123'),
                    'role' => 'siswa',
                    'status_aktif' => true,
                    'status_akun' => 'aktif',
                ]);

                // Format Tanggal Lahir (FastExcel / OpenSpout biasanya me-return string Y-m-d atau DateTime)
                $tglLahir = '2000-01-01';
                if (! empty($row['tgl_lahir'])) {
                    if ($row['tgl_lahir'] instanceof \DateTimeInterface) {
                        $tglLahir = $row['tgl_lahir']->format('Y-m-d');
                    } else {
                        // Jika formatnya string, kita asumsikan sudah valid dan coba parse
                        $parsed = strtotime($row['tgl_lahir']);
                        if ($parsed !== false) {
                            $tglLahir = date('Y-m-d', $parsed);
                        }
                    }
                }

                // Buat Siswa
                Siswa::create([
                    'id_user' => $user->id_user,
                    'id_kelas' => $this->idKelas,
                    'nisn' => $nisn,
                    'nis' => $nis,
                    'nama_siswa' => $row['nama'],
                    'tempat_lahir' => $row['tempat_lahir'] ?? '-',
                    'tgl_lahir' => $tglLahir,
                    'jenis_kelamin' => isset($row['lp']) && strtoupper($row['lp']) == 'P' ? 'P' : 'L',
                    'agama' => $row['agama'] ?? 'Islam',
                    'alamat' => $row['alamat'] ?? '-',
                    'nama_wali' => $row['nama_wali'] ?? '-',
                    'no_hp_wali' => $row['no_hp_wali'] ?? $noHp,
                    'no_hp' => $noHp,
                    'status_siswa' => Siswa::STATUS_AKTIF,
                    'status_diubah_pada' => now(),
                ]);
                $rowIndex++;
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Samakan variasi header Excel seperti "No. HP", "NO HP", dan spasi tersembunyi.
     */
    private function normalizeHeaders(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $key = preg_replace('/^\x{FEFF}/u', '', trim((string) $key));
            $key = mb_strtolower($key);
            $key = preg_replace('/[^\pL\pN]+/u', '_', $key);
            $normalized[trim($key, '_')] = $value;
        }

        return $normalized;
    }

    private function firstFilledValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        return null;
    }

    private function isPlaceholderPhone(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '' || trim((string) $value) === '-';
    }
}
