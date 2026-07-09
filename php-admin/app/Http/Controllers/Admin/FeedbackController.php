<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = SiteFeedback::query()
            ->with(['user.role', 'attachments'])
            ->latest();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($priority = $request->string('priority')->toString()) {
            if (in_array($priority, ['low', 'medium', 'high'], true)) {
                $query->where('priority', $priority);
            }
        }

        if ($status = $request->string('status')->toString()) {
            if (in_array($status, ['new', 'read', 'done'], true)) {
                $query->where('status', $status);
            }
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('body', 'like', '%'.$search.'%')
                    ->orWhere('page_url', 'like', '%'.$search.'%')
                    ->orWhere('page_title', 'like', '%'.$search.'%');
            });
        }

        $feedback = $query->paginate(20)->withQueryString();

        return view('admin.feedback.index', [
            'feedback' => $feedback,
            'isAdmin' => $user->isAdmin(),
            'search' => $search,
        ]);
    }

    public function show(Request $request, SiteFeedback $feedback): View
    {
        $user = $request->user();

        if (! $user->isAdmin() && $feedback->user_id !== $user->id) {
            abort(403);
        }

        if ($user->isAdmin() && $feedback->status === 'new') {
            $feedback->update(['status' => 'read']);
        }

        $feedback->load(['user.role', 'attachments']);

        return view('admin.feedback.show', [
            'item' => $feedback,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function updateStatus(Request $request, SiteFeedback $feedback): RedirectResponse
    {
        if (! $request->user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:new,read,done'],
        ]);

        $feedback->update(['status' => $validated['status']]);

        return redirect()
            ->route('admin.feedback.show', $feedback)
            ->with('success', 'Đã cập nhật trạng thái góp ý.');
    }
}
