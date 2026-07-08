<?php

namespace App\Services;

use App\Models\QuestionBankItem;
use App\Models\Tag;
use Illuminate\Support\Collection;

class QuestionBankTagService
{
    /**
     * @param  list<int|string>  $tagIds
     * @return Collection<int, Tag>
     */
    public function syncFromIds(QuestionBankItem $item, array $tagIds): Collection
    {
        $ids = collect($tagIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $item->tags()->sync($ids);

        return $item->tags()->orderBy('name')->get();
    }
}
