<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function __construct(private PermissionService $permissions) {}

    public function logAdminAction(string $action, ?Model $subject = null, array $meta = []): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        AuditLog::create([
            'id_user' => $user->id_user,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'meta' => $meta ?: null,
        ]);

    }

    public function list(?string $action = null, ?string $search = null, int $perPage = 1000)
    {
        $query = AuditLog::with('user')->orderByDesc('id');

        if ($action) {
            $query->where('action', $action);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($perPage);
    }
}
