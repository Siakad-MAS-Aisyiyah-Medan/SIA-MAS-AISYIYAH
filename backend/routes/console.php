<?php

use App\Models\Admin;
use App\Models\PendaftaranDokumen;
use App\Models\User;
use App\Services\PpdbBerkasService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

Artisan::command('siakad:create-admin {username} {--name=Administrator}', function () {
    $username = trim($this->argument('username'));
    if ($username === '' || User::where('username', $username)->exists()) {
        $this->error('Username kosong atau sudah digunakan.');

        return self::FAILURE;
    }
    $password = $this->secret('Password administrator (minimal 12 karakter)');
    if (strlen((string) $password) < 12 || $password !== $this->secret('Ulangi password')) {
        $this->error('Password terlalu pendek atau konfirmasi tidak sama.');

        return self::FAILURE;
    }
    DB::transaction(function () use ($username, $password) {
        $user = User::create(['username' => $username, 'name' => $this->option('name'), 'password' => $password, 'role' => 'admin', 'status_aktif' => true, 'status_akun' => 'aktif']);
        $user->admin()->create(['nip' => 'ADMIN-'.$user->id_user, 'nama_admin' => $user->name, 'no_hp' => '-']);
    });
    $this->info('Administrator berhasil dibuat.');

    return self::SUCCESS;
})->purpose('Buat administrator tanpa akun atau password bawaan');

Artisan::command('ppdb:secure-files', function () {
    $private = Storage::disk('local');
    $public = Storage::disk('public');
    $moved = 0;
    $missing = 0;
    foreach (PendaftaranDokumen::cursor() as $document) {
        foreach (PpdbBerkasService::FILE_COLUMNS as $column) {
            $path = $document->{$column};
            if (! $path) {
                continue;
            }
            if ($public->exists($path)) {
                if ($private->exists($path) && hash_file('sha256', $public->path($path)) !== hash_file('sha256', $private->path($path))) {
                    throw new RuntimeException('Salinan publik dan privat berbeda. Periksa backup sebelum memindahkan berkas.');
                }
                $stream = $public->readStream($path);
                try {
                    if (! $private->put($path, $stream)) {
                        throw new RuntimeException('Gagal menyalin berkas PPDB.');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                if (hash_file('sha256', $public->path($path)) !== hash_file('sha256', $private->path($path))) {
                    throw new RuntimeException('Verifikasi salinan berkas PPDB gagal.');
                }
                $public->delete($path);
                $moved++;
            } elseif (! $private->exists($path)) {
                $missing++;
            }
        }
    }
    $this->info("Berkas dipindahkan ke penyimpanan privat: {$moved}; referensi berkas hilang: {$missing}.");

    return $missing ? self::FAILURE : self::SUCCESS;
})->purpose('Amankan unggahan PPDB lama dengan salinan dan verifikasi hash sebelum menghapus salinan publik');

Artisan::command('siakad:restore-admin {login=admin} {--password=} {--force-password}', function () {
    $login = trim((string) $this->argument('login'));

    $user = User::query()
        ->where('username', $login)
        ->orWhere('email', $login)
        ->first();

    if (! $user) {
        $this->error("User '{$login}' tidak ditemukan.");

        return self::FAILURE;
    }

    $user->forceFill([
        'role' => 'admin',
        'status_akun' => 'aktif',
        'status_aktif' => true,
    ]);

    $password = $this->option('password');
    if ($password !== null && $password !== '') {
        if (! $this->option('force-password') && strlen((string) $password) < 8) {
            $this->error('Password minimal 8 karakter. Gunakan --force-password hanya untuk kondisi darurat.');

            return self::FAILURE;
        }

        $user->password = Hash::make((string) $password);
    }

    $user->save();

    Admin::updateOrCreate(
        ['id_user' => $user->id_user],
        [
            'id_user' => $user->id_user,
            'nip' => $user->admin?->nip ?: 'ADMIN-'.$user->id_user,
            'nama_admin' => $user->admin?->nama_admin ?: ($user->name ?: $user->username),
            'no_hp' => $user->admin?->no_hp ?: '-',
        ]
    );

    $message = "Akun '{$user->username}' berhasil dipulihkan sebagai administrator aktif.";
    if ($password !== null && $password !== '') {
        $message .= ' Password juga sudah direset.';
    }

    $this->info($message);

    return self::SUCCESS;
})->purpose('Pulihkan akun administrator produksi yang role/status-nya berubah');

Artisan::command('siakad:reset-password {login} {password} {--role=} {--force-password}', function () {
    $login = trim((string) $this->argument('login'));
    $password = (string) $this->argument('password');
    $role = $this->option('role') !== null && $this->option('role') !== ''
        ? (string) $this->option('role')
        : null;

    if (! $this->option('force-password') && strlen($password) < 8) {
        $this->error('Password minimal 8 karakter. Gunakan --force-password hanya untuk kondisi darurat.');

        return self::FAILURE;
    }

    if ($role !== null && ! in_array($role, User::ROLES, true)) {
        $this->error('Role tidak valid. Gunakan salah satu: '.implode(', ', User::ROLES).'.');

        return self::FAILURE;
    }

    $compact = preg_replace('/[\s\-.]+/', '', $login);
    $loginCandidates = [$login];
    if ($compact !== null && $compact !== '') {
        $loginCandidates[] = $compact;

        if (ctype_digit($compact)) {
            $withoutLeadingZero = ltrim($compact, '0') ?: '0';
            $loginCandidates[] = $withoutLeadingZero;
            $loginCandidates[] = str_pad($withoutLeadingZero, 10, '0', STR_PAD_LEFT);
        }
    }
    $loginCandidates = array_values(array_unique($loginCandidates));

    $users = User::query()
        ->with(['admin', 'kepalaSekolah', 'guru', 'siswa'])
        ->when($role, fn ($query) => $query->where('role', $role))
        ->where(function ($query) use ($login, $loginCandidates) {
            $query->whereIn('username', $loginCandidates)
                ->orWhere('email', $login)
                ->orWhereHas('admin', fn ($admin) => $admin->whereIn('nip', $loginCandidates))
                ->orWhereHas('kepalaSekolah', fn ($kepsek) => $kepsek->whereIn('nip', $loginCandidates))
                ->orWhereHas('guru', fn ($guru) => $guru->whereIn('nip', $loginCandidates))
                ->orWhereHas('siswa', fn ($siswa) => $siswa
                    ->whereIn('nisn', $loginCandidates)
                    ->orWhereIn('nis', $loginCandidates));
        })
        ->get();

    if ($users->isEmpty()) {
        $this->error("Akun '{$login}' tidak ditemukan.");

        return self::FAILURE;
    }

    if ($users->count() > 1 && $role === null) {
        $this->error("Ditemukan lebih dari satu akun untuk '{$login}'. Jalankan ulang dengan --role=admin|kepsek|guru|siswa|calon_siswa.");
        foreach ($users as $match) {
            $this->line("- {$match->username} | {$match->name} | {$match->role}");
        }

        return self::FAILURE;
    }

    /** @var User $user */
    $user = $users->first();
    $user->forceFill([
        'password' => Hash::make($password),
        'status_akun' => 'aktif',
        'status_aktif' => true,
    ])->save();

    $this->info("Password akun '{$user->username}' ({$user->name}, role {$user->role}) berhasil direset dan akun diaktifkan.");

    return self::SUCCESS;
})->purpose('Reset password akun berdasarkan username, email, NIP, NISN, atau NIS');
