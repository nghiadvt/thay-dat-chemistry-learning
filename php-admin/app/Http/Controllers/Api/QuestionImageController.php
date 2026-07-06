<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $file = $validated['upload'];
        $name = now()->format('Ymd-His').'-'.Str::random(8).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('questions', $name, 'public');

        if ($path === false) {
            return $this->jsonError('Không lưu được ảnh.', 500);
        }

        return response()->json([
            'url' => url(Storage::disk('public')->url($path)),
        ]);
    }
}
