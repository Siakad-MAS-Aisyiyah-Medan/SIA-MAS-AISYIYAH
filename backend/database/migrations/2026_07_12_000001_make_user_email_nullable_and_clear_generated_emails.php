<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
        }

        DB::table('users')
            ->where(function ($query) {
                $query->where('email', 'like', '%@siswa.siakad.sch.id')
                    ->orWhere('email', 'like', '%@guru.siakad.sch.id')
                    ->orWhere('email', 'like', '%@mas.sch.id');
            })
            ->update(['email' => null]);
    }

    public function down(): void
    {
        // Email intentionally remains nullable; generated system email values are not restored.
    }
};
