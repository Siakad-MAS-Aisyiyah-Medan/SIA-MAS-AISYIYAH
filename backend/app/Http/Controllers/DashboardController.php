<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalGuru = User::where('role', 'guru')->count();
        $totalMurid = User::where('role', 'siswa')->count();
        $totalMapel = Mapel::count();
        $totalKelas = Kelas::count();
        if (auth()->user()->role === 'guru') {
            $mapelIds = Mapel::where('id_guru', auth()->id())->pluck('id_mapel');
            $kelasIds = DB::table('kelas_mapel')->whereIn('id_mapel', $mapelIds)->distinct()->pluck('id_kelas');
            $totalMurid = Siswa::aktif()->whereIn('id_kelas', $kelasIds)->count();
            $totalMapel = $mapelIds->count();
            $totalKelas = $kelasIds->count();
        }

        $auditLogs = AuditLog::with('user:id_user,name,role')
            ->when(auth()->user()->role !== 'admin', fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => optional($log->user)->name ?? 'System',
                    'target' => $log->description,
                    'time' => $log->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_guru' => $totalGuru,
                    'total_murid' => $totalMurid,
                    'total_mapel' => $totalMapel,
                    'total_kelas' => $totalKelas,
                ],
                'audit_logs' => $auditLogs,
            ],
        ]);
    }
}
