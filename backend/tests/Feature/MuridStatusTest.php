<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuridStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_admin_can_filter_students_by_status_and_last_class(): void
    {
        $kelasA = Kelas::create(['nama_kelas' => 'XII IPA 1', 'tahun_ajaran' => '2025/2026']);
        $kelasB = Kelas::create(['nama_kelas' => 'XII IPS 1', 'tahun_ajaran' => '2025/2026']);

        $this->createStudent('aktif-a', $kelasA, Siswa::STATUS_AKTIF);
        $expected = $this->createStudent('lulus-a', $kelasA, Siswa::STATUS_LULUS);
        $this->createStudent('lulus-b', $kelasB, Siswa::STATUS_LULUS);

        $response = $this->getJson('/api/murid?status_siswa=lulus&id_kelas='.$kelasA->id_kelas);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_user', $expected->id_user)
            ->assertJsonPath('data.0.siswa.status_siswa', Siswa::STATUS_LULUS)
            ->assertJsonPath('data.0.siswa.id_kelas', $kelasA->id_kelas);
    }

    public function test_graduating_student_preserves_profile_and_last_class_and_disables_account(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'XII IPA 2', 'tahun_ajaran' => '2025/2026']);
        $user = $this->createStudent('calon-alumni', $kelas, Siswa::STATUS_AKTIF);
        $siswaId = $user->siswa->id_siswa;
        $token = $user->createToken('student-session')->accessToken;

        $payload = $this->validUpdatePayload($user, [
            'status_siswa' => Siswa::STATUS_LULUS,
            'tahun_lulus' => null,
            'status_aktif' => true,
        ]);

        $this->putJson('/api/murid/'.$user->id_user, $payload)
            ->assertOk()
            ->assertJsonPath('data.siswa.status_siswa', Siswa::STATUS_LULUS)
            ->assertJsonPath('data.siswa.id_kelas', $kelas->id_kelas);

        $this->assertDatabaseHas('siswa', [
            'id_siswa' => $siswaId,
            'id_user' => $user->id_user,
            'id_kelas' => $kelas->id_kelas,
            'status_siswa' => Siswa::STATUS_LULUS,
            'tahun_lulus' => now()->year,
        ]);
        $this->assertDatabaseHas('users', [
            'id_user' => $user->id_user,
            'status_aktif' => false,
            'status_akun' => 'nonaktif',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_alumni_cannot_be_deleted_through_regular_student_delete_action(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'XII IPS 2', 'tahun_ajaran' => '2025/2026']);
        $user = $this->createStudent('alumni-arsip', $kelas, Siswa::STATUS_LULUS);

        $this->deleteJson('/api/murid/'.$user->id_user)
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id_user' => $user->id_user]);
        $this->assertDatabaseHas('siswa', [
            'id_user' => $user->id_user,
            'status_siswa' => Siswa::STATUS_LULUS,
        ]);
    }

    public function test_last_class_cannot_be_deleted_while_still_referenced_by_student_archive(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'XII IPA Arsip', 'tahun_ajaran' => '2025/2026']);
        $this->createStudent('alumni-kelas', $kelas, Siswa::STATUS_LULUS);

        $this->deleteJson('/api/kelas/'.$kelas->id_kelas)
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('kelas', ['id_kelas' => $kelas->id_kelas]);
    }

    private function createStudent(string $key, Kelas $kelas, string $status): User
    {
        $user = User::create([
            'name' => 'Murid '.$key,
            'username' => $key,
            'email' => $key.'@example.test',
            'password' => 'password',
            'role' => 'siswa',
            'status_aktif' => $status === Siswa::STATUS_AKTIF,
            'status_akun' => $status === Siswa::STATUS_AKTIF ? 'aktif' : 'nonaktif',
        ]);

        $user->siswa()->create([
            'id_kelas' => $kelas->id_kelas,
            'nisn' => str_pad((string) $user->id_user, 10, '1', STR_PAD_LEFT),
            'nis' => 'NIS-'.$key,
            'nama_siswa' => 'Murid '.$key,
            'tempat_lahir' => 'Medan',
            'tgl_lahir' => '2008-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'alamat' => 'Medan',
            'nama_wali' => 'Wali '.$key,
            'no_hp_wali' => '081234567890',
            'no_hp' => '081234567890',
            'tahun_masuk' => 2023,
            'tahun_lulus' => $status === Siswa::STATUS_LULUS ? 2026 : null,
            'status_siswa' => $status,
            'status_diubah_pada' => now(),
        ]);

        return $user->fresh('siswa');
    }

    private function validUpdatePayload(User $user, array $overrides = []): array
    {
        $siswa = $user->siswa;

        return array_merge([
            'nama_siswa' => $siswa->nama_siswa,
            'nisn' => $siswa->nisn,
            'nis' => $siswa->nis,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'tempat_lahir' => $siswa->tempat_lahir,
            'tanggal_lahir' => $siswa->tgl_lahir->toDateString(),
            'alamat' => $siswa->alamat,
            'no_hp' => $siswa->no_hp,
            'tahun_masuk' => $siswa->tahun_masuk,
            'tahun_lulus' => $siswa->tahun_lulus,
            'id_kelas' => $siswa->id_kelas,
            'status_siswa' => $siswa->status_siswa,
            'status_aktif' => $user->status_aktif,
        ], $overrides);
    }
}
