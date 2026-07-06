<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keyboard;
use App\Services\KeyboardValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function destroy(Keyboard $keyboard): JsonResponse
    {
        if ($keyboard->quizzes()->exists()) {
            return $this->jsonError(
                'Không thể xóa bàn phím đang được quiz sử dụng. Gán quiz sang bàn phím khác trước.',
                409
            );
        }

        $keyboard->delete();

        return $this->jsonSuccess(['deleted' => true]);
    }
}
