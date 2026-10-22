<?php

namespace App\Imports;

use App\Models\Guru;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GuruImport
{
    public function import($rows)
    {
        DB::beginTransaction();

        try {
            // Kita mengubah cara mendapatkan indeks karena index FastExcel mulai dari 0 untuk row pertama isi data (baris 2)
            $rowIndex = 2;
            foreach ($rows as $row) {
                // Pastikan key array lowercase agar sesuai dengan kode
                $row = array_change_key_case($row, CASE_LOWER);

                // Lewati baris kosong atau jika diisi strip (-)
                if (
                    empty(trim($row['nip'] ?? '')) || trim($row['nip']) === '-' ||
                    empty(trim($row['nama'] ?? '')) || trim($row['nama']) === '-'
                ) {
                    $rowIndex++;

                    continue;
                }

                $nip = $row['nip'];
                if (! in_array(strtoupper(trim((string) ($row['lp'] ?? ''))), ['L', 'P'], true)) {
                    throw new Exception('Baris '.$rowIndex.': Kolom lp wajib L atau P.');
                }
                $password = (string) ($row['password'] ?? '');
                if (strlen($password) < 8) {
                    throw new Exception('Baris '.$rowIndex.': Kolom password wajib diisi minimal 8 karakter.');
                }

                // Cek apakah NIP sudah ada
                if (Guru::where('nip', $nip)->exists()) {
                    throw new Exception('Baris '.$rowIndex.": NIP/NUPTK ($nip) sudah terdaftar di sistem.");
                }

                if (User::where('username', $nip)->exists()) {
                    throw new Exception('Baris '.$rowIndex.": Username/NIP ($nip) sudah terdaftar di tabel pengguna.");
                }

                // Buat User
                $user = User::create([
                    'name' => $row['nama'],
                    'username' => $nip,
                    'email' => null,
                    'password' => Hash::make($password),
                    'role' => 'guru',
                    'status_aktif' => true,
                    'status_akun' => 'aktif',
                ]);

                // Buat Guru
                Guru::create([
                    'id_user' => $user->id_user,
                    'nip' => $nip,
                    'nama_guru' => $row['nama'],
                    'jenis_kelamin' => strtoupper(trim((string) $row['lp'])) === 'P' ? 'P' : 'L',
                    'agama' => $row['agama'] ?? 'Islam',
                    'alamat' => $row['alamat'] ?? '-',
                    'no_hp' => $row['no_hp'] ?? '-',
                    'status' => 'aktif',
                ]);

                $rowIndex++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
