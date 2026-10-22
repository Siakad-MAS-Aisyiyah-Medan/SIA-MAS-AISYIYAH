<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('must_change_password')->default(false));
        foreach (DB::table('users')->select('id_user', 'password')->cursor() as $user) {
            foreach (['password', 'admin123', '12345678'] as $default) {
                if (Hash::check($default, $user->password)) {
                    DB::table('users')->where('id_user', $user->id_user)->update(['must_change_password' => true]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('must_change_password'));
    }
};
