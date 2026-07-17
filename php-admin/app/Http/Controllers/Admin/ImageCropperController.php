<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImageCropSource;
use App\Models\Tag;
use App\Services\ImageCropSourceTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ImageCropperController extends Controller
{
    private const PER_PAGE = 15;

    private const OTHER_GROUP_COLOR = '#9CA3AF';

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $sort = $request->input('sort', 'updated_desc');
        $updatedFrom = $request->input('updated_from');
        $updatedTo = $request->input('updated_to');

        $sourcesQuery = ImageCropSource::query()
            ->withCount('regions')
            ->with(['tags:id,name,color']);

        if ($updatedFrom) {
            $sourcesQuery->whereDate('updated_at', '>=', $updatedFrom);
        }
        if ($updatedTo) {
            $sourcesQuery->whereDate('updated_at', '<=', $updatedTo);
        }

        $allSources = $sourcesQuery->get();
        $groupTags = Tag::query()->where('scope', 'image_crop')->orderBy('name')->get();

        $groups = collect();

        foreach ($groupTags as $tag) {
            $members = $allSources->filter(fn (ImageCropSource $s) => $s->tags->contains('id', $tag->id))->values();
            if ($members->isEmpty()) {
                continue;
            }
            $groups->push([
                'tag' => $tag,
                'sources' => $members,
                'updated_at' => $members->max('updated_at'),
            ]);
        }

        $others = $allSources->filter(fn (ImageCropSource $s) => $s->tags->isEmpty())->values();
        if ($others->isNotEmpty()) {
            $groups->push([
                'tag' => new Tag(['name' => 'Khác', 'color' => self::OTHER_GROUP_COLOR]),
                'sources' => $others,
                'updated_at' => $others->max('updated_at'),
            ]);
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $groups = $groups->filter(fn (array $g) => str_contains(mb_strtolower($g['tag']->name), $needle))->values();
        }

        $groups = (match ($sort) {
            'updated_asc' => $groups->sortBy('updated_at'),
            'name_asc' => $groups->sortBy(fn (array $g) => mb_strtolower($g['tag']->name)),
            'name_desc' => $groups->sortByDesc(fn (array $g) => mb_strtolower($g['tag']->name)),
            default => $groups->sortByDesc('updated_at'),
        })->values();

        $page = max(1, (int) $request->input('page', 1));
        $groupsPage = new LengthAwarePaginator(
            $groups->forPage($page, self::PER_PAGE)->values(),
            $groups->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.image-cropper.index', [
            'groups' => $groupsPage,
            'allGroupTags' => $groupTags,
            'search' => $search,
            'sort' => $sort,
            'updatedFrom' => $updatedFrom,
            'updatedTo' => $updatedTo,
        ]);
    }

    public function create(): View
    {
        return view('admin.image-cropper.workspace', [
            'sourceBoot' => null,
        ]);
    }

    public function edit(ImageCropSource $imageCropper): View
    {
        $regions = $imageCropper->regions()->get();

        $sourceBoot = [
            'id' => $imageCropper->id,
            'name' => $imageCropper->name,
            'image_url' => $imageCropper->image_url,
            'regions' => $regions->map(fn ($region) => [
                'id' => $region->id,
                'label' => $region->label,
                'x' => $region->x,
                'y' => $region->y,
                'w' => $region->w,
                'h' => $region->h,
                'rotation' => $region->rotation,
                'flipped' => $region->flipped,
                'position' => $region->position,
                'url' => $region->cropped_url,
            ])->values(),
        ];

        return view('admin.image-cropper.workspace', compact('sourceBoot'));
    }

    public function updateGroups(Request $request, ImageCropSource $imageCropper, ImageCropSourceTagService $tagService): JsonResponse
    {
        $validated = $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where('scope', 'image_crop')],
        ]);

        $tagService->syncFromIds($imageCropper, $validated['tag_ids'] ?? []);
        $imageCropper->load('tags');

        return response()->json([
            'success' => true,
            'data' => [
                'tag_ids' => $imageCropper->tags->pluck('id'),
                'tags' => $imageCropper->tags->map(fn (Tag $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'color' => $t->color,
                ]),
            ],
        ]);
    }

    public function destroy(ImageCropSource $imageCropper): RedirectResponse
    {
        if ($imageCropper->folder) {
            Storage::disk('public')->deleteDirectory($imageCropper->folder);
        }

        $imageCropper->delete();

        return redirect()->route('admin.image-cropper.index')
            ->with('success', 'Đã xóa ảnh.');
    }
}
