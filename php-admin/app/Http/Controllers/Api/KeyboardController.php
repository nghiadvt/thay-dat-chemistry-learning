<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keyboard;
use App\Services\KeyboardValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KeyboardController extends Controller
{
    public function __construct(
        private KeyboardValidator $validator,
    ) {}

    public function index(): JsonResponse
    {
        $keyboards = Keyboard::query()
            ->orderBy('name')
            ->get();

        return $this->jsonSuccess(['keyboards' => $keyboards]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:64'],
            'config' => ['required', 'array'],
        ]);

        $config = $this->validator->normalizeConfig($validated['config']);
        $issues = $this->validator->validate($config);
        if ($issues !== []) {
            return $this->jsonError('Cấu hình bàn phím không hợp lệ.', 422, ['issues' => $issues]);
        }

        $keyboard = Keyboard::create([
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? 'chemistry',
            'config' => $config,
        ]);

        return $this->jsonSuccess(['keyboard' => $keyboard], 201);
    }

    public function show(Keyboard $keyboard): JsonResponse
    {
        return $this->jsonSuccess(['keyboard' => $keyboard]);
    }

    public function update(Request $request, Keyboard $keyboard): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:64'],
            'config' => ['sometimes', 'required', 'array'],
        ]);

        if (isset($validated['config'])) {
            $config = $this->validator->normalizeConfig($validated['config']);
            $issues = $this->validator->validate($config);
            if ($issues !== []) {
                return $this->jsonError('Cấu hình bàn phím không hợp lệ.', 422, ['issues' => $issues]);
            }
            $validated['config'] = $config;
        }

        $keyboard->update($validated);

        return $this->jsonSuccess(['keyboard' => $keyboard->fresh()]);
    }

    public function uploadPreview(Request $request, Keyboard $keyboard): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'string'],
        ]);

        if (! preg_match('/^data:image\/png;base64,(.+)$/s', $validated['image'], $matches)) {
            return $this->jsonError('Ảnh preview phải là PNG base64.', 422);
        }

        $binary = base64_decode($matches[1], true);
        if ($binary === false || strlen($binary) > 2 * 1024 * 1024) {
            return $this->jsonError('Ảnh preview không hợp lệ hoặc quá lớn (tối đa 2MB).', 422);
        }

        $path = $keyboard->buildPreviewStoragePath();
        $written = Storage::disk('public')->put($path, $binary);
        if ($written === false) {
            return $this->jsonError('Không ghi được file preview. Kiểm tra quyền thư mục storage/app/public.', 500);
        }

        if ($keyboard->preview_path && $keyboard->preview_path !== $path) {
            Storage::disk('public')->delete($keyboard->preview_path);
        }

        $keyboard->update(['preview_path' => $path]);

        return $this->jsonSuccess([
            'keyboard' => $keyboard->fresh(),
            'preview_url' => $keyboard->preview_url,
        ]);
    }

    public function destroy(Keyboard $keyboard): JsonResponse
    {
        if ($keyboard->quizzes()->exists()) {
            return $this->jsonError(
                'Không thể xóa bàn phím đang được quiz sử dụng. Gán quiz sang bàn phím khác trước.',
                409
            );
        }

        if ($keyboard->preview_path) {
            Storage::disk('public')->delete($keyboard->preview_path);
        }

        $keyboard->delete();

        return $this->jsonSuccess(['deleted' => true]);
    }
}
