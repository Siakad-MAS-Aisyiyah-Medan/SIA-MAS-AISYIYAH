<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlexibleNilaiTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private User $siswa;

    private Kelas $kelas;

    private Mapel $mapel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        $this->guru = User::create([
            'name' => 'Guru Matematika',
            'username' => 'guru-matematika',
            'password' => 'password',
            'role' => 'guru',
            'status_aktif' => true,
            'status_akun' => 'aktif',
        ]);
        $this->kelas = Kelas::create([
            'nama_kelas' => 'X IPA 1',
            'tingkat' => 'X',
            'tahun_ajaran' => '2026/2027',
        ]);
        $this->mapel = Mapel::create([
            'nama_mapel' => 'Matematika',
            'tingkat' => 'X',
            'id_guru' => $this->guru->id_user,
        ]);
        $this->siswa = $this->createStudent();

        $this->actingAs($this->guru);
    }

    public function test_teacher_can_configure_components_and_final_score_is_only_created_when_complete(): void
    {
        $form = $this->getJson('/api/guru/nilai/form?'.http_build_query($this->context()))
            ->assertOk()
            ->assertJsonCount(3, 'data.skema.komponen')
            ->assertJsonPath('data.skema.total_bobot', 100)
            ->json('data');

        $components = $form['skema']['komponen'];
        $schemePayload = [
            'id_skema' => $form['skema']['id_skema'],
            'nama_skema' => 'Skema Fleksibel Matematika',
            'komponen' => [
                array_merge($components[0], ['bobot' => 20]),
                array_merge($components[1], ['bobot' => 30]),
                array_merge($components[2], ['bobot' => 40]),
                ['nama_komponen' => 'Praktik', 'bobot' => 10],
            ],
        ];

        $savedScheme = $this->putJson('/api/guru/nilai/skema', $schemePayload)
            ->assertOk()
            ->assertJsonPath('data.nama_skema', 'Skema Fleksibel Matematika')
            ->assertJsonPath('data.total_bobot', 100)
            ->assertJsonCount(4, 'data.komponen')
            ->json('data');

        $scores = [80, 90, 100, 70];
        foreach ($savedScheme['komponen'] as $index => $component) {
            $this->postJson('/api/guru/nilai/komponen/bulk', [
                'meta' => array_merge($this->context(), [
                    'id_skema' => $savedScheme['id_skema'],
                    'id_komponen' => $component['id_komponen'],
                ]),
                'items' => [[
                    'id_user_siswa' => $this->siswa->id_user,
                    'nilai' => $scores[$index],
                ]],
            ])->assertOk();

            $summary = Nilai::where('id_user_siswa', $this->siswa->id_user)->firstOrFail();
            if ($index < 3) {
                $this->assertNull($summary->nilai_akhir);
            }
        }

        $this->assertDatabaseHas('nilai', [
            'id_user_siswa' => $this->siswa->id_user,
            'id_mapel' => $this->mapel->id_mapel,
            'nilai_akhir' => 90,
            'nilai_angka' => 90,
            'predikat' => 'A',
            'validated_by_wali' => false,
        ]);
    }

    public function test_existing_fixed_scores_are_imported_into_default_scheme_without_treating_missing_as_zero(): void
    {
        Nilai::create([
            'id_user_siswa' => $this->siswa->id_user,
            'id_mapel' => $this->mapel->id_mapel,
            'id_guru_input' => $this->guru->id_user,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
            'nilai_tugas' => 85,
            'nilai_uts' => null,
            'nilai_uas' => 90,
            'nilai_akhir' => 61,
            'nilai_angka' => 61,
            'predikat' => 'D',
        ]);

        $response = $this->getJson('/api/guru/nilai/form?'.http_build_query($this->context()))
            ->assertOk()
            ->assertJsonPath('data.siswa.0.nilai_akhir', null)
            ->assertJsonPath('data.siswa.0.lengkap', false)
            ->json('data');

        $componentByCode = collect($response['skema']['komponen'])->keyBy('kode_komponen');
        $studentScores = $response['siswa'][0]['nilai_komponen'];

        $this->assertSame(85, (int) $studentScores[$componentByCode['nilai_tugas']['id_komponen']]);
        $this->assertArrayNotHasKey(
            (string) $componentByCode['nilai_uts']['id_komponen'],
            $studentScores
        );
        $this->assertSame(90, (int) $studentScores[$componentByCode['nilai_uas']['id_komponen']]);
    }

    private function context(): array
    {
        return [
            'id_kelas' => $this->kelas->id_kelas,
            'id_mapel' => $this->mapel->id_mapel,
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ];
    }

    private function createStudent(): User
    {
        $user = User::create([
            'name' => 'Murid Uji',
            'username' => 'murid-uji-nilai',
            'password' => 'password',
            'role' => 'siswa',
            'status_aktif' => true,
            'status_akun' => 'aktif',
        ]);

        Siswa::create([
            'id_user' => $user->id_user,
            'id_kelas' => $this->kelas->id_kelas,
            'nisn' => '1234567890',
            'nis' => 'NIS-NILAI-01',
            'nama_siswa' => 'Murid Uji',
            'tempat_lahir' => 'Medan',
            'tgl_lahir' => '2009-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'alamat' => 'Medan',
            'nama_wali' => 'Wali Murid',
            'no_hp_wali' => '081234567890',
            'status_siswa' => Siswa::STATUS_AKTIF,
        ]);

        return $user;
    }
}
