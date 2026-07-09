<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteFeedback;
use App\Models\SiteFeedbackAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteFeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'priority' => ['required', 'in:low,medium,high'],
            'page_url' => ['required', 'string', 'max:512', 'regex:#^/admin#'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $feedback = DB::transaction(function () use ($request, $validated) {
            $feedback = SiteFeedback::create([
                'user_id' => $request->user()->id,
                'page_url' => $validated['page_url'],
                'page_title' => $validated['page_title'] ?? null,
                'body' => $validated['body'],
                'priority' => $validated['priority'],
                'status' => 'new',
            ]);

            foreach ($request->file('images', []) as $file) {
                $name = now()->format('Ymd-His').'-'.Str::random(8).'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('feedback', $name, 'public');

                if ($path === false) {
                    continue;
                }

                SiteFeedbackAttachment::create([
                    'site_feedback_id' => $feedback->id,
                    'path' => $path,
                    'mime_type' => $file->getClientMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                ]);
            }

            return $feedback->load('attachments');
        });

        return $this->jsonSuccess([
            'id' => $feedback->id,
            'created_at' => $feedback->created_at?->toIso8601String(),
        ], 201);
    }
}
