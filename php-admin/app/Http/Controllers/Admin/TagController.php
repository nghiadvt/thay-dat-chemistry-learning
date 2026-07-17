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
        return $this->indexForScope('content');
    }

    public function indexGroups(): JsonResponse
    {
        return $this->indexForScope('image_crop');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->storeForScope($request, 'content');
    }

    public function storeGroup(Request $request): JsonResponse
    {
        return $this->storeForScope($request, 'image_crop');
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', Rule::unique('tags', 'name')->where('scope', $tag->scope)->ignore($tag->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $result = $this->persistTag($validated, $tag->scope, $tag);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'success' => true,
            'data' => $this->tagPayload($result->fresh()),
        ]);
    }

    private function indexForScope(string $scope): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Tag::query()->where('scope', $scope)->orderBy('name')->get()->map(fn (Tag $tag) => $this->tagPayload($tag)),
        ]);
    }

    private function storeForScope(Request $request, string $scope): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', Rule::unique('tags', 'name')->where('scope', $scope)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $result = $this->persistTag($validated, $scope);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'success' => true,
            'data' => $this->tagPayload($result),
        ], 201);
    }

    /**
     * @param  array{name: string, color: string}  $validated
     */
    private function persistTag(array $validated, string $scope, ?Tag $tag = null): Tag|JsonResponse
    {
        $name = trim($validated['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'tag-'.substr(md5(mb_strtolower($name)), 0, 12);
        }

        $slugConflict = Tag::query()
            ->where('scope', $scope)
            ->where('slug', $slug)
            ->when($tag, fn ($query) => $query->where('id', '!=', $tag->id))
            ->exists();

        if ($slugConflict) {
            return response()->json([
                'success' => false,
                'error' => 'Chủ đề này đã tồn tại.',
            ], 422);
        }

        $attributes = [
            'name' => $name,
            'slug' => $slug,
            'color' => strtoupper($validated['color']),
            'scope' => $scope,
        ];

        if ($tag) {
            $tag->update($attributes);

            return $tag;
        }

        return Tag::create($attributes);
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
