<?php

namespace App\Services;

use App\Models\Pendaftaran;
use App\Models\PendaftaranDokumen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Upload berkas PPDB — aman, menggunakan tabel PendaftaranDokumen.
 */
class PpdbBerkasService
{
    /** @var array<string, string> key => label */
    public const JENIS = [
        'ijazah_atau_skl' => 'Ijazah/SKHUN',
        'stk' => 'STK Dilegalisir',
        'pas_foto' => 'Pas Foto 3x4',
        'nisn' => 'NISN',
        'kartu_keluarga' => 'Kartu Keluarga',
        'ktp_orang_tua' => 'KTP Orang Tua',
    ];

    /** Alias jenis lama → baru (kompatibilitas data) */
    private const LEGACY_JENIS_MAP = [
        'foto' => 'pas_foto',
        'rapor' => 'stk',
        'akta_kelahiran' => 'nisn',
    ];

    public const FILE_COLUMNS = [
        'ijazah_atau_skl' => 'file_ijazah',
        'kartu_keluarga' => 'file_kk',
        'pas_foto' => 'file_pas_photo',
        'stk' => 'file_stk',
        'nisn' => 'file_nisn',
        'ktp_orang_tua' => 'file_ktp_ortua',
    ];

    public static function normalizeJenis(string $jenis): string
    {
        return self::LEGACY_JENIS_MAP[$jenis] ?? $jenis;
    }

    public static function allJenisKeys(): array
    {
        return array_keys(self::JENIS);
    }

    /**
     * @return array{items: array<int, array>, can_edit: bool, max_size_kb: int, allowed_extensions: array}
     */
    public function listForUser(User $user): array
    {
        $pendaftaran = $this->getPendaftaranByUser($user);
        if (! $pendaftaran) {
            return [
                'items' => $this->buildPlaceholderItems(),
                'can_edit' => true,
                'max_size_kb' => config('ppdb.berkas.max_size_kb', 2048),
                'allowed_extensions' => config('ppdb.berkas.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png']),
            ];
        }

        $pendaftaran->load('dokumen');
        $dokumen = $pendaftaran->dokumen;

        $items = [];
        foreach (self::JENIS as $key => $label) {
            $col = self::FILE_COLUMNS[$key];
            $filePath = $dokumen ? $dokumen->$col : null;
            $items[] = $this->formatBerkasItem($key, $label, $filePath, $dokumen);
        }

