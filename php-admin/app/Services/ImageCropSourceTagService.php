<?php

namespace App\Services;

use App\Models\ImageCropSource;
use App\Models\Tag;
use Illuminate\Support\Collection;

class ImageCropSourceTagService
{
    /**
     * @param  list<int|string>  $tagIds
     * @return Collection<int, Tag>
     */
    public function syncFromIds(ImageCropSource $source, array $tagIds): Collection
    {
        $ids = collect($tagIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $source->tags()->sync($ids);

        return $source->tags()->orderBy('name')->get();
    }
}
