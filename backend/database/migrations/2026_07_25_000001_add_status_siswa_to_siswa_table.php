<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('status_siswa', 20)
                ->default('aktif')
                ->after('tahun_lulus');
            $table->timestamp('status_diubah_pada')
                ->nullable()
                ->after('status_siswa');
            $table->index(['status_siswa', 'id_kelas'], 'siswa_status_kelas_index');
        });

        DB::table('siswa')
            ->whereNotNull('tahun_lulus')
            ->update([
                'status_siswa' => 'lulus',
                'status_diubah_pada' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropIndex('siswa_status_kelas_index');
            $table->dropColumn(['status_siswa', 'status_diubah_pada']);
        });
    }
};
