<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\Tag;
use Illuminate\Support\Collection;

class QuizTagService
{
    /**
     * @return Collection<int, Tag>
     */
    public function syncFromInput(Quiz $quiz, ?string $tagsInput): Collection
    {
        $names = collect(preg_split('/[,;]+/', $tagsInput ?? '') ?: [])
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name));

        $tags = $names->map(fn (string $name) => Tag::findOrCreateFromName($name));
        $quiz->tags()->sync($tags->pluck('id'));

        return $tags;
    }
}
