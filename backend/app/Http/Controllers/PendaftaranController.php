<?php

namespace App\Http\Controllers;

use App\Http\Resources\PendaftaranResource;
use App\Http\Resources\PpdbResource;
use App\Http\Resources\UserResource;
use App\Models\Pendaftaran;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PpdbBerkasService;
use App\Services\PpdbService;
use App\Services\SchoolNumberService;
use App\Utils\ApiResponse;
use App\Utils\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PendaftaranController extends Controller
{
    use AuditsAdminActions;

    public function __construct(private PpdbService $service, private PpdbBerkasService $berkasService) {}

    /**
     * GET /api/ppdb/my-registration
     * Mengambil data pendaftaran milik user yang login.
     */
    public function myRegistration()
    {
        $user = Auth::user();
        $pendaftaran = $this->service->getByUser($user);

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve(),
            'pendaftaran' => $pendaftaran
                ? PpdbResource::applicant($pendaftaran)->resolve()
                : null,
            'has_registration' => (bool) $pendaftaran,
        ]);
    }

    /**
     * POST /api/ppdb/start
     * Membuat draft pendaftaran jika belum ada.
     */
    public function start()
    {
        return DB::transaction(function () {
            User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();

            return $this->startRegistration();
        });
    }

    private function startRegistration()
    {
        $user = Auth::user();

        if ($user->role !== 'calon_siswa') {
            return ApiResponse::error('Hanya calon siswa yang dapat memulai pendaftaran', 403);
        }

        $existing = $this->service->getByUser($user);
        if ($existing) {
            return ApiResponse::success(
                PpdbResource::applicant($existing)->resolve(),
                'Pendaftaran sudah ada'
            );
        }

        // Buat draft baru
        $pendaftaran = Pendaftaran::create([
            'id_user' => $user->id_user,
            'tahun_pelajaran' => SystemSetting::getValue('ppdb_tahun_ajaran') ?: (now()->month >= 7 ? now()->year.'/'.(now()->year + 1) : (now()->year - 1).'/'.now()->year),
            'current_step' => '1',
            'nisn' => $user->username,
            'nama_lengkap' => $user->name ?? '',
            'tempat_lahir' => '',
            'tgl_lahir' => null,
            'jenis_kelamin' => null,
            'agama' => '',
            'kewarganegaraan' => 'Indonesia',
            'anak_ke' => 1,
            'jml_saudara_kandung' => 0,
            'jml_saudara_tiri' => 0,
            'alamat' => '',
            'no_telp' => '',
            'status_yatim' => 'Tidak',
            'berat_badan' => 0,
            'tinggi_badan' => 0,
            'gol_darah' => '-',
            'sekolah_asal' => '',
            'no_sttb' => '',
            'nama_ayah' => '',
            'nama_ibu' => '',
            'pendidikan_ayah' => '',
            'pendidikan_ibu' => '',
            'pekerjaan_ayah' => '',
            'pekerjaan_ibu' => '',
            'agama_ortu' => '',
            'alamat_ortu' => '',
            'no_hp_ortu' => '',
            'hobi' => '',
            'cita_cita' => '',
            'ppdb_status' => 'draft',
            'status_pendaftaran' => 'draft',
            'status_kelulusan' => 'Pending',
        ]);

        $pendaftaran->keteranganPribadi()->updateOrCreate(
            ['pendaftaran_id' => $pendaftaran->id_pendaftaran],
            [
                'nisn' => $pendaftaran->nisn,
                'nama_lengkap' => $pendaftaran->nama_lengkap,
                'tempat_lahir' => $pendaftaran->tempat_lahir,
                'tgl_lahir' => $pendaftaran->tgl_lahir,
                'jenis_kelamin' => $pendaftaran->jenis_kelamin,
                'agama' => $pendaftaran->agama,
                'kewarganegaraan' => $pendaftaran->kewarganegaraan,
                'anak_ke' => $pendaftaran->anak_ke,
                'jml_saudara_kandung' => $pendaftaran->jml_saudara_kandung,
                'jml_saudara_tiri' => $pendaftaran->jml_saudara_tiri,
                'alamat' => $pendaftaran->alamat,
                'no_telp' => $pendaftaran->no_telp,
                'status_yatim' => $pendaftaran->status_yatim,
            ]
        );

        $this->auditAdmin('calon_siswa.ppdb.start', $pendaftaran, ['nisn' => $user->username]);

        return ApiResponse::success(
            PpdbResource::applicant($pendaftaran->fresh($this->service->pendaftaranRelations()))->resolve(),
            'Draft pendaftaran dibuat',
            201
        );
    }

    /**
     * PUT /api/ppdb/step/keterangan-pribadi
     */
    public function saveKeteranganPribadi(Request $request)
    {
        $request->merge($this->service->normalizeNumericInputs($request, [
            'anak_ke',
            'jml_saudara_kandung',
            'jml_saudara_tiri',
        ]));

        $validated = $request->validate([
            'nisn' => 'sometimes|string|min:10|max:20',
            'nama_lengkap' => 'sometimes|string|max:255',
            'jenis_kelamin' => 'sometimes|in:L,P',
            'tempat_lahir' => 'sometimes|string|max:100',
            'tgl_lahir' => 'sometimes|date|before_or_equal:today',
            'agama' => 'sometimes|string|max:50',
            'kewarganegaraan' => 'sometimes|string|max:50',
            'anak_ke' => 'sometimes|integer|min:1',
            'jml_saudara_kandung' => 'sometimes|integer|min:0',
            'jml_saudara_tiri' => 'sometimes|integer|min:0',
            'alamat' => 'sometimes|string',
            'no_telp' => 'sometimes|string|max:20',
            'status_yatim' => 'sometimes|in:Yatim,Piatu,Yatim Piatu,Tidak',
            'current_step' => 'sometimes|string|max:50',
        ]);

        return $this->savePendaftaranStep(Auth::user(), $validated, 'Keterangan pribadi disimpan');
    }

    /**
     * PUT /api/ppdb/step/kesehatan
     */
    public function saveKesehatan(Request $request)
    {
        $request->merge($this->service->normalizeNumericInputs($request, [
            'berat_badan',
            'tinggi_badan',
        ]));

        $validated = $request->validate([
            'berat_badan' => 'sometimes|integer|min:1',
            'tinggi_badan' => 'sometimes|integer|min:1',
            'gol_darah' => 'sometimes|string|max:5',
            'penyakit_diderita' => 'nullable|string|max:255',
            'cacat_badan' => 'nullable|string|max:255',
            'current_step' => 'sometimes|string|max:50',
        ]);

        return $this->savePendaftaranStep(Auth::user(), $validated, 'Data kesehatan disimpan');
    }

    /**
     * PUT /api/ppdb/step/pendidikan-asal
     */
    public function savePendidikanAsal(Request $request)
    {
        $validated = $request->validate([
            'sekolah_asal' => 'sometimes|string|max:255',
            'tahun_lulus' => 'sometimes|string|max:4',
            'no_sttb' => 'sometimes|string|max:100',
            'pindahan_dari' => 'nullable|string|max:255',
            'no_surat_pindah' => 'nullable|string|max:100',
            'current_step' => 'sometimes|string|max:50',
        ]);

        return $this->savePendaftaranStep(Auth::user(), $validated, 'Data pendidikan asal disimpan');
    }

    /**
     * PUT /api/ppdb/step/orang-tua-wali
     */
    public function saveOrangTuaWali(Request $request)
    {
        $validated = $request->validate([
            'nama_ayah' => 'sometimes|string|max:255',
            'nama_ibu' => 'sometimes|string|max:255',
            'pendidikan_ayah' => 'sometimes|string|max:100',
            'pendidikan_ibu' => 'sometimes|string|max:100',
            'pekerjaan_ayah' => 'sometimes|string|max:100',
            'pekerjaan_ibu' => 'sometimes|string|max:100',
            'agama_ortu' => 'sometimes|string|max:50',
            'alamat_ortu' => 'sometimes|string',
            'no_hp_ortu' => 'sometimes|string|max:20',
            'no_hp_ayah' => 'nullable|string|max:20',
            'no_hp_ibu' => 'nullable|string|max:20',
            'nama_wali' => 'nullable|string|max:255',
            'pendidikan_wali' => 'nullable|string|max:100',
            'pekerjaan_wali' => 'nullable|string|max:100',
            'agama_wali' => 'nullable|string|max:50',
            'alamat_wali' => 'nullable|string',
            'current_step' => 'sometimes|string|max:50',
        ]);

        return $this->savePendaftaranStep(Auth::user(), $validated, 'Data orang tua/wali disimpan');
    }

    /**
     * PUT /api/ppdb/step/kepribadian
     */
    public function saveKepribadian(Request $request)
    {
        $validated = $request->validate([
            'hobi' => 'sometimes|string|max:255',
            'cita_cita' => 'sometimes|string|max:255',
            'current_step' => 'sometimes|string|max:50',
        ]);

        return $this->savePendaftaranStep(Auth::user(), $validated, 'Data kepribadian disimpan');
    }

    /**
     * PUT /api/ppdb/step/dokumen
     */
    public function saveDokumen(Request $request)
    {
        $validated = $request->validate([
            'catatan_dokumen' => 'nullable|string|max:500',
        ]);

        return $this->savePendaftaranStep(Auth::user(), $validated, 'Data dokumen disimpan');
    }

    /**
     * POST /api/ppdb/submit
     */
    public function submit(?Request $request = null)
    {
        return DB::transaction(function () {
            User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();

            return $this->submitRegistration();
        });
    }

    private function submitRegistration()
    {
        $user = Auth::user();
        if ($user->role !== 'calon_siswa') {
            return ApiResponse::error('Hanya calon siswa yang dapat submit', 403);
        }

        $pendaftaran = $this->service->getByUser($user);
        if (! $pendaftaran) {
            return ApiResponse::error('Data pendaftaran tidak ditemukan. Mulai pendaftaran terlebih dahulu.', 404);
        }

        if (! in_array($pendaftaran->ppdb_status, ['draft', 'revisi'], true)) {
            return ApiResponse::error('Pendaftaran sudah dikirim atau sedang diproses.', 422);
        }

        $required = ['nama_lengkap', 'tempat_lahir', 'tgl_lahir', 'jenis_kelamin', 'agama', 'alamat', 'sekolah_asal', 'nama_ayah', 'nama_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu'];
        foreach ($required as $field) {
            if (empty(trim((string) $pendaftaran->$field))) {
                return ApiResponse::error("Field {$field} wajib diisi sebelum submit.", 422);
            }
        }

        try {
            $this->berkasService->assertAllRequiredUploaded($pendaftaran->load('dokumen'));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $pendaftaran->ppdb_status = 'diajukan';
        $pendaftaran->status_pendaftaran = 'submitted';
        $pendaftaran->status_kelulusan = 'Pending';
        $pendaftaran->submitted_at = now();
        if (! $pendaftaran->no_registrasi) {
            $prefix = 'PPDB-'.now()->year.'-';
            $pendaftaran->no_registrasi = app(SchoolNumberService::class)->next('ppdb-'.now()->year, $prefix, 'pendaftaran', 'no_registrasi');
            $pendaftaran->nomor_pendaftaran = $pendaftaran->no_registrasi;
        }
        $pendaftaran->save();

        $this->auditAdmin('calon_siswa.ppdb.submit', $pendaftaran, ['no_registrasi' => $pendaftaran->no_registrasi]);

        return ApiResponse::success(
            PpdbResource::applicant($pendaftaran->fresh(['dokumen']))->resolve(),
            'Pendaftaran berhasil diajukan'
        );
    }

    /**
     * GET /api/ppdb/status
     */
    public function status()
    {
        $pendaftaran = $this->service->getByUser(Auth::user());

        if (! $pendaftaran) {
            return ApiResponse::success(null, 'Belum ada data pendaftaran');
        }

        return ApiResponse::success([
            'status' => $pendaftaran->ppdb_status,
            'no_registrasi' => $pendaftaran->no_registrasi,
            'catatan_admin' => $pendaftaran->catatan_admin,
            'submitted_at' => $pendaftaran->submitted_at,
            'verified_at' => $pendaftaran->verified_at,
            'accepted_at' => $pendaftaran->accepted_at,
        ]);
    }

    /**
     * Helper: simpan step data pendaftaran
     */
    private function savePendaftaranStep(User $user, array $data, string $msg): JsonResponse
    {
        $pendaftaran = $this->service->getByUser($user);
        if (! $pendaftaran) {
            return ApiResponse::error('Data pendaftaran tidak ditemukan. Mulai pendaftaran terlebih dahulu.', 404);
        }

        $locked = ['diajukan', 'terverifikasi', 'diterima', 'ditolak', 'daftar_ulang', 'menjadi_murid'];
        if (in_array($pendaftaran->ppdb_status, $locked, true)) {
            return ApiResponse::error('Pendaftaran tidak dapat diubah pada status: '.$pendaftaran->ppdb_status.'.', 422);
        }

        DB::transaction(function () use ($pendaftaran, $data) {
            $pendaftaran->fill($data);
            $pendaftaran->save();
            $this->service->syncPendaftaranDetail($pendaftaran, $data);
        });

        $this->auditAdmin('calon_siswa.ppdb.update_step', $pendaftaran, ['step' => $data['current_step'] ?? 'unknown']);

        return ApiResponse::success(
            PpdbResource::applicant($pendaftaran->fresh($this->service->pendaftaranRelations()))->resolve(),
            $msg
        );
    }

    public function calonShow()
    {
        $user = Auth::user();
        $data = $this->service->getByUser($user);

        return ApiResponse::success(
            $data ? PendaftaranResource::applicant($data)->resolve() : null
        );
    }

    public function calonUpdate(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'tempat_lahir' => 'sometimes|string|max:100',
            'tgl_lahir' => 'sometimes|date|before_or_equal:today',
            'agama' => 'sometimes|string|max:50',
            'kewarganegaraan' => 'sometimes|string|max:50',
            'anak_ke' => 'sometimes|integer|min:1',
            'jml_saudara_kandung' => 'sometimes|integer|min:0',
            'jml_saudara_tiri' => 'sometimes|integer|min:0',
            'alamat' => 'sometimes|string',
            'no_telp' => 'sometimes|string|max:20',
            'status_yatim' => 'sometimes|in:Yatim,Piatu,Yatim Piatu,Tidak',
            'berat_badan' => 'sometimes|integer|min:1',
            'tinggi_badan' => 'sometimes|integer|min:1',
            'gol_darah' => 'sometimes|string|max:5',
            'penyakit_diderita' => 'nullable|string|max:255',
            'cacat_badan' => 'nullable|string|max:255',
            'sekolah_asal' => 'sometimes|string|max:255',
            'no_sttb' => 'sometimes|string|max:100',
            'pindahan_dari' => 'nullable|string|max:255',
            'no_surat_pindah' => 'nullable|string|max:100',
            'nama_ayah' => 'sometimes|string|max:255',
            'nama_ibu' => 'sometimes|string|max:255',
            'pendidikan_ayah' => 'sometimes|string|max:100',
            'pendidikan_ibu' => 'sometimes|string|max:100',
            'pekerjaan_ayah' => 'sometimes|string|max:100',
            'pekerjaan_ibu' => 'sometimes|string|max:100',
            'agama_ortu' => 'sometimes|string|max:50',
            'alamat_ortu' => 'sometimes|string',
            'nama_wali' => 'nullable|string|max:255',
            'pendidikan_wali' => 'nullable|string|max:100',
            'pekerjaan_wali' => 'nullable|string|max:100',
            'agama_wali' => 'nullable|string|max:50',
            'alamat_wali' => 'nullable|string',
            'hobi' => 'sometimes|string|max:255',
            'cita_cita' => 'sometimes|string|max:255',
            'file_ijazah' => 'nullable|file|mimes:pdf|max:5120',
            'file_stk' => 'nullable|file|mimes:pdf|max:5120',
            'file_pas_photo' => 'nullable|file|mimes:pdf|max:5120',
            'file_nisn' => 'nullable|file|mimes:pdf|max:5120',
            'file_kk' => 'nullable|file|mimes:pdf|max:5120',
            'file_ktp_ortua' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        $user = Auth::user();

        try {
            $pendaftaran = $this->service->saveDraftForUser(
                $user,
                $request->safeFields(),
                $request
            );

            return ApiResponse::success(
                PendaftaranResource::applicant($pendaftaran)->resolve(),
                'Draft pendaftaran disimpan'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function calonSubmit()
    {
        $user = Auth::user();
        if ($user->role !== 'calon_siswa') {
            return ApiResponse::error('Hanya calon siswa yang dapat submit', 403);
        }

        try {
            $pendaftaran = $this->service->submitForUser($user);

            return ApiResponse::success(
                PendaftaranResource::applicant($pendaftaran)->resolve(),
                'Pendaftaran berhasil dikirim'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * @deprecated Gunakan POST /api/auth/register-calon-siswa
     */
    public function calonPpdbRegister(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required_without:nama|string|max:255',
            'nama' => 'required_without:nama_lengkap|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'nisn' => 'required|string|min:10|max:20|unique:users,username',
            'no_hp' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return ApiResponse::error(
            'Endpoint ini tidak lagi digunakan. Daftar akun melalui POST /api/auth/register-calon-siswa, lalu isi formulir PPDB setelah login.',
            410
        );
    }

    public function calonPpdbMe()
    {
        $user = Auth::user();
        $data = $this->service->getByUser($user);

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve(),
            'pendaftaran' => $data ? PpdbResource::applicant($data)->resolve() : null,
        ]);
    }

    public function calonPpdbSaveFormulir(Request $request)
    {
        $validated = $request->validate([
            'nisn' => 'sometimes|string|min:10|max:20',
            'nama_lengkap' => 'sometimes|string|max:255',
            'jenis_kelamin' => 'sometimes|in:L,P',
            'tempat_lahir' => 'sometimes|string|max:100',
            'tanggal_lahir' => 'sometimes|date|before_or_equal:today',
            'tgl_lahir' => 'sometimes|date|before_or_equal:today',
            'agama' => 'sometimes|string|max:50',
            'alamat' => 'sometimes|string',
            'asal_sekolah' => 'sometimes|string|max:255',
            'sekolah_asal' => 'sometimes|string|max:255',
            'tahun_lulus' => 'sometimes|string|max:4',
            'nama_ayah' => 'sometimes|string|max:255',
            'pekerjaan_ayah' => 'sometimes|string|max:100',
            'nama_ibu' => 'sometimes|string|max:255',
            'pekerjaan_ibu' => 'sometimes|string|max:100',
            'no_hp_ortu' => 'sometimes|string|max:20',
            'no_telp' => 'sometimes|string|max:20',
        ]);

        try {
            $pendaftaran = $this->service->saveFormulir(Auth::user(), $validated);

            return ApiResponse::success(
                PpdbResource::applicant($pendaftaran)->resolve(),
                'Formulir disimpan sebagai draft'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function calonPpdbUploadBerkas(Request $request)
    {
        $jenis = implode(',', PpdbBerkasService::allJenisKeys());
        $maxKb = (int) config('ppdb.berkas.max_size_kb', 2048);

        $validated = $request->validate([
            'jenis_berkas' => 'required|string|in:'.$jenis,
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:'.$maxKb,
        ]);

        try {
            $berkas = $this->service->uploadBerkas(
                Auth::user(),
                $validated['jenis_berkas'],
                $request->file('file')
            );

            $this->auditAdmin('calon_siswa.ppdb.upload_berkas', null, ['jenis_berkas' => $validated['jenis_berkas']]);

            return ApiResponse::success($berkas, 'Berkas berhasil diunggah');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function calonPpdbSubmit()
    {
        $user = Auth::user();
        if ($user->role !== 'calon_siswa') {
            return ApiResponse::error('Hanya calon murid yang dapat submit', 403);
        }

        try {
            $pendaftaran = $this->submit($user);

            return ApiResponse::success(
                PpdbResource::applicant($pendaftaran)->resolve(),
                'Pendaftaran berhasil diajukan'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function calonPpdbStatus()
    {
        $data = $this->service->getByUser(Auth::user());

        if (! $data) {
            return ApiResponse::success(null, 'Belum ada data pendaftaran');
        }

        return ApiResponse::success([
            'status' => $data->ppdb_status,
            'no_registrasi' => $data->no_registrasi,
            'catatan_admin' => $data->catatan_admin,
            'submitted_at' => $data->submitted_at,
            'verified_at' => $data->verified_at,
            'accepted_at' => $data->accepted_at,
        ]);
    }

    public function calonPpdbBukti()
    {
        $data = $this->service->getByUser(Auth::user());
        if (! $data) {
            return ApiResponse::error('Data pendaftaran tidak ditemukan', 404);
        }

        if (! in_array($data->ppdb_status, ['diterima', 'daftar_ulang', 'menjadi_murid'], true)) {
            return ApiResponse::error('Bukti hanya tersedia untuk pendaftar yang diterima', 403);
        }

        return ApiResponse::success($this->service->getBuktiData($data), 'Bukti pendaftaran');
    }

    public function publicInfo()
    {
        return ApiResponse::success($this->service->getPublicInfo(), 'Informasi PPDB');
    }

    public function downloadBrosur()
    {
        $path = $this->service->resolveBrosurStoragePath();
        if (! $path) {
            return response()->json(['message' => 'Brosur not found'], 404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $downloadName = 'brosur-ppdb-mas-aisyiyah-medan'.($extension ? '.'.$extension : '');
        $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk('public')->download($path, $downloadName, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
        ]);
    }

    /** @deprecated Gunakan PpdbBerkasService::allJenisKeys() */
    public function adminPpdbStats()
    {
        $total = Pendaftaran::count();
        $accepted = Pendaftaran::whereIn('ppdb_status', ['diterima', 'accepted', 'menjadi_murid'])->count();
        $rejected = Pendaftaran::whereIn('ppdb_status', ['ditolak', 'rejected'])->count();

        $belum_mengirim = Pendaftaran::where('ppdb_status', 'draft')->count();
        $sudah_mengirim = Pendaftaran::whereIn('ppdb_status', ['submitted', 'diajukan', 'terverifikasi', 'verified'])->count();
        $revisi = Pendaftaran::whereIn('ppdb_status', ['revisi', 'dikembalikan'])->count();

        return ApiResponse::success([
            'total' => $total,
            'diterima' => $accepted,
            'ditolak' => $rejected,
            'belum_mengirim' => $belum_mengirim,
            'sudah_mengirim' => $sudah_mengirim,
            'revisi' => $revisi,
        ]);
    }

    public function adminPpdbIndex(Request $request)
    {
        return ApiResponse::paginated(
            $this->service->adminList($request->query('search'), $request->query('status'), (int) $request->query('per_page', 10)),
            'Berhasil mengambil data PPDB'
        );
    }

    public function adminPpdbShow($id)
    {
        return ApiResponse::success($this->service->adminFind((int) $id));
    }

    public function adminPpdbVerifikasi($id)
    {
        return ApiResponse::success($this->service->adminVerifikasi((int) $id), 'Berhasil verifikasi');
    }

    public function adminPpdbRevisi(Request $request, $id)
    {
        return ApiResponse::success($this->service->adminRevisi((int) $id, $request->input('catatan', '')), 'Berhasil revisi');
    }

    public function adminPpdbTerima(Request $request, $id)
    {
        return ApiResponse::success($this->service->adminTerima((int) $id, $request->input('catatan', '')), 'Berhasil diterima');
    }

    public function adminPpdbTolak(Request $request, $id)
    {
        return ApiResponse::success($this->service->adminTolak((int) $id, $request->input('catatan', '')), 'Berhasil ditolak');
    }

    /**
     * PATCH /admin/ppdb/{id}/status — legacy route compatibility.
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:terverifikasi,diterima,ditolak,revisi',
            'catatan' => 'nullable|string|max:1000',
        ]);

        $status = $validated['status'];
        $catatan = $validated['catatan'] ?? '';

        return match ($status) {
            'terverifikasi' => $this->adminPpdbVerifikasi($id),
            'diterima' => $this->adminPpdbTerima($request, $id),
            'ditolak' => $this->adminPpdbTolak($request, $id),
            'revisi' => $this->adminPpdbRevisi($request, $id),
            default => ApiResponse::error('Status tidak valid', 422),
        };
    }

    public function adminPpdbJadikanMurid(Request $request, $id)
    {
        $data = $request->validate(['id_kelas' => 'nullable|integer|exists:kelas,id_kelas']);

        return ApiResponse::success($this->service->adminJadikanMurid((int) $id, $data['id_kelas'] ?? null), 'Berhasil jadikan murid');
    }
}
