<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Guru;
use App\Models\KepalaSekolah;
use App\Models\Siswa;
use App\Utils\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiodataController extends Controller
{
    use AuditsAdminActions;

    public function update(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = auth()->user();
            $role = $user->role;

            if ($role === 'guru') {
                $validated = $request->validate([
                    'nip' => 'nullable|string|max:50|unique:guru,nip,'.$user->id_user.',id_user',
                    'nip_nuptk' => 'nullable|string|max:50|unique:guru,nip,'.$user->id_user.',id_user',
                    'nama_guru' => 'required|string|max:255',
                    'jenis_kelamin' => 'nullable|string|in:L,P',
                    'tgl_lahir' => 'nullable|date|before_or_equal:today',
                    'agama' => 'nullable|string|max:50',
                    'alamat' => 'nullable|string',
                    'no_hp' => 'nullable|string|max:20',
                ]);
                $validated['nip'] = $validated['nip'] ?? $validated['nip_nuptk'] ?? null;
                unset($validated['nip_nuptk']);
                $profile = $user->guru;
                if (! $profile) {
                    $profile = new Guru(['id_user' => $user->id_user]);
                }
                $profile->fill($validated);
                if (! $profile->nip) {
                    $profile->nip = $user->username ?: '-';
                }
                if (! $profile->jenis_kelamin) {
                    $profile->jenis_kelamin = 'L';
                }
                if (! $profile->agama) {
                    $profile->agama = '-';
                }
                if (! $profile->alamat) {
                    $profile->alamat = '-';
                }
                if (! $profile->no_hp) {
                    $profile->no_hp = '-';
                }
                $profile->save();
            } elseif ($role === 'siswa') {
                $validated = $request->validate([
                    'nisn' => 'nullable|string|max:20|unique:siswa,nisn,'.$user->id_user.',id_user',
                    'nis' => 'nullable|string|max:20|unique:siswa,nis,'.$user->id_user.',id_user',
                    'nama_siswa' => 'required|string|max:255',
                    'tempat_lahir' => 'nullable|string|max:100',
                    'tgl_lahir' => 'nullable|date|before_or_equal:today',
                    'jenis_kelamin' => 'nullable|string|in:L,P',
                    'agama' => 'nullable|string|max:50',
                    'alamat' => 'nullable|string',
                    'nama_wali' => 'nullable|string|max:255',
                    'no_hp_wali' => 'nullable|string|max:20',
                ]);
                $profile = $user->siswa;
                if (! $profile) {
                    $profile = new Siswa(['id_user' => $user->id_user]);
                }
                $profile->fill($validated);
                if (! $profile->nisn) {
                    $profile->nisn = $user->username ?: '-';
                }
                if (! $profile->nis) {
                    $profile->nis = $user->username ?: '-';
                }
                if (! $profile->tempat_lahir) {
                    $profile->tempat_lahir = '-';
                }
                if (! $profile->tgl_lahir) {
                    $profile->tgl_lahir = now()->format('Y-m-d');
                }
                if (! $profile->jenis_kelamin) {
                    $profile->jenis_kelamin = 'L';
                }
                if (! $profile->agama) {
                    $profile->agama = '-';
                }
                if (! $profile->alamat) {
                    $profile->alamat = '-';
                }
                if (! $profile->nama_wali) {
                    $profile->nama_wali = '-';
                }
                if (! $profile->no_hp_wali) {
                    $profile->no_hp_wali = '-';
                }
                $profile->save();
            } elseif ($role === 'kepsek') {
                $validated = $request->validate([
                    'nip' => 'nullable|string|max:50',
                    'nama_lengkap' => 'required|string|max:255',
                    'jenis_kelamin' => 'nullable|string|in:L,P',
                    'tgl_lahir' => 'nullable|date|before_or_equal:today',
                    'no_hp' => 'nullable|string|max:20',
                    'alamat' => 'nullable|string',
                ]);
                $validated['nama_kepsek'] = $validated['nama_lengkap'];
                unset($validated['nama_lengkap']);
                $profile = $user->kepalaSekolah;
                if (! $profile) {
                    $profile = new KepalaSekolah(['id_user' => $user->id_user]);
                }
                $profile->fill($validated);
                if (! $profile->nip) {
                    $profile->nip = $user->username ?: '-';
                }
                if (! $profile->no_hp) {
                    $profile->no_hp = '-';
                }
                $profile->save();

                $guruProfile = $user->guru ?: new Guru(['id_user' => $user->id_user]);
                $guruProfile->fill([
                    'nip' => $profile->nip,
                    'nama_guru' => $profile->nama_kepsek,
                    'jenis_kelamin' => $profile->jenis_kelamin,
                    'tgl_lahir' => $profile->tgl_lahir,
                    'alamat' => $profile->alamat,
                    'no_hp' => $profile->no_hp,
                    'agama' => $guruProfile->agama ?: '-',
                    'status' => $guruProfile->status ?: 'aktif',
                    'foto' => $profile->foto ?: $guruProfile->foto,
                ]);
                $guruProfile->save();
            } elseif ($role === 'admin') {
                $validated = $request->validate([
                    'nip' => 'nullable|string|max:50',
                    'nama_lengkap' => 'required|string|max:255',
                    'no_hp' => 'nullable|string|max:20',
                ]);
                $validated['nama_admin'] = $validated['nama_lengkap'];
                unset($validated['nama_lengkap']);
                $profile = $user->admin;
                if (! $profile) {
                    $profile = new Admin(['id_user' => $user->id_user]);
                }
                $profile->fill($validated);
                if (! $profile->nip) {
                    $profile->nip = $user->username ?: '-';
                }
                if (! $profile->no_hp) {
                    $profile->no_hp = '-';
                }
                $profile->save();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Role tidak memiliki biodata.',
                ], 400);
            }

            $user->update(['name' => $profile->nama_guru ?? $profile->nama_siswa ?? $profile->nama_kepsek ?? $profile->nama_admin]);

            $this->auditAdmin('profile.biodata.update', null, ['role' => $role]);

            return response()->json([
                'success' => true,
                'message' => 'Biodata berhasil diperbarui.',
                'data' => $profile,
            ]);
        });
    }
}
