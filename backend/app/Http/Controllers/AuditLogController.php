<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Services\AuditLogService;
use App\Utils\ApiResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer',
        ]);

        $paginator = app(AuditLogService::class)->list(
            null,
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 20)
        );

        $paginator->getCollection()->transform(
            fn ($row) => (new AuditLogResource($row))->resolve()
        );

        return ApiResponse::paginated($paginator, 'Berhasil mengambil audit log');
    }
}
