<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_user' => $this->id_user,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->displayEmail(),
            'role' => $this->role,
            'status_aktif' => (bool) $this->status_aktif,
            'must_change_password' => (bool) $this->must_change_password,
            'status_akun' => $this->status_akun ?? (($this->status_aktif ?? true) ? 'aktif' : 'nonaktif'),
            'last_login_at' => $this->last_login_at,
            'has_ppdb_registration' => $this->role === 'calon_siswa' ? $this->hasPpdbRegistration() : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
