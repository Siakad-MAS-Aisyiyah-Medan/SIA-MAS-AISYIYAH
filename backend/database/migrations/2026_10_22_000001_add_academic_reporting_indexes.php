<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', fn (Blueprint $table) => $table->index(['id_kelas', 'id_mapel', 'tanggal'], 'absensi_kelas_mapel_tanggal_index'));
        Schema::table('nilai', fn (Blueprint $table) => $table->index(['tahun_ajaran', 'semester', 'id_mapel'], 'nilai_periode_mapel_index'));
        Schema::table('pendaftaran', fn (Blueprint $table) => $table->index(['ppdb_status', 'updated_at'], 'pendaftaran_status_updated_index'));
        Schema::table('siswa', fn (Blueprint $table) => $table->index(['id_kelas', 'status_siswa'], 'siswa_kelas_status_index'));
    }

    public function down(): void
    {
        Schema::table('absensi', fn (Blueprint $table) => $table->dropIndex('absensi_kelas_mapel_tanggal_index'));
        Schema::table('nilai', fn (Blueprint $table) => $table->dropIndex('nilai_periode_mapel_index'));
        Schema::table('pendaftaran', fn (Blueprint $table) => $table->dropIndex('pendaftaran_status_updated_index'));
        Schema::table('siswa', fn (Blueprint $table) => $table->dropIndex('siswa_kelas_status_index'));
    }
};
