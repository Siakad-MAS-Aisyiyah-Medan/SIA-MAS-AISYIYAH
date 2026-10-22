<?php

namespace App\Http\Controllers;

use App\Imports\GuruImport;
use App\Imports\SiswaImport;
use App\Utils\AuditsAdminActions;
use Exception;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportController extends Controller
{
    use AuditsAdminActions;

    public function importSiswa(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:2048',
            'id_kelas' => 'required|exists:kelas,id_kelas',
        ]);

        try {
            $siswaImport = new SiswaImport($request->id_kelas);
            $rows = (new FastExcel)->import($request->file('file'));
            $siswaImport->import($rows);

            $this->auditAdmin('admin.import.siswa', null, ['id_kelas' => $request->id_kelas]);

            return response()->json([
                'success' => true,
                'message' => 'Data Siswa berhasil diimport.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimport data: '.$e->getMessage(),
            ], 422);
        }
    }

    public function importGuru(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:2048',
        ]);

        try {
            $guruImport = new GuruImport;
            $rows = (new FastExcel)->import($request->file('file'));
            $guruImport->import($rows);

            $this->auditAdmin('admin.import.guru', null, []);

            return response()->json([
                'success' => true,
                'message' => 'Data Guru berhasil diimport.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimport data: '.$e->getMessage(),
            ], 422);
        }
    }

    public function downloadTemplateSiswa()
    {
        // Menyediakan satu baris kosong dengan key (header) yang benar
        $data = collect([
            [
                'NISN' => '',
                'NIS' => '',
                'password' => '',
                'nama' => '',
                'tempat_lahir' => '',
                'tgl_lahir' => '',
                'lp' => '',
                'agama' => '',
                'alamat' => '',
                'nama_wali' => '',
                'no_hp_wali' => '',
            ],
        ]);

        return (new FastExcel($data))->download('template_siswa.xlsx');
    }

    public function downloadTemplateGuru()
    {
        // Menyediakan satu baris kosong dengan key (header) yang benar
        $data = collect([
            [
                'nip' => '',
                'password' => '',
                'nama' => '',
                'lp' => '',
                'agama' => '',
                'alamat' => '',
                'no_hp' => '',
            ],
        ]);

        return (new FastExcel($data))->download('template_guru.xlsx');
    }
}