        return [
            'items' => $items,
            'can_edit' => $this->canEditPendaftaran($pendaftaran),
            'max_size_kb' => config('ppdb.berkas.max_size_kb', 2048),
            'allowed_extensions' => config('ppdb.berkas.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png']),
        ];
    }

    public function upload(User $user, string $jenis, UploadedFile $file): array
    {
        $jenis = self::normalizeJenis($jenis);
        if (! array_key_exists($jenis, self::JENIS)) {
            throw new InvalidArgumentException('Jenis berkas tidak valid.');
        }

        $pendaftaran = $this->resolveEditablePendaftaran($user);
        $this->assertAllowedFile($file, $jenis);

        $disk = config('ppdb.berkas.storage_disk', 'public');

        $dokumen = PendaftaranDokumen::firstOrCreate(
            ['pendaftaran_id' => $pendaftaran->id_pendaftaran]
        );

        $col = self::FILE_COLUMNS[$jenis];
        $existingPath = $dokumen->$col;

        $ext = strtolower($file->getClientOriginalExtension());
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'berkas';
        $storedPath = $file->storeAs(
            config('ppdb.berkas.storage_path_prefix', 'ppdb').'/'.$user->id_user,
            $jenis.'-'.$safeName.'-'.Str::uuid().'.'.$ext,
            $disk
        );
        if (! $storedPath) {
            throw new \RuntimeException('Berkas gagal disimpan. Silakan coba kembali.');
        }
        try {
            DB::transaction(function () use ($dokumen, $col, $storedPath, $pendaftaran) {
                $dokumen->$col = $storedPath;
                $dokumen->save();
                $pendaftaran->update([$col => $storedPath]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($storedPath);
            throw $e;
        }
        if ($existingPath && $existingPath !== $storedPath) {
            Storage::disk($disk)->delete($existingPath);
        }

        return $this->formatBerkasItem($jenis, self::JENIS[$jenis], $storedPath, $dokumen);
    }

    public function delete(User $user, string $jenis): void
    {
        $jenis = self::normalizeJenis($jenis);
        if (! array_key_exists($jenis, self::FILE_COLUMNS)) {
            throw new InvalidArgumentException('Jenis berkas tidak valid.');
        }
        $pendaftaran = $this->resolveEditablePendaftaran($user);

        $dokumen = PendaftaranDokumen::where('pendaftaran_id', $pendaftaran->id_pendaftaran)->first();
        if (! $dokumen) {
            throw new InvalidArgumentException('Berkas tidak ditemukan.');
        }

        $col = self::FILE_COLUMNS[$jenis];
        $existingPath = $dokumen->$col;

        if (! $existingPath) {
            throw new InvalidArgumentException('Berkas tidak ditemukan.');
        }

        $disk = config('ppdb.berkas.storage_disk', 'public');
        if (Storage::disk($disk)->exists($existingPath)) {
            Storage::disk($disk)->delete($existingPath);
        }

        $dokumen->$col = null;
        $dokumen->save();
        $pendaftaran->update([$col => null]);
    }

    public function assertAllRequiredUploaded(Pendaftaran $pendaftaran): void
    {
        $dokumen = $pendaftaran->dokumen;
        if (! $dokumen) {
            throw new InvalidArgumentException('Anda belum mengunggah berkas apapun.');
        }

        foreach (self::JENIS as $jenis => $label) {
            $col = self::FILE_COLUMNS[$jenis];
            if (empty($dokumen->$col) || ! Storage::disk(config('ppdb.berkas.storage_disk'))->exists($dokumen->$col)) {
                throw new InvalidArgumentException('Berkas '.$label.' wajib diunggah sebelum submit.');
            }
        }
    }

    protected function resolveEditablePendaftaran(User $user): Pendaftaran
    {
        $pendaftaran = $this->getPendaftaranByUser($user);
        if (! $pendaftaran) {
            throw new InvalidArgumentException('Mulai pendaftaran PPDB terlebih dahulu.');
        }

        if (! $this->canEditPendaftaran($pendaftaran)) {
            throw new InvalidArgumentException('Berkas tidak dapat diubah pada status pendaftaran saat ini.');
        }

        return $pendaftaran;
    }

    protected function canEditPendaftaran(Pendaftaran $pendaftaran): bool
    {
        return $pendaftaran->isEditable();
    }

    protected function assertAllowedFile(UploadedFile $file, string $jenis): void
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $allowedExt = config('ppdb.berkas.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png']);

        if (! in_array($ext, $allowedExt, true)) {
            throw new InvalidArgumentException(
                'Format '.self::JENIS[$jenis].' harus: '.implode(', ', $allowedExt).'.'
            );
        }

        $mime = $file->getMimeType();
        $allowedMimes = config('ppdb.berkas.allowed_mimes', []);
        if (empty($allowedMimes)) {
            $allowedMimes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/jpg',
            ];
        }
        if ($mime && ! in_array($mime, $allowedMimes, true)) {
            throw new InvalidArgumentException('Tipe file tidak valid atau tidak aman.');
        }

        $maxBytes = (int) config('ppdb.berkas.max_size_kb', 2048) * 1024;
        if ($file->getSize() > $maxBytes) {
            $maxMb = round($maxBytes / 1024 / 1024, 1);
            throw new InvalidArgumentException('Ukuran file maksimal '.$maxMb.' MB.');
        }
    }

    protected function formatBerkasItem(string $jenis, string $label, ?string $filePath, ?PendaftaranDokumen $row): array
    {
        if (! $filePath) {
            return [
                'jenis_berkas' => $jenis,
                'label' => $label,
                'status' => 'belum_upload',
                'status_verifikasi' => null,
                'url' => null,
                'preview_url' => null,
                'file_name' => null,
                'file_size' => null,
                'mime_type' => null,
                'catatan' => null,
                'uploaded_at' => null,
            ];
        }

        $disk = config('ppdb.berkas.storage_disk', 'public');
        $url = self::documentUrl($row->pendaftaran_id, $jenis);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return [
            'id' => $row ? $row->id : null,
            'jenis_berkas' => $jenis,
            'label' => $label,
            'status' => 'valid',
            'status_verifikasi' => 'valid',
            'url' => $url,
            'preview_url' => $url,
            'file_name' => basename($filePath),
            'file_size' => Storage::disk($disk)->exists($filePath)
                ? Storage::disk($disk)->size($filePath)
                : null,
            'mime_type' => $this->mimeFromExtension($ext),
            'catatan' => $row ? $row->catatan_dokumen : null,
            'uploaded_at' => $row ? $row->updated_at?->toIso8601String() : null,
        ];
    }

    protected function buildPlaceholderItems(): array
    {
        $items = [];
        foreach (self::JENIS as $key => $label) {
            $items[] = $this->formatBerkasItem($key, $label, null, null);
        }

        return $items;
    }

    protected function mimeFromExtension(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    protected function getPendaftaranByUser(User $user): ?Pendaftaran
    {
        return Pendaftaran::where('id_user', $user->id_user)->first();
    }

    public static function documentUrl(int $registrationId, string $jenis): string
    {
        return URL::temporarySignedRoute('ppdb.documents.show', now()->addMinutes(30), [
            'pendaftaran' => $registrationId,
            'jenis' => $jenis,
        ]);
    }
}
