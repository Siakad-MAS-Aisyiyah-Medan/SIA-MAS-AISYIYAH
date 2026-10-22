<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['key' => 'manage_all', 'name' => 'Kelola Semua', 'group' => 'admin'],
            ['key' => 'manage_profil_sekolah', 'name' => 'Kelola Profil Sekolah', 'group' => 'admin'],
            ['key' => 'manage_tahun_ajaran', 'name' => 'Kelola Tahun Ajaran', 'group' => 'admin'],
            ['key' => 'manage_pengumuman', 'name' => 'Kelola Pengumuman Sekolah', 'group' => 'admin'],
            ['key' => 'manage_guru', 'name' => 'Kelola Data Guru', 'group' => 'admin'],
            ['key' => 'manage_murid', 'name' => 'Kelola Data Murid', 'group' => 'admin'],
            ['key' => 'manage_kelas', 'name' => 'Kelola Data Kelas', 'group' => 'admin'],
            ['key' => 'manage_mapel', 'name' => 'Kelola Mata Pelajaran', 'group' => 'admin'],
            ['key' => 'manage_ppdb', 'name' => 'Kelola Data PPDB', 'group' => 'admin'],
            ['key' => 'manage_users', 'name' => 'Kelola Akun Pengguna dan Hak Akses', 'group' => 'admin'],

            ['key' => 'view_dashboard_kepsek', 'name' => 'Dashboard Kepala Sekolah', 'group' => 'kepsek'],
            ['key' => 'view_dashboard_guru', 'name' => 'Dashboard Guru', 'group' => 'guru'],
            ['key' => 'view_dashboard_siswa', 'name' => 'Dashboard Murid', 'group' => 'siswa'],
            ['key' => 'view_dashboard_calon_siswa', 'name' => 'Dashboard Calon Murid', 'group' => 'calon_siswa'],

            ['key' => 'manage_profil_pribadi', 'name' => 'Kelola Profil Pribadi', 'group' => 'shared'],
            ['key' => 'manage_pengaturan_akun', 'name' => 'Kelola Pengaturan Akun Pribadi', 'group' => 'shared'],
            ['key' => 'view_pengumuman', 'name' => 'Lihat Pengumuman Sekolah', 'group' => 'shared'],
            ['key' => 'view_data_guru', 'name' => 'Lihat Data Guru', 'group' => 'kepsek'],
            ['key' => 'view_data_siswa', 'name' => 'Lihat Data Murid', 'group' => 'kepsek'],
            ['key' => 'view_data_kelas', 'name' => 'Lihat Data Kelas', 'group' => 'kepsek'],
            ['key' => 'view_mapel', 'name' => 'Lihat Mata Pelajaran', 'group' => 'shared'],
            ['key' => 'view_data_ppdb', 'name' => 'Lihat Data PPDB', 'group' => 'kepsek'],
            ['key' => 'view_transkrip_murid', 'name' => 'Lihat Transkrip Akademik Murid', 'group' => 'kepsek'],
            ['key' => 'view_murid_diajar', 'name' => 'Lihat Data Murid yang Diajar', 'group' => 'guru'],
            ['key' => 'view_kelas_diajar', 'name' => 'Lihat Data Kelas yang Diajar', 'group' => 'guru'],
            ['key' => 'view_mapel_diampu', 'name' => 'Lihat Mata Pelajaran yang Diampu', 'group' => 'guru'],
            ['key' => 'manage_nilai_siswa', 'name' => 'Kelola Nilai Akademik Murid', 'group' => 'guru'],
            ['key' => 'manage_absensi_siswa', 'name' => 'Kelola Absensi Murid', 'group' => 'guru'],
            ['key' => 'view_kelas_pribadi', 'name' => 'Lihat Data Kelas yang Dimasuki', 'group' => 'siswa'],
            ['key' => 'view_transkrip_pribadi', 'name' => 'Lihat Transkrip Akademik Pribadi', 'group' => 'siswa'],
            ['key' => 'view_absensi_pribadi', 'name' => 'Lihat Absensi Pribadi', 'group' => 'siswa'],
            ['key' => 'view_nilai_pribadi', 'name' => 'Lihat Nilai Pribadi', 'group' => 'siswa'],
            ['key' => 'manage_pendaftaran_pribadi', 'name' => 'Kelola Pendaftaran Pribadi', 'group' => 'calon_siswa'],
            ['key' => 'manage_formulir_pendaftaran', 'name' => 'Kelola Formulir Pendaftaran', 'group' => 'calon_siswa'],
            ['key' => 'manage_berkas_pendaftaran', 'name' => 'Kelola Berkas Pendaftaran', 'group' => 'calon_siswa'],
            ['key' => 'submit_pendaftaran', 'name' => 'Kirim Pendaftaran', 'group' => 'calon_siswa'],
            ['key' => 'view_status_pendaftaran', 'name' => 'Lihat Status Pendaftaran', 'group' => 'calon_siswa'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['key' => $perm['key']], $perm);
        }

        $allPermissionIds = Permission::pluck('id_permission', 'key');

        $roles = [
            ['key' => 'admin', 'name' => 'Administrator', 'redirect_path' => '/admin/dashboard', 'permissions' => ['manage_all']],
            ['key' => 'kepsek', 'name' => 'Kepala Sekolah', 'redirect_path' => '/kepala-sekolah/dashboard', 'permissions' => [
                'view_dashboard_kepsek', 'manage_profil_pribadi', 'manage_pengaturan_akun', 'view_pengumuman',
                'view_data_guru', 'view_data_siswa', 'view_data_kelas', 'view_mapel', 'view_transkrip_murid',
                'view_data_ppdb',
            ]],
            ['key' => 'guru', 'name' => 'Guru', 'redirect_path' => '/guru/dashboard', 'permissions' => [
                'view_dashboard_guru', 'manage_profil_pribadi', 'manage_pengaturan_akun', 'view_pengumuman',
                'view_murid_diajar', 'view_kelas_diajar', 'view_mapel_diampu', 'manage_nilai_siswa',
                'manage_absensi_siswa',
            ]],
            ['key' => 'siswa', 'name' => 'Murid', 'redirect_path' => '/siswa/dashboard', 'permissions' => [
                'view_dashboard_siswa', 'manage_profil_pribadi', 'manage_pengaturan_akun', 'view_pengumuman',
                'view_kelas_pribadi', 'view_mapel', 'view_transkrip_pribadi', 'view_absensi_pribadi',
                'view_nilai_pribadi',
            ]],
            ['key' => 'calon_siswa', 'name' => 'Calon Murid', 'redirect_path' => '/calon-murid/dashboard', 'permissions' => [
                'view_dashboard_calon_siswa', 'manage_pengaturan_akun', 'manage_pendaftaran_pribadi',
                'manage_formulir_pendaftaran', 'manage_berkas_pendaftaran', 'submit_pendaftaran',
                'view_status_pendaftaran',
            ]],
        ];

        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(
                ['key' => $roleData['key']],
                [
                    'name' => $roleData['name'],
                    'redirect_path' => $roleData['redirect_path'],
                ]
            );

            if (in_array('manage_all', $roleData['permissions'], true)) {
                $role->permissions()->sync($allPermissionIds->values()->all());
            } else {
                $ids = collect($roleData['permissions'])
                    ->map(fn (string $key) => $allPermissionIds->get($key))
                    ->filter()
                    ->values()
                    ->all();
                $role->permissions()->sync($ids);
            }
        }
    }
}
