<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use App\Services\CardTemplateStorageService;
use App\Support\CardFonts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class CardTemplateController extends Controller
{
    public function __construct(
        private CardTemplateStorageService $storage,
    ) {}

    public function index(Request $request): View
    {
        $templates = CardTemplate::query()
            ->visibleTo($request->user())
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.card-templates.index', compact('templates'));
    }

    public function create(Request $request): View
    {
        return view('admin.card-templates.editor', [
            'template' => null,
            'templateBoot' => $this->bootPayload(null),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        try {
            $template = DB::transaction(function () use ($request, $validated) {
                $layout = $this->storage->normalizeLayout($validated['layout'], (int) $validated['sides']);

                $template = CardTemplate::create([
                    'teacher_id' => $request->user()->id,
                    'name' => $validated['name'],
                    'sides' => $validated['sides'],
                    'frame_width_mm' => $validated['frame_width_mm'],
                    'frame_height_mm' => $validated['frame_height_mm'],
                    'layout' => $layout,
                ]);

                $layout = $this->persistImages($template, $layout, $validated);

                $frontBaked = $layout['_front_baked_path'] ?? null;
                $backBaked = $layout['_back_baked_path'] ?? null;
                unset($layout['_front_baked_path'], $layout['_back_baked_path']);

                $template->update([
                    'layout' => $layout,
                    'front_baked_path' => $frontBaked,
                    'back_baked_path' => $backBaked,
                ]);

                return $template->fresh();
            });
        } catch (RuntimeException $e) {
            return $this->jsonError($e->getMessage(), 422);
        }

        return $this->jsonSuccess(['template' => $this->serialize($template)], 201);
    }

    public function edit(Request $request, CardTemplate $template): View
    {
        $this->assertOwned($request, $template);

        return view('admin.card-templates.editor', [
            'template' => $template,
            'templateBoot' => $this->bootPayload($template),
        ]);
    }

    public function update(Request $request, CardTemplate $template): JsonResponse
    {
        $this->assertOwned($request, $template);

        $validated = $this->validatePayload($request);
        $oldLayout = $template->layout ?? CardTemplate::defaultLayout();

        try {
            $template = DB::transaction(function () use ($template, $validated, $oldLayout) {
                $layout = $this->storage->normalizeLayout($validated['layout'], (int) $validated['sides']);

                $template->fill([
                    'name' => $validated['name'],
                    'sides' => $validated['sides'],
                    'frame_width_mm' => $validated['frame_width_mm'],
                    'frame_height_mm' => $validated['frame_height_mm'],
                    'layout' => $layout,
                ])->save();

                $layout = $this->persistImages($template, $layout, $validated, $oldLayout);

                $this->storage->purgeRemovedLayerFiles($oldLayout, $layout);

                $frontBaked = $layout['_front_baked_path'] ?? $template->front_baked_path;
                $backBaked = array_key_exists('_back_baked_path', $layout)
                    ? $layout['_back_baked_path']
                    : $template->back_baked_path;
                unset($layout['_front_baked_path'], $layout['_back_baked_path']);

                $template->update([
                    'layout' => $layout,
                    'front_baked_path' => $frontBaked,
                    'back_baked_path' => $backBaked,
                ]);

                return $template->fresh();
            });
        } catch (RuntimeException $e) {
            return $this->jsonError($e->getMessage(), 422);
        }

        return $this->jsonSuccess(['template' => $this->serialize($template)]);
    }

    public function destroy(Request $request, CardTemplate $template): JsonResponse
    {
        $this->assertOwned($request, $template);

        $this->storage->deleteTemplateFiles($template);
        $template->delete();

        return $this->jsonSuccess(['deleted' => true]);
    }

    /**
     * Render 1 thẻ mặt trước với dữ liệu mẫu (iframe xem trước).
     */
    public function preview(Request $request, CardTemplate $template): Response
    {
        $this->assertOwned($request, $template);

        $layout = $template->layout ?? CardTemplate::defaultLayout();
        $front = $layout['front'] ?? ['imageLayers' => [], 'elements' => []];
        $elements = $front['elements'] ?? [];

        $fontKeys = collect($elements)->pluck('fontFamily')->filter()->unique()->values()->all();

        $html = view('admin.card-templates.preview', [
            'elements' => $elements,
            'imageDataUri' => $this->storage->toDataUri($template->front_baked_path),
            'frameWidthMm' => (float) $template->frame_width_mm,
            'frameHeightMm' => (float) $template->frame_height_mm,
            'scaleK' => 1.0,
            'data' => CardFonts::sampleData(),
            'fontCss' => CardFonts::dompdfFontFaceCss($fontKeys),
        ])->render();

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function bootPayload(?CardTemplate $template): array
    {
        return [
            'id' => $template?->id,
            'name' => $template?->name ?? 'Mẫu thẻ mới',
            'sides' => $template?->sides ?? 1,
            'frame_width_mm' => (float) ($template?->frame_width_mm ?? 85.60),
            'frame_height_mm' => (float) ($template?->frame_height_mm ?? 53.98),
            'layout' => $template?->layout ?? CardTemplate::defaultLayout(),
            'front_baked_url' => $template?->front_baked_url,
            'back_baked_url' => $template?->back_baked_url,
            'fonts' => CardFonts::all(),
            'bindings' => CardFonts::dataBindings(),
            'sampleData' => CardFonts::sampleData(),
            'storageBaseUrl' => asset('storage'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CardTemplate $template): array
    {
        $layout = $template->layout ?? CardTemplate::defaultLayout();

        return [
            'id' => $template->id,
            'name' => $template->name,
            'sides' => $template->sides,
            'frame_width_mm' => (float) $template->frame_width_mm,
            'frame_height_mm' => (float) $template->frame_height_mm,
            'layout' => $layout,
            'front_baked_url' => $template->front_baked_url,
            'back_baked_url' => $template->back_baked_url,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sides' => ['required', 'integer', Rule::in([1, 2])],
            'frame_width_mm' => ['required', 'numeric', 'min:20', 'max:210'],
            'frame_height_mm' => ['required', 'numeric', 'min:20', 'max:297'],
            'layout' => ['required', 'array'],
            'layer_uploads' => ['nullable', 'array'],
            'layer_uploads.front' => ['nullable', 'array'],
            'layer_uploads.back' => ['nullable', 'array'],
            'front_baked' => ['nullable', 'string'],
            'back_baked' => ['nullable', 'string'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>|null  $oldLayout
     * @return array<string, mixed>
     */
    private function persistImages(CardTemplate $template, array $layout, array $validated, ?array $oldLayout = null): array
    {
        $uploads = $validated['layer_uploads'] ?? [];

        foreach (['front', 'back'] as $side) {
            if ($side === 'back' && (int) $validated['sides'] === 1) {
                continue;
            }

            $sideUploads = $uploads[$side] ?? [];
            if ($sideUploads !== []) {
                $this->storage->syncLayerUploads($template, $side, $sideUploads, $layout[$side]);
            }
        }

        $frontBaked = null;
        if (! empty($validated['front_baked'])) {
            $frontBaked = $this->storage->saveBakedSide($template, 'front', $validated['front_baked']);
        } elseif (($layout['front']['imageLayers'] ?? []) !== []) {
            $frontBaked = $this->storage->bakeSideFromLayers($template, 'front', $layout['front']);
        }
        if ($frontBaked) {
            if ($oldLayout && $template->front_baked_path && $template->front_baked_path !== $frontBaked) {
                $this->storage->deleteIfExists($template->front_baked_path);
            }
            $layout['_front_baked_path'] = $frontBaked;
        }

        $backBaked = null;
        if ((int) $validated['sides'] === 2) {
            if (! empty($validated['back_baked'])) {
                $backBaked = $this->storage->saveBakedSide($template, 'back', $validated['back_baked']);
            } elseif (($layout['back']['imageLayers'] ?? []) !== []) {
                $backBaked = $this->storage->bakeSideFromLayers($template, 'back', $layout['back']);
            }
            if ($backBaked) {
                if ($oldLayout && $template->back_baked_path && $template->back_baked_path !== $backBaked) {
                    $this->storage->deleteIfExists($template->back_baked_path);
                }
                $layout['_back_baked_path'] = $backBaked;
            }
        } elseif ($template->back_baked_path) {
            $this->storage->deleteIfExists($template->back_baked_path);
            $layout['_back_baked_path'] = null;
        }

        return $layout;
    }

    private function assertOwned(Request $request, CardTemplate $template): void
    {
        abort_unless(
            $request->user()->isAdmin() || $template->teacher_id === $request->user()->id,
            403,
            'Bạn không sở hữu mẫu thẻ này.'
        );
    }
}
