<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Tag::query()->orderBy('name')->get()->map(fn (Tag $tag) => $this->tagPayload($tag)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:tags,name'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $name = trim($validated['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'tag-'.substr(md5(mb_strtolower($name)), 0, 12);
        }

        if (Tag::query()->where('slug', $slug)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Chủ đề này đã tồn tại.',
            ], 422);
        }

        $tag = Tag::create([
            'name' => $name,
            'slug' => $slug,
            'color' => strtoupper($validated['color']),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->tagPayload($tag),
        ], 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', Rule::unique('tags', 'name')->ignore($tag->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $name = trim($validated['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'tag-'.substr(md5(mb_strtolower($name)), 0, 12);
        }

        if (Tag::query()->where('slug', $slug)->where('id', '!=', $tag->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Chủ đề này đã tồn tại.',
            ], 422);
        }

        $tag->update([
            'name' => $name,
            'slug' => $slug,
            'color' => strtoupper($validated['color']),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->tagPayload($tag->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tagPayload(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
            'text_color' => $tag->text_color,
        ];
    }
}
