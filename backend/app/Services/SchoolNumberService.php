<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SchoolNumberService
{
    public function next(string $key, string $prefix, string $table, string $column): string
    {
        return DB::transaction(function () use ($key, $prefix, $table, $column) {
            DB::table('school_number_sequences')->insertOrIgnore(['key' => $key, 'value' => 0]);
            $sequence = DB::table('school_number_sequences')->where('key', $key)->lockForUpdate()->first();
            $existing = DB::table($table)->where($column, 'like', $prefix.'%')->pluck($column)
                ->map(fn ($value) => (int) substr($value, strlen($prefix)))->max() ?? 0;
            $next = max((int) $sequence->value, $existing) + 1;
            DB::table('school_number_sequences')->where('key', $key)->update(['value' => $next]);

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
