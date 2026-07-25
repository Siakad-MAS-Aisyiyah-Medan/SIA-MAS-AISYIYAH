<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skema_penilaian', function (Blueprint $table) {
            $table->bigIncrements('id_skema');
            $table->unsignedBigInteger('id_guru');
            $table->unsignedBigInteger('id_mapel');
            $table->unsignedBigInteger('id_kelas');
            $table->string('tahun_ajaran', 20);
            $table->string('semester', 10);
            $table->string('nama_skema', 100)->default('Skema Penilaian');
            $table->string('status', 20)->default('aktif');
            $table->unsignedInteger('versi')->default(1);
            $table->timestamps();

            $table->unique(
                ['id_guru', 'id_mapel', 'id_kelas', 'tahun_ajaran', 'semester'],
                'skema_penilaian_context_unique'
            );
            $table->foreign('id_guru')->references('id_user')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_mapel')->references('id_mapel')->on('mata_pelajaran')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_kelas')->references('id_kelas')->on('kelas')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->bigIncrements('id_komponen');
            $table->unsignedBigInteger('id_skema');
            $table->string('nama_komponen', 60);
            $table->string('kode_komponen', 80);
            $table->decimal('bobot', 5, 2);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['id_skema', 'is_active', 'urutan'], 'komponen_skema_active_index');
            $table->foreign('id_skema')->references('id_skema')->on('skema_penilaian')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('nilai_komponen_siswa', function (Blueprint $table) {
            $table->bigIncrements('id_nilai_komponen');
            $table->unsignedBigInteger('id_komponen');
            $table->unsignedBigInteger('id_user_siswa');
            $table->decimal('nilai', 5, 2);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['id_komponen', 'id_user_siswa'], 'nilai_komponen_siswa_unique');
            $table->foreign('id_komponen')->references('id_komponen')->on('komponen_penilaian')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('id_user_siswa')->references('id_user')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('nilai', function (Blueprint $table) {
            $table->unsignedBigInteger('id_skema')->nullable()->after('id_guru_input');
            $table->index('id_skema', 'nilai_id_skema_index');
            $table->foreign('id_skema')->references('id_skema')->on('skema_penilaian')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nilai', function (Blueprint $table) {
            $table->dropForeign(['id_skema']);
            $table->dropIndex('nilai_id_skema_index');
            $table->dropColumn('id_skema');
        });

        Schema::dropIfExists('nilai_komponen_siswa');
        Schema::dropIfExists('komponen_penilaian');
        Schema::dropIfExists('skema_penilaian');
    }
};
