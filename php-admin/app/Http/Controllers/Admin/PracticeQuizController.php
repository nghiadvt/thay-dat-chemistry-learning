<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PracticeQuiz;
use App\Models\PracticeTopic;
use App\Models\QuestionBankItem;
use App\Models\StudentClass;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PracticeQuizController extends Controller
{
    public function store(Request $request, PracticeTopic $practiceTopic): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $sortOrder = (int) $practiceTopic->quizzes()->max('sort_order') + 1;

        $quiz = PracticeQuiz::create([
            'practice_topic_id' => $practiceTopic->id,
            'name' => $validated['name'],
            'sort_order' => $sortOrder,
            'is_active' => true,
            'requires_pro' => false,
        ]);

        return redirect()->route('admin.practice.quizzes.show', $quiz)
            ->with('success', 'Đã tạo bài. Thêm câu hỏi và lớp bên dưới.');
    }

    public function show(PracticeQuiz $practiceQuiz): View
    {
        $practiceQuiz->load(['topic.grade', 'questionBankItems.tags', 'studentClasses']);

        return view('admin.practice.quizzes.show', [
            'quiz' => $practiceQuiz,
            'bankTags' => Tag::query()->orderBy('name')->get(),
            'classes' => StudentClass::query()->orderBy('name')->get(),
            'selectedClassIds' => $practiceQuiz->studentClasses->pluck('id')->all(),
        ]);
    }

    /**
     * Sửa tên nhanh ngay trong danh sách (accordion trang chủ đề) — dùng chung
     * cho cả form sửa nhanh lẫn form "Lưu thông tin bài" ở trang biên soạn chi tiết.
     */
    public function update(Request $request, PracticeQuiz $practiceQuiz): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $practiceQuiz->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? $practiceQuiz->sort_order,
        ]);

        return $this->backPreservingTopic($request, $practiceQuiz, 'Đã cập nhật bài trắc nghiệm.');
    }

    public function toggleActive(Request $request, PracticeQuiz $practiceQuiz): RedirectResponse
    {
        $practiceQuiz->update(['is_active' => ! $practiceQuiz->is_active]);

        return $this->backPreservingTopic($request, $practiceQuiz, $practiceQuiz->is_active ? 'Đã bật bài.' : 'Đã tắt bài.');
    }

    public function togglePro(Request $request, PracticeQuiz $practiceQuiz): RedirectResponse
    {
        $practiceQuiz->update(['requires_pro' => ! $practiceQuiz->requires_pro]);

        return $this->backPreservingTopic(
            $request,
            $practiceQuiz,
            $practiceQuiz->requires_pro ? 'Bài đã yêu cầu tài khoản Pro.' : 'Đã bỏ yêu cầu Pro cho bài.'
        );
    }

    /**
     * Các form sửa nhanh trong accordion trang danh sách gắn kèm ?grade=&open_topic=
     * để quay lại đúng khối/chủ đề đang mở. Form ở trang biên soạn chi tiết không
     * gắn tham số này nên vẫn dùng back() như cũ, ở lại trang chi tiết.
     */
    private function backPreservingTopic(Request $request, PracticeQuiz $practiceQuiz, string $message): RedirectResponse
    {
        if ($request->filled('open_topic')) {
            return redirect()->route('admin.practice.index', [
                'grade' => $request->query('grade'),
                'open_topic' => $practiceQuiz->practice_topic_id,
            ])->with('success', $message);
        }

        return back()->with('success', $message);
    }

    public function destroy(PracticeQuiz $practiceQuiz): RedirectResponse
    {
        $topic = $practiceQuiz->topic;
        $practiceQuiz->delete();

        return redirect()->route('admin.practice.index', [
            'grade' => $topic->practice_grade_id,
            'open_topic' => $topic->id,
        ])->with('success', 'Đã xóa bài trắc nghiệm.');
    }

    public function attachQuestions(Request $request, PracticeQuiz $practiceQuiz): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'bank_ids' => ['required', 'array', 'min:1'],
            'bank_ids.*' => ['integer', 'exists:question_bank_items,id'],
        ]);

        $existingIds = $practiceQuiz->questionBankItems()->pluck('question_bank_items.id')->all();
        $nextSort = (int) ($practiceQuiz->questionBankItems()->max('practice_quiz_question_bank_item.sort_order') ?? 0) + 1;

        $added = 0;
        foreach ($validated['bank_ids'] as $bankId) {
            if (in_array((int) $bankId, $existingIds, true)) {
                continue;
            }

            $practiceQuiz->questionBankItems()->attach((int) $bankId, ['sort_order' => $nextSort++]);
            $added++;
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['added' => $added]]);
        }

        return back()->with('success', $added > 0 ? "Đã thêm {$added} câu hỏi." : 'Các câu đã chọn đều đã có trong bài.');
    }

    public function detachQuestion(PracticeQuiz $practiceQuiz, QuestionBankItem $question_bank): RedirectResponse
    {
        $practiceQuiz->questionBankItems()->detach($question_bank->id);

        $remaining = $practiceQuiz->questionBankItems()->orderByPivot('sort_order')->get();
        foreach ($remaining as $index => $item) {
            $practiceQuiz->questionBankItems()->updateExistingPivot($item->id, ['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Đã bỏ câu hỏi khỏi bài.');
    }

    public function reorderQuestions(Request $request, PracticeQuiz $practiceQuiz): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $bankIds = collect($validated['order'])->map(fn ($id) => (int) $id)->values();
        $owned = $practiceQuiz->questionBankItems()->pluck('question_bank_items.id');

        if ($owned->count() !== $bankIds->count() || $bankIds->diff($owned)->isNotEmpty()) {
            return response()->json(['success' => false, 'error' => 'Danh sách câu hỏi không hợp lệ.'], 422);
        }

        foreach ($bankIds as $index => $bankId) {
            $practiceQuiz->questionBankItems()->updateExistingPivot($bankId, ['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function syncClasses(Request $request, PracticeQuiz $practiceQuiz): RedirectResponse
    {
        $validated = $request->validate([
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:student_classes,id'],
        ]);

        $practiceQuiz->studentClasses()->sync($validated['class_ids'] ?? []);

        return back()->with('success', 'Đã cập nhật danh sách lớp cho bài.');
    }
}
