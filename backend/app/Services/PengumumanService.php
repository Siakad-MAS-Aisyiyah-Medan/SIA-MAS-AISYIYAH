<?php

namespace App\Services;

use App\Http\Resources\PengumumanResource;
use App\Models\Pengumuman;
use App\Utils\AuditsAdminActions;
use App\Utils\SearchInput;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PengumumanService
{
    use AuditsAdminActions;

    public function list(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Pengumuman::query()->with('penulis')->orderByDesc('tanggal_publikasi')->orderByDesc('id');

        if ($term = SearchInput::escape($search)) {
            $query->where(function ($q) use ($term) {
                $q->where('judul', 'like', "%{$term}%")
                    ->orWhere('isi', 'like', "%{$term}%");
            });
        }

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(
            fn ($item) => (new PengumumanResource($item))->resolve()
        );

        return $paginator;
    }

    public function find(int $id): array
    {
        return (new PengumumanResource(Pengumuman::with('penulis')->findOrFail($id)))->resolve();
    }

    public function create(array $data): array
    {
        if (isset($data['thumbnail']) && $data['thumbnail'] instanceof UploadedFile) {
            $path = $data['thumbnail']->store('pengumuman', 'public');
            $data['thumbnail'] = '/storage/'.$path;
        }

        $data['tanggal_publikasi'] = $data['tanggal_publikasi'] ?? now()->toDateString();
        $data['penulis_id'] = auth()->id();
        $item = Pengumuman::create($data);
        $this->auditAdmin('pengumuman.create', $item, ['judul' => $item->judul]);

        return (new PengumumanResource($item))->resolve();
    }

    public function processUpdate(int $id, array $data): array
    {
        $item = Pengumuman::findOrFail($id);

        if (isset($data['thumbnail']) && $data['thumbnail'] instanceof UploadedFile) {
            if ($item->thumbnail) {
                $oldPath = str_replace('/storage/', '', $item->thumbnail);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $data['thumbnail']->store('pengumuman', 'public');
            $data['thumbnail'] = '/storage/'.$path;
        }

        $item->update($data);
        $this->auditAdmin('pengumuman.update', $item, ['judul' => $item->judul]);

        return (new PengumumanResource($item->fresh()))->resolve();
    }

    public function delete(int $id): void
    {
        $item = Pengumuman::findOrFail($id);
        $this->auditAdmin('pengumuman.delete', $item, ['judul' => $item->judul]);
        $item->delete();
    }
}
